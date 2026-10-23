<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Factory tombol "Cetak Checklist" (bulk-selection / massal-per-lokasi).
 * Beda dari PrintBarcodeActions: ini gak nyetak gambar barcode dan gak
 * lewat antrian printer thermal -- cuma buka halaman cetak lembar
 * checklist (data barang polos) buat dicetak lewat printer biasa/PDF,
 * jadi barang yang belum punya barcode pun tetap ikut kecetak.
 */
class PrintChecklistActions
{
    /**
     * Tombol bulk (checkbox pilih-banyak baris di tabel). Menerima Asset
     * langsung (AssetResource/BaseCategoryAssetResource) atau UnifiedItem
     * (UnifiedItemResource) -- baris persediaan dilewati karena checklist
     * ini berbasis data Asset (kategori/lokasi/PIC).
     */
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('printChecklistBulk')
            ->label('Cetak Checklist')
            ->hiddenLabel(fn () => Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin'))
            ->tooltip(fn () => (Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin')) ? 'Cetak Checklist' : null)
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->url(fn (Collection $records) => route('checklist.print', ['ids' => implode(',', static::resolveAssetIds($records))]))
            ->openUrlInNewTab()
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Header action massal per Gedung + Ruangan.
     */
    public static function byLocationHeaderAction(): Action
    {
        return Action::make('printChecklistByLocation')
            ->label('Cetak Checklist (Massal)')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->modalHeading('Cetak Checklist - per Ruangan')
            ->modalWidth('lg')
            ->form([
                Select::make('campus_id')
                    ->label('Gedung / Kampus')
                    ->options(fn () => Campus::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('location_id', null))
                    ->required(),

                Select::make('location_id')
                    ->label('Ruangan')
                    ->options(fn (callable $get) => Location::when($get('campus_id'), fn ($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('campus_id')))
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            $savedSort = session('last_print_sort_' . $state, session('last_print_sort', 'notes'));
                            $set('sort_by', $savedSort);
                        }
                    }),

                Select::make('sort_by')
                    ->label('Urutan Cetak')
                    ->options([
                        'notes' => 'Keterangan (A–Z)',
                        'name' => 'Nama Barang (A–Z)',
                        'inventory_number' => 'Kode Inventaris (A–Z)',
                    ])
                    ->default('notes')
                    ->required()
                    ->helperText('Urutan data pada lembar checklist. Barcode yang dicetak nanti akan otomatis mengikuti urutan ini.'),
            ])
            ->action(function (array $data, $livewire) {
                $sortBy = $data['sort_by'] ?? 'notes';
                session(['last_print_sort_' . $data['location_id'] => $sortBy]);
                session(['last_print_sort' => $sortBy]);

                $url = route('checklist.print', [
                    'location_id' => $data['location_id'],
                    'sort_by' => $sortBy,
                ]);
                $livewire->js('window.open(' . json_encode($url) . ", '_blank')");
            })
            ->modalSubmitActionLabel('Cetak')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * $records isi Asset langsung, atau UnifiedItem (campuran row_type
     * 'asset'/'supply' -- 'supply' gak punya field Asset, dilewati).
     *
     * @return array<int, int>
     */
    protected static function resolveAssetIds(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        if ($records->first() instanceof Asset) {
            return $records->pluck('id')->all();
        }

        return $records->where('row_type', 'asset')->pluck('raw_id')->all();
    }
}
