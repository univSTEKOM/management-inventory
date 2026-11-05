<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\PrintJobResource\Pages;
use App\Models\PrintJob;
use App\Models\PrinterStation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PrintJobResource extends Resource
{
    protected static ?string $model = PrintJob::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-printer';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getModelLabel(): string
    {
        return 'Antrean Cetak Barcode';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Antrean Cetak Barcode';
    }

    public static function getNavigationLabel(): string
    {
        return 'Antrean Cetak Barcode';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasPermissionTo('print_jobs.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasPermissionTo('print_jobs.delete');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'station', 'requestedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'completed'  => 'success',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'pending'    => 'heroicon-m-clock',
                        'processing' => 'heroicon-m-arrow-path',
                        'completed'  => 'heroicon-m-check-circle',
                        'failed'     => 'heroicon-m-x-circle',
                        default      => null,
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending'    => 'Menunggu',
                        'processing' => 'Sedang Diproses',
                        'completed'  => 'Selesai Dicetak',
                        'failed'     => 'Gagal',
                        default      => ucfirst($state ?? '-'),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('inventory_number')
                    ->label('Kode Barang / SKU')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Nama Barang')
                    ->default('-')
                    ->searchable()
                    ->limit(35),

                Tables\Columns\TextColumn::make('location_label')
                    ->label('Ruangan / Lokasi')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('printer_station')
                    ->label('Printer Station')
                    ->badge()
                    ->color(fn (PrintJob $record): string => ($record->station?->isOnline() ?? false) ? 'success' : 'gray')
                    ->formatStateUsing(function (PrintJob $record, ?string $state): string {
                        $stationName = $record->station?->name ?? ($state ?? '-');
                        $online = $record->station?->isOnline() ?? false;
                        return $stationName . ($online ? ' (Online)' : ' (Offline)');
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Diminta Oleh')
                    ->default('System')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Antre')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('printed_at')
                    ->label('Waktu Dicetak')
                    ->dateTime('d M Y, H:i:s')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Percobaan')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Pesan Error')
                    ->limit(30)
                    ->color('danger')
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'    => 'Menunggu (Pending)',
                        'processing' => 'Sedang Diproses (Processing)',
                        'completed'  => 'Selesai (Completed)',
                        'failed'     => 'Gagal (Failed)',
                    ]),

                Tables\Filters\SelectFilter::make('printer_station')
                    ->label('Printer Station')
                    ->options(fn () => PrinterStation::orderBy('name')->pluck('name', 'station_key')),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Components\DatePicker::make('from')->label('Dari Tanggal'),
                        Components\DatePicker::make('to')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Cetak Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function () {
                        $user = Auth::user();
                        return $user?->hasRole('Superadmin')
                            || $user?->hasRole('superadmin')
                            || ($user?->hasPermissionTo('print_jobs.retry') ?? false);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Cetak Ulang Barcode?')
                    ->modalDescription(fn (PrintJob $record) => "Antrean untuk barang {$record->inventory_number} (" . ($record->asset?->name ?? 'Barang') . ") akan dikembalikan ke status Menunggu (Pending) agar dicetak oleh printer station.")
                    ->action(function (PrintJob $record) {
                        $record->update([
                            'status'        => 'pending',
                            'error_message' => null,
                        ]);

                        Notification::make()
                            ->title('Barcode dimasukkan kembali ke antrean cetak.')
                            ->success()
                            ->send();
                    }),

                Action::make('viewError')
                    ->label('Lihat Error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (PrintJob $record) => filled($record->error_message))
                    ->modalHeading('Detail Error Cetak Barcode')
                    ->modalContent(fn (PrintJob $record) => new HtmlString(
                        '<div class="p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-lg text-sm text-red-900 dark:text-red-300">'
                        . '<div class="font-semibold mb-1">Pesan Error:</div>'
                        . '<pre class="whitespace-pre-wrap font-mono text-xs">' . e($record->error_message) . '</pre>'
                        . '<div class="mt-3 text-xs text-gray-600 dark:text-gray-400">'
                        . 'Tip: Pastikan printer TSC TE210 menyala, kabel USB terhubung, driver Seagull terbaca di Devices and Printers, dan terminal PowerShell PrintAgent di PC printer sedang berjalan.'
                        . '</div>'
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('retryBulk')
                    ->label('Cetak Ulang Terpilih')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function () {
                        $user = Auth::user();
                        return $user?->hasRole('Superadmin')
                            || $user?->hasRole('superadmin')
                            || ($user?->hasPermissionTo('print_jobs.retry') ?? false);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Cetak Ulang Barcode Terpilih?')
                    ->modalDescription('Semua baris yang dipilih akan diubah statusnya menjadi Menunggu (Pending) untuk dicetak ulang.')
                    ->action(function (Collection $records) {
                        $count = $records->count();
                        PrintJob::whereIn('id', $records->pluck('id'))->update([
                            'status'        => 'pending',
                            'error_message' => null,
                        ]);

                        Notification::make()
                            ->title("{$count} barcode dimasukkan kembali ke antrean cetak.")
                            ->success()
                            ->send();
                    }),

                DeleteBulkAction::make()
                    ->visible(function () {
                        $user = Auth::user();
                        return $user?->hasRole('Superadmin')
                            || $user?->hasRole('superadmin')
                            || ($user?->hasPermissionTo('print_jobs.delete') ?? false);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintJobs::route('/'),
        ];
    }
}

