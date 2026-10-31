<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action "Ubah Merk/Tipe" -- mengubah merk / tipe barang yang dipilih secara massal.
 *
 * Mendukung Asset (BaseCategoryAssetResource, AssetResource), InventoryBalance (BHP),
 * maupun UnifiedItem (Semua Barang).
 * Baris BHP yang jadi identik (kategori, nama, merk, ruangan) akan otomatis digabung
 * via InventoryBalanceMerger.
 */
class ChangeBrandActions
{
    public static function userCanBulkEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasPermissionTo('assets.bulk_edit_brand');
    }

    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('changeBrandBulk')
            ->label('Ubah Merk/Tipe')
            ->hiddenLabel()
            ->tooltip('Ubah Merk/Tipe')
            ->icon('heroicon-o-bookmark')
            ->color('info')
            ->visible(fn () => static::userCanBulkEdit(Auth::user()))
            ->authorize(fn () => static::userCanBulkEdit(Auth::user()))
            ->modalHeading('Ubah Merk / Tipe Barang')
            ->modalDescription('Mengubah merk / tipe untuk semua barang yang dipilih. Stok BHP yang identik (kategori, nama, merk, ruangan) akan digabung secara otomatis.')
            ->modalWidth('md')
            ->form([
                Select::make('brand')
                    ->label('Merk / Tipe')
                    ->placeholder('Pilih dari daftar merk atau tambah baru...')
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        $assetBrands = Asset::whereNotNull('brand')
                            ->where('brand', '<>', '')
                            ->distinct()
                            ->pluck('brand', 'brand');

                        $balanceBrands = InventoryBalance::whereNotNull('brand')
                            ->where('brand', '<>', '')
                            ->distinct()
                            ->pluck('brand', 'brand');

                        return $assetBrands->merge($balanceBrands)->sort()->all();
                    })
                    ->createOptionForm([
                        TextInput::make('brand')
                            ->label('Merk / Tipe Baru')
                            ->placeholder('Contoh: Lenovo, Canon, Dell, dll.')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionAction(fn ($action) => $action->label('Tambah Merk Baru')->modalHeading('Tambah Merk / Tipe Baru'))
                    ->createOptionUsing(fn (array $data) => trim($data['brand']))
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $newBrand = trim($data['brand']);

                $assets = static::resolveAssets($records);
                $balances = static::resolveBalances($records);

                $changed = 0;
                $merged = 0;

                try {
                    DB::transaction(function () use ($assets, $balances, $newBrand, &$changed, &$merged) {
                        foreach ($assets as $asset) {
                            $asset->update(['brand' => $newBrand]);
                            $changed++;
                        }

                        foreach ($balances as $balance) {
                            InventoryBalanceMerger::apply($balance, ['brand' => $newBrand], 'updated') ? $merged++ : $changed++;
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal mengubah merk/tipe')->body($e->getMessage())->danger()->send();

                    return;
                }

                $message = "{$changed} barang berhasil diperbarui merk/tipenya";
                if ($merged > 0) {
                    $message .= ", {$merged} BHP digabung dengan stok yang sudah ada";
                }

                Notification::make()->title($message)->success()->send();
            })
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->first() instanceof Asset) {
            return $records;
        }

        if ($records->first() instanceof InventoryBalance) {
            return collect();
        }

        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))->get();
    }

    /**
     * @return Collection<int, InventoryBalance>
     */
    protected static function resolveBalances(Collection $records): Collection
    {
        if ($records->first() instanceof InventoryBalance) {
            return $records;
        }

        if ($records->first() instanceof Asset) {
            return collect();
        }

        return InventoryBalance::whereIn('id', $records->where('row_type', 'supply')->pluck('raw_id'))->get();
    }
}
