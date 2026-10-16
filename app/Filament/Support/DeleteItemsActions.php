<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action "Hapus" -- menghapus beberapa barang sekaligus dengan pop-up konfirmasi.
 *
 * Mendukung Asset (BaseCategoryAssetResource, AssetResource), InventoryBalance (BHP),
 * maupun UnifiedItem (Semua Barang).
 * Aset akan di-soft-delete (sesuai AssetObserver & audit log).
 * Stok BHP (InventoryBalance) akan dihapus langsung beserta relasinya.
 */
class DeleteItemsActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('deleteSelectedBulk')
            ->label('Hapus')
            ->hiddenLabel(fn () => Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin'))
            ->tooltip(fn () => (Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin')) ? 'Hapus' : null)
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn () => Auth::user()?->hasRole('Superadmin') || Auth::user()?->hasRole('superadmin') || (Auth::user()?->hasPermissionTo('assets.delete') ?? false))
            ->authorize(fn () => Auth::user()?->hasRole('Superadmin') || Auth::user()?->hasRole('superadmin') || (Auth::user()?->hasPermissionTo('assets.delete') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Hapus Data Terpilih')
            ->modalDescription('Apakah Anda yakin ingin menghapus data yang dipilih? Data aset yang dihapus akan dipindahkan ke riwayat terhapus (soft delete).')
            ->modalSubmitActionLabel('Ya, Hapus')
            ->modalCancelActionLabel('Batal')
            ->modalIcon('heroicon-o-trash')
            ->action(function (Collection $records) {
                $assets = static::resolveAssets($records);
                $balances = static::resolveBalances($records);

                $deleted = 0;

                try {
                    DB::transaction(function () use ($assets, $balances, &$deleted) {
                        foreach ($assets as $asset) {
                            $asset->delete();
                            $deleted++;
                        }

                        foreach ($balances as $balance) {
                            $balance->delete();
                            $deleted++;
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Gagal menghapus data')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    return;
                }

                if ($deleted > 0) {
                    Notification::make()
                        ->title("{$deleted} data berhasil dihapus.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Tidak ada data yang dihapus.')
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

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
        if ($records->isEmpty()) {
            return collect();
        }

        if ($records->first() instanceof InventoryBalance) {
            return $records;
        }

        if ($records->first() instanceof Asset) {
            return collect();
        }

        return InventoryBalance::whereIn('id', $records->where('row_type', 'supply')->pluck('raw_id'))->get();
    }
}
