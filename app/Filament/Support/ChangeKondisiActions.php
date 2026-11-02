<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChangeKondisiActions
{
    public static function userCanBulkChangeKondisi(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasPermissionTo('assets.bulk_change_kondisi');
    }

    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('changeKondisiBulk')
            ->label('Ubah Kondisi')
            ->hiddenLabel()
            ->tooltip('Ubah Kondisi')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('info')
            ->visible(fn () => static::userCanBulkChangeKondisi(Auth::user()))
            ->authorize(fn () => static::userCanBulkChangeKondisi(Auth::user()))
            ->modalHeading('Ubah Kondisi Barang')
            ->modalDescription('Mengubah kondisi untuk semua barang yang dipilih. Khusus Aset.')
            ->modalWidth('md')
            ->form([
                Select::make('new_kondisi')
                    ->label('Kondisi Baru')
                    ->options([
                        'good'                     => 'Baik',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label('Alasan Perubahan')
                    ->required()
            ])
            ->action(function (Collection $records, array $data) {
                $newKondisi = $data['new_kondisi'];
                $reason = $data['reason'];

                $changed = 0;

                try {
                    DB::transaction(function () use ($records, $newKondisi, $reason, &$changed) {
                        foreach (static::resolveAssets($records) as $asset) {
                            $lockedAsset = Asset::where('id', $asset->id)->lockForUpdate()->first();

                            request()->merge(['status_change_reason' => $reason]);
                            $lockedAsset->update([
                                'kondisi' => $newKondisi,
                            ]);
                            $changed++;
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal mengubah kondisi')->body($e->getMessage())->danger()->send();
                    return;
                }

                if ($changed > 0) {
                    Notification::make()->title("{$changed} barang diubah kondisinya")->success()->send();
                } else {
                    Notification::make()->title('Tidak ada barang yang diubah')->warning()->send();
                }
            })
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

        // For UnifiedItem
        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))->get();
    }
}