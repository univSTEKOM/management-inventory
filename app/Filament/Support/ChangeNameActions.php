<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action "Ubah Nama Barang" -- mengubah nama barang yang dipilih secara massal.
 *
 * Mendukung Asset, InventoryBalance (BHP), maupun UnifiedItem (Semua Barang).
 * Baris BHP yang jadi identik (kategori, nama, merk, ruangan) digabung via InventoryBalanceMerger.
 */
class ChangeNameActions
{
    public static function userCanBulkEditName(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasPermissionTo('assets.bulk_edit_name');
    }

    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('changeNameBulk')
            ->label('Ubah Nama Barang')
            ->hiddenLabel()
            ->tooltip('Ubah Nama Barang')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->visible(fn () => static::userCanBulkEditName(Auth::user()))
            ->authorize(fn () => static::userCanBulkEditName(Auth::user()))
            ->modalHeading('Ubah Nama Barang')
            ->modalDescription('Mengubah nama untuk semua barang yang dipilih. Stok BHP yang identik (kategori, nama, merk, ruangan) akan digabung secara otomatis.')
            ->modalWidth('md')
            ->form([
                TextInput::make('name')
                    ->label('Nama Barang Baru')
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn ($state) => trim((string) $state)),
            ])
            ->action(function (Collection $records, array $data) {
                $newName = trim($data['name']);

                $assets = static::resolveAssets($records);
                $balances = static::resolveBalances($records);

                $changed = 0;
                $merged = 0;

                try {
                    DB::transaction(function () use ($assets, $balances, $newName, &$changed, &$merged) {
                        foreach ($assets as $asset) {
                            $asset->update(['name' => $newName]);
                            $changed++;
                        }

                        foreach ($balances as $balance) {
                            InventoryBalanceMerger::apply($balance, ['name' => $newName], 'updated') ? $merged++ : $changed++;
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal mengubah nama barang')->body($e->getMessage())->danger()->send();

                    return;
                }

                $message = "{$changed} barang berhasil diperbarui namanya";
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
