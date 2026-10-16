<?php

namespace App\Support;

use App\Models\AssetPhoto;
use App\Models\InventoryBalance;
use Illuminate\Support\Facades\Storage;

/**
 * Satu file foto bisa dipakai banyak barang sekaligus: bulk "Tambah Foto
 * Massal" menyimpan file sekali lalu mereferensikan path yang sama di
 * `asset_photos.file_path` dan/atau `inventory_balances.photo`, dan
 * "Duplikat Barang" (EditAsset) menyalin baris foto apa adanya.
 *
 * Karena itu file fisik hanya boleh dihapus kalau sudah tidak ada barang
 * lain yang mereferensikannya.
 */
class SharedPhotoFiles
{
    /**
     * Apakah `$path` masih direferensikan barang lain.
     *
     * Foto milik aset yang di-soft-delete tetap dihitung, karena aset itu
     * masih bisa di-restore.
     */
    public static function isReferenced(string $path, ?int $exceptAssetId = null, ?int $exceptBalanceId = null): bool
    {
        $usedByAsset = AssetPhoto::query()
            ->where('file_path', $path)
            ->when($exceptAssetId, fn ($query) => $query->where('asset_id', '!=', $exceptAssetId))
            ->exists();

        if ($usedByAsset) {
            return true;
        }

        return InventoryBalance::query()
            ->where('photo', $path)
            ->when($exceptBalanceId, fn ($query) => $query->whereKeyNot($exceptBalanceId))
            ->exists();
    }

    /**
     * Hapus file dari disk yang diberikan, kecuali masih dipakai barang lain.
     *
     * @param  array<int, string>  $disks
     */
    public static function deleteIfUnreferenced(?string $path, array $disks, ?int $exceptAssetId = null, ?int $exceptBalanceId = null): void
    {
        if (blank($path) || static::isReferenced($path, $exceptAssetId, $exceptBalanceId)) {
            return;
        }

        foreach (array_unique($disks) as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
