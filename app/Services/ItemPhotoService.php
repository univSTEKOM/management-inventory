<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\InventoryBalance;
use App\Models\UnifiedItem;
use App\Models\User;
use App\Support\SharedPhotoFiles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Menerapkan 1-3 file foto ke satu atau banyak barang (Tambah Foto /
 * Tambah Foto Massal). Tiap file disimpan sekali, lalu path-nya
 * direferensikan oleh setiap barang sesuai mekanisme penyimpanan existing:
 *
 * - Aset, Inventaris, BHP individual (model Asset) -> baris `asset_photos`
 *   (maks 3 foto per barang, sama dengan form AssetResource).
 * - Stok BHP (InventoryBalance) -> kolom tunggal `inventory_balances.photo`,
 *   jadi hanya foto pertama yang dipakai.
 *
 * Disk `s3_thumb` dan `s3` menunjuk bucket yang sama; `s3_thumb` hanya
 * mengganti URL publik ke route thumbnail.
 */
class ItemPhotoService
{
    public const DISK = 's3_thumb';

    public const DIRECTORY = 'asset-photos';

    public const MAX_ASSET_PHOTOS = 3;

    public const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Barang yang sudah punya foto tidak diubah (default). */
    public const MODE_SKIP = 'skip';

    /** Aset: tambahkan sebagai foto tambahan selama total <= MAX_ASSET_PHOTOS. Stok BHP (1 foto) tidak bisa. */
    public const MODE_ADD = 'add';

    /** Ganti seluruh foto lama barang dengan foto baru. */
    public const MODE_REPLACE = 'replace';

    public const MODES = [self::MODE_SKIP, self::MODE_ADD, self::MODE_REPLACE];

    public static function userCanManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('superadmin')
            || $user->hasRole('Superadmin')
            || $user->hasPermissionTo('assets.update');
    }

    /**
     * Petakan record tabel (Asset, InventoryBalance, atau UnifiedItem) ke id
     * barang yang dituju.
     *
     * @return array{assets: array<int, int>, balances: array<int, int>}
     */
    public static function targetsFromRecords(iterable $records): array
    {
        $targets = ['assets' => [], 'balances' => []];

        foreach ($records as $record) {
            match (true) {
                $record instanceof Asset => $targets['assets'][] = (int) $record->getKey(),
                $record instanceof InventoryBalance => $targets['balances'][] = (int) $record->getKey(),
                $record instanceof UnifiedItem && $record->row_type === 'asset' => $targets['assets'][] = (int) $record->raw_id,
                $record instanceof UnifiedItem && $record->row_type === 'supply' => $targets['balances'][] = (int) $record->raw_id,
                default => null,
            };
        }

        return [
            'assets' => array_values(array_unique($targets['assets'])),
            'balances' => array_values(array_unique($targets['balances'])),
        ];
    }

    /**
     * Identitas & status foto tiap barang untuk ditampilkan sebelum konfirmasi.
     * Dua query total, tanpa memuat file foto.
     *
     * @param  array{assets?: array<int, int>, balances?: array<int, int>}  $targets
     * @return Collection<int, array{type: string, id: int, label: string, photo_count: int}>
     */
    public function describe(array $targets): Collection
    {
        $assets = empty($targets['assets'])
            ? collect()
            : Asset::query()
                ->whereKey($targets['assets'])
                ->withCount('photos')
                ->orderBy('name')
                ->get(['id', 'name', 'brand', 'inventory_number', 'barcode']);

        $balances = empty($targets['balances'])
            ? collect()
            : InventoryBalance::query()
                ->whereKey($targets['balances'])
                ->orderBy('name')
                ->get(['id', 'name', 'brand', 'master_barcode', 'photo']);

        return $assets
            ->map(fn (Asset $asset) => [
                'type' => 'asset',
                'id' => (int) $asset->id,
                'label' => static::assetLabel($asset),
                'photo_count' => (int) $asset->photos_count,
            ])
            ->concat($balances->map(fn (InventoryBalance $balance) => [
                'type' => 'supply',
                'id' => (int) $balance->id,
                'label' => static::balanceLabel($balance),
                'photo_count' => filled($balance->photo) ? 1 : 0,
            ]))
            ->values();
    }

    /**
     * Terapkan foto `$paths` (urut, maks MAX_ASSET_PHOTOS) ke barang tujuan. Data barang dibaca ulang dan
     * dikunci di sini (bukan dari state form), sehingga barang yang sudah
     * dihapus atau sudah mendapat foto dari pengguna lain ikut tertangani.
     * Tiap barang diproses di savepoint sendiri: kegagalan satu barang tidak
     * membatalkan barang lain dan tercatat di hasil.
     *
     * @param  array{assets?: array<int, int>, balances?: array<int, int>}  $targets
     * @param  string|array<int, string>  $paths
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException file/mode tidak valid (tidak ada data diubah)
     * @throws RuntimeException file tidak ditemukan (tidak ada data diubah)
     */
    public function apply(array $targets, string|array $paths, string $mode, ?User $user): ItemPhotoResult
    {
        if (! static::userCanManage($user)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengubah foto barang.');
        }

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Pilihan penanganan foto existing tidak dikenal.');
        }

        $paths = static::normalizePaths($paths);

        if ($paths === []) {
            throw new InvalidArgumentException('Foto belum dipilih.');
        }

        if (count($paths) > self::MAX_ASSET_PHOTOS) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_ASSET_PHOTOS.' foto per unggahan.');
        }

        $meta = [];
        foreach ($paths as $path) {
            $meta[$path] = $this->validateStoredFile($path);
        }

        $assetIds = array_values(array_unique(array_map('intval', $targets['assets'] ?? [])));
        $balanceIds = array_values(array_unique(array_map('intval', $targets['balances'] ?? [])));

        $result = new ItemPhotoResult;

        DB::transaction(function () use ($assetIds, $balanceIds, $paths, $mode, $meta, $result) {
            $assets = $assetIds === []
                ? collect()
                : Asset::query()
                    ->whereKey($assetIds)
                    ->with('photos:id,asset_id,file_path,sort_order')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            $balances = $balanceIds === []
                ? collect()
                : InventoryBalance::query()
                    ->whereKey($balanceIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            foreach ($assetIds as $id) {
                $asset = $assets->get($id);

                if (! $asset) {
                    $result->fail("Aset ID {$id}", 'Barang tidak ditemukan atau sudah dihapus.');

                    continue;
                }

                $this->applyToAsset($asset, $paths, $mode, $meta, $result);
            }

            foreach ($balanceIds as $id) {
                $balance = $balances->get($id);

                if (! $balance) {
                    $result->fail("Stok BHP ID {$id}", 'Barang tidak ditemukan atau sudah dihapus.');

                    continue;
                }

                if ($this->applyToBalance($balance, $paths[0], $mode, $result) && count($paths) > 1) {
                    $result->note('Stok BHP hanya menyimpan 1 foto; yang dipakai foto pertama.');
                }
            }
        });

        return $result;
    }

    /**
     * Hapus file upload yang akhirnya tidak dipakai barang mana pun
     * (mis. semua barang dilewati, proses gagal, atau foto ke-2/3 yang hanya
     * diterapkan ke Stok BHP).
     *
     * @param  string|array<int, string>|null  $paths
     */
    public function discardUnusedUpload(string|array|null $paths): void
    {
        foreach (static::normalizePaths($paths ?? []) as $path) {
            if ($this->isUploadPath($path)) {
                SharedPhotoFiles::deleteIfUnreferenced($path, [self::DISK]);
            }
        }
    }

    /**
     * Ubah state FileUpload (string atau array, bisa berkunci UUID) menjadi
     * daftar path unik yang urutannya dipertahankan.
     *
     * @return array<int, string>
     */
    public static function normalizePaths(mixed $paths): array
    {
        $paths = is_array($paths) ? $paths : [$paths];

        return array_values(array_unique(array_filter(
            $paths,
            fn ($path) => is_string($path) && $path !== '',
        )));
    }

    public static function assetLabel(Asset $asset): string
    {
        return static::formatLabel($asset->inventory_number ?: $asset->barcode, $asset->name, $asset->brand);
    }

    public static function balanceLabel(InventoryBalance $balance): string
    {
        return static::formatLabel($balance->master_barcode, $balance->name, $balance->brand).' [Stok BHP]';
    }

    protected static function formatLabel(?string $code, ?string $name, ?string $brand): string
    {
        $label = trim(($code ? "{$code} — " : '').($name ?? '-'));

        return filled($brand) ? "{$label} ({$brand})" : $label;
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<string, array{file_size: int|null, mime_type: string|null}>  $meta
     */
    protected function applyToAsset(Asset $asset, array $paths, string $mode, array $meta, ItemPhotoResult $result): void
    {
        $label = static::assetLabel($asset);
        $photos = $asset->photos;
        $newPaths = array_values(array_diff($paths, $photos->pluck('file_path')->all()));

        if ($newPaths === []) {
            $result->skip($label, 'Foto ini sudah terpasang.');

            return;
        }

        if ($photos->isNotEmpty() && $mode === self::MODE_SKIP) {
            $result->skip($label, 'Sudah memiliki foto (tidak ditimpa).');

            return;
        }

        if ($mode === self::MODE_ADD && $photos->count() + count($newPaths) > self::MAX_ASSET_PHOTOS) {
            $room = max(0, self::MAX_ASSET_PHOTOS - $photos->count());
            $result->fail($label, "Sudah {$photos->count()} foto, hanya muat {$room} lagi (maks ".self::MAX_ASSET_PHOTOS.'). Pilih opsi "Ganti foto lama" atau unggah lebih sedikit foto.');

            return;
        }

        try {
            DB::transaction(function () use ($asset, $photos, $paths, $newPaths, $mode, $meta) {
                $sortOrder = 0;

                if ($mode === self::MODE_REPLACE) {
                    foreach ($photos as $photo) {
                        // Observer: audit log + hapus file (hanya jika tak dipakai barang lain, setelah commit).
                        $photo->setRelation('asset', $asset)->delete();
                    }
                    $newPaths = $paths;
                } elseif ($photos->isNotEmpty()) {
                    $sortOrder = (int) $photos->max('sort_order') + 1;
                }

                foreach ($newPaths as $path) {
                    $photo = new AssetPhoto([
                        'file_path' => $path,
                        'file_size' => $meta[$path]['file_size'],
                        'mime_type' => $meta[$path]['mime_type'],
                        'sort_order' => $sortOrder++,
                    ]);
                    // Hindari lazy-load asset per foto di observer audit log.
                    $photo->setRelation('asset', $asset);
                    $asset->photos()->save($photo);
                }
            });
        } catch (Throwable $e) {
            report($e);
            $result->fail($label, 'Gagal menyimpan ke database.');

            return;
        }

        $result->succeed($label);
    }

    /** @return bool true jika foto stok BHP berubah */
    protected function applyToBalance(InventoryBalance $balance, string $path, string $mode, ItemPhotoResult $result): bool
    {
        $label = static::balanceLabel($balance);
        $oldPath = $balance->photo;

        if ($oldPath === $path) {
            $result->skip($label, 'Foto ini sudah terpasang.');

            return false;
        }

        if (filled($oldPath) && $mode === self::MODE_SKIP) {
            $result->skip($label, 'Sudah memiliki foto (tidak ditimpa).');

            return false;
        }

        if (filled($oldPath) && $mode === self::MODE_ADD) {
            $result->fail($label, 'Stok BHP hanya mendukung 1 foto. Pilih opsi "Ganti foto lama" untuk mengganti.');

            return false;
        }

        try {
            DB::transaction(fn () => $balance->update(['photo' => $path]));
        } catch (Throwable $e) {
            report($e);
            $result->fail($label, 'Gagal menyimpan ke database.');

            return false;
        }

        if (filled($oldPath)) {
            // Sama seperti edit Stok BHP: file lama di disk s3, hapus hanya jika
            // tidak dipakai barang lain, dan setelah transaksi luar commit.
            DB::afterCommit(fn () => SharedPhotoFiles::deleteIfUnreferenced($oldPath, ['s3']));
        }

        $result->succeed($label);

        return true;
    }

    /**
     * Validasi ulang file di backend: path harus hasil upload ke folder foto
     * barang, file harus ada, dan bertipe gambar yang diizinkan.
     *
     * @return array{file_size: int|null, mime_type: string|null}
     */
    protected function validateStoredFile(string $path): array
    {
        if (! $this->isUploadPath($path)) {
            throw new InvalidArgumentException('Path file foto tidak valid.');
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            throw new RuntimeException('File foto tidak ditemukan di penyimpanan. Silakan unggah ulang.');
        }

        // Deteksi dari isi file, bukan ekstensi/metadata. Cukup header 4KB
        // pertama sehingga foto besar tidak perlu diunduh penuh.
        $stream = $disk->readStream($path);
        $head = is_resource($stream) ? (string) fread($stream, 4096) : '';
        if (is_resource($stream)) {
            fclose($stream);
        }
        $mimeType = $head === '' ? null : (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);

        if (! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('File harus berupa gambar JPG, PNG, atau WebP.');
        }

        return [
            'file_size' => $disk->size($path),
            'mime_type' => $mimeType,
        ];
    }

    protected function isUploadPath(string $path): bool
    {
        return str_starts_with($path, self::DIRECTORY.'/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }
}
