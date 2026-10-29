<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\UnifiedItem;
use App\Services\ItemPhotoResult;
use App\Services\ItemPhotoService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Aksi foto barang:
 *
 * - bulkAction(): "Tambah Foto Massal" -- 1-3 foto diterapkan ke semua barang
 *   terpilih. Modal menampilkan daftar barang, preview foto, dan meminta
 *   konfirmasi. Barang yang sudah punya foto TIDAK diubah kecuali pengguna
 *   memilih opsi tambah/ganti secara eksplisit.
 * - rowAction(): "Tambah Foto" per baris di Semua Barang untuk barang yang
 *   belum punya foto.
 *
 * Mendukung Asset (Aset, Inventaris, BHP individual -> asset_photos),
 * InventoryBalance / baris stok BHP di UnifiedItem (-> inventory_balances.photo).
 * Logika penyimpanan ada di ItemPhotoService.
 */
class AddPhotoActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('addPhotoBulk')
            ->label('Tambah Foto Massal')
            ->hiddenLabel()
            ->tooltip('Tambah Foto Massal')
            ->icon('heroicon-o-camera')
            ->color('success')
            ->visible(fn () => ItemPhotoService::userCanManage(Auth::user()))
            ->authorize(fn () => ItemPhotoService::userCanManage(Auth::user()))
            ->modalHeading('Tambah Foto Massal')
            ->modalDescription('Foto (maks '.ItemPhotoService::MAX_ASSET_PHOTOS.') akan diterapkan ke setiap barang terpilih. Periksa daftar barang sebelum menyimpan.')
            ->modalWidth('2xl')
            ->schema(function (Collection $records, $livewire): array {
                $items = app(ItemPhotoService::class)->describe(ItemPhotoService::targetsFromRecords($records));
                $missing = static::countMissingSelections($records, $livewire);

                if ($items->isEmpty()) {
                    return [
                        Placeholder::make('no_items')
                            ->hiddenLabel()
                            ->content('Tidak ada barang terpilih yang dapat diberi foto. Barang mungkin sudah dihapus.'),
                    ];
                }

                $withPhoto = $items->where('photo_count', '>', 0)->count();
                $hasSupply = $items->contains('type', 'supply');

                return [
                    Placeholder::make('summary')
                        ->hiddenLabel()
                        ->content(static::summaryHtml($items, $withPhoto, $missing)),

                    static::photoUpload(ItemPhotoService::MAX_ASSET_PHOTOS, $hasSupply),

                    Radio::make('existing_mode')
                        ->label("Penanganan {$withPhoto} barang yang sudah memiliki foto")
                        ->options([
                            ItemPhotoService::MODE_SKIP => 'Lewati (foto lama tidak diubah)',
                            ItemPhotoService::MODE_ADD => 'Tambahkan ke foto yang ada (total maks '.ItemPhotoService::MAX_ASSET_PHOTOS.' per barang; barang yang tidak muat gagal; Stok BHP hanya 1 foto)',
                            ItemPhotoService::MODE_REPLACE => 'Ganti foto lama dengan foto ini',
                        ])
                        ->default(ItemPhotoService::MODE_SKIP)
                        ->required()
                        ->visible($withPhoto > 0),

                    Checkbox::make('confirmed')
                        ->label("Saya sudah memeriksa daftar {$items->count()} barang dan foto di atas.")
                        ->accepted()
                        ->validationMessages(['accepted' => 'Centang konfirmasi sebelum menyimpan.']),
                ];
            })
            ->action(function (Collection $records, array $data, $livewire) {
                $missing = static::countMissingSelections($records, $livewire);

                $result = static::runApply(
                    ItemPhotoService::targetsFromRecords($records),
                    $data['photo'] ?? null,
                    $data['existing_mode'] ?? ItemPhotoService::MODE_SKIP,
                );

                if (! $result) {
                    return;
                }

                if ($missing > 0) {
                    $result->fail("{$missing} barang terpilih", 'Tidak lagi tersedia (sudah dihapus atau keluar dari daftar).');
                }

                $result->toNotification()->send();
            })
            ->modalSubmitActionLabel('Simpan Foto')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    public static function recordHasPhoto(mixed $record): bool
    {
        if ($record instanceof UnifiedItem) {
            return (bool) $record->has_photo;
        }

        if ($record instanceof Asset) {
            return (bool) ($record->has_photo ?? $record->photos()->where('file_path', '<>', '')->exists());
        }

        if ($record instanceof InventoryBalance) {
            return filled($record->photo);
        }

        return false;
    }

    public static function rowAction(): Action
    {
        return Action::make('addPhoto')
            ->label('Tambah Foto')
            ->icon('heroicon-o-camera')
            ->color('success')
            ->visible(fn (mixed $record) => ! static::recordHasPhoto($record) && ItemPhotoService::userCanManage(Auth::user()))
            ->authorize(fn () => ItemPhotoService::userCanManage(Auth::user()))
            ->modalHeading('Tambah Foto Barang')
            ->modalWidth('lg')
            ->schema(fn (mixed $record): array => [
                Placeholder::make('item')
                    ->label('Barang')
                    ->content(static::itemIdentity($record)),
                static::photoUpload(
                    ($record instanceof InventoryBalance || ($record instanceof UnifiedItem && $record->row_type === 'supply'))
                        ? 1
                        : ItemPhotoService::MAX_ASSET_PHOTOS
                ),
            ])
            ->action(function (mixed $record, array $data) {
                $result = static::runApply(
                    ItemPhotoService::targetsFromRecords([$record]),
                    $data['photo'] ?? null,
                    ItemPhotoService::MODE_SKIP,
                );

                $result?->toNotification()->send();
            })
            ->modalSubmitActionLabel('Simpan Foto')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * Aksi baris: modal galeri foto dengan opsi "Buka di Tab Baru" dan "Unduh".
     * Hanya tampil jika barang sudah memiliki foto (has_photo = true).
     */
    public static function viewPhotoAction(): Action
    {
        return Action::make('viewPhoto')
            ->label('Lihat Foto')
            ->icon('heroicon-o-photo')
            ->color('info')
            ->visible(fn (mixed $record) => static::recordHasPhoto($record))
            ->modalHeading(fn (mixed $record) => 'Foto: '.($record instanceof UnifiedItem ? $record->item_name : ($record->name ?? 'Barang')))
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema(fn (mixed $record): array => [
                Placeholder::make('photos_gallery')
                    ->hiddenLabel()
                    ->content(static::buildPhotoGalleryHtml($record)),
            ]);
    }

    /**
     * Bangun HTML galeri foto untuk modal viewPhotoAction.
     * Asset → asset_photos table. Supply → inventory_balances.photo column.
     */
    protected static function buildPhotoGalleryHtml(mixed $record): HtmlString
    {
        $urls = [];

        if ($record instanceof Asset) {
            $photos = $record->photos()->orderBy('sort_order')->get(['file_path']);
            foreach ($photos as $photo) {
                if (filled($photo->file_path)) {
                    $urls[] = [
                        'thumb' => Storage::disk('s3_thumb')->url($photo->file_path),
                        'full' => Storage::disk('s3')->url($photo->file_path),
                    ];
                }
            }
        } elseif ($record instanceof InventoryBalance) {
            if (filled($record->photo)) {
                $urls[] = [
                    'thumb' => Storage::disk('s3_thumb')->url($record->photo),
                    'full' => Storage::disk('s3')->url($record->photo),
                ];
            }
        } elseif ($record instanceof UnifiedItem) {
            if ($record->row_type === 'asset') {
                $photos = Asset::find((int) $record->raw_id)?->photos()->orderBy('sort_order')->get(['file_path']) ?? collect();
                foreach ($photos as $photo) {
                    if (filled($photo->file_path)) {
                        $urls[] = [
                            'thumb' => Storage::disk('s3_thumb')->url($photo->file_path),
                            'full' => Storage::disk('s3')->url($photo->file_path),
                        ];
                    }
                }
            } else {
                $balance = InventoryBalance::find((int) $record->raw_id);
                if ($balance && filled($balance->photo)) {
                    $urls[] = [
                        'thumb' => Storage::disk('s3_thumb')->url($balance->photo),
                        'full' => Storage::disk('s3')->url($balance->photo),
                    ];
                }
            }
        }

        if (empty($urls)) {
            return new HtmlString('<p class="text-gray-500 text-sm">Foto tidak ditemukan.</p>');
        }

        $cards = '';
        foreach ($urls as $i => $url) {
            $num = $i + 1;
            $escaped_thumb = e($url['thumb']);
            $escaped_full = e($url['full']);
            $cards .= <<<HTML
            <div class="flex flex-col gap-2 items-center">
                <a href="{$escaped_full}" target="_blank" rel="noopener noreferrer" class="block w-full overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 hover:opacity-90 transition-opacity">
                    <img src="{$escaped_thumb}" alt="Foto {$num}" class="w-full h-48 object-contain bg-gray-50 dark:bg-gray-900" loading="lazy">
                </a>
                <div class="flex gap-2 w-full">
                    <a href="{$escaped_full}" target="_blank" rel="noopener noreferrer"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium bg-primary-600 text-white hover:bg-primary-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Buka
                    </a>
                    <a href="{$escaped_full}" download
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh
                    </a>
                </div>
            </div>
            HTML;
        }

        $cols = count($urls) === 1 ? 'grid-cols-1' : 'grid-cols-2';

        return new HtmlString(
            "<div class=\"grid {$cols} gap-4 py-2\">{$cards}</div>"
        );
    }

    protected static function photoUpload(int $maxFiles, bool $mentionSupplyLimit = false): FileUpload
    {
        $maxSizeKB = 10240; // 10MB
        $maxSizeMB = 10;

        $helper = $maxFiles > 1
            ? "Maks {$maxFiles} foto, urutan pertama jadi foto utama. Format: JPG, PNG, WebP. Maks {$maxSizeMB}MB per foto."
            : "Satu foto. Format: JPG, PNG, WebP. Maks {$maxSizeMB}MB.";

        if ($mentionSupplyLimit) {
            $helper .= ' Stok BHP hanya menyimpan foto pertama.';
        }

        // Validasi & penyimpanan sama dengan form AssetResource (disk s3_thumb, folder asset-photos, maks 3).
        return FileUpload::make('photo')
            ->label('Foto')
            ->validationAttribute('Foto')
            ->validationMessages([
                'uploaded' => 'File foto gagal diunggah. Kemungkinan penyebab: ukuran file melebihi batas upload server (PHP/Nginx), format file bukan JPG/PNG/WebP, atau koneksi internet terputus.',
                'max' => "Ukuran file foto terlalu besar. Maksimal {$maxSizeMB}MB per file.",
                'mimes' => 'Format file tidak didukung. Harap gunakan format gambar JPG, PNG, atau WebP.',
                'accepted_file_types' => 'Format file tidak didukung. Harap pilih gambar dengan format JPG, PNG, atau WebP.',
                'required' => 'Silakan pilih atau ambil foto terlebih dahulu.',
            ])
            ->multiple()
            ->maxFiles($maxFiles)
            ->reorderable()
            ->image()
            ->imageEditor()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2000')
            ->imageResizeTargetHeight('2000')
            ->imagePreviewHeight('200')
            ->maxSize($maxSizeKB)
            ->disk(ItemPhotoService::DISK)
            ->directory(ItemPhotoService::DIRECTORY)
            ->acceptedFileTypes(ItemPhotoService::ACCEPTED_MIME_TYPES)
            ->helperText($helper)
            // Tanpa atribut 'capture', browser mobile menawarkan kamera ATAU galeri.
            ->extraInputAttributes(['accept' => 'image/*'])
            ->required();
    }

    /**
     * Jalankan service dan ubah error menjadi notifikasi. Mengembalikan null
     * jika tidak ada data yang diubah karena error.
     *
     * @param  array{assets: array<int, int>, balances: array<int, int>}  $targets
     */
    protected static function runApply(array $targets, mixed $photo, string $mode): ?ItemPhotoResult
    {
        $service = app(ItemPhotoService::class);
        $paths = ItemPhotoService::normalizePaths($photo);

        if ($paths === []) {
            Notification::make()->title('Foto belum dipilih')->danger()->send();

            return null;
        }

        try {
            $result = $service->apply($targets, $paths, $mode, Auth::user());
        } catch (AuthorizationException $e) {
            $service->discardUnusedUpload($paths);
            Notification::make()->title('Akses ditolak')->body($e->getMessage())->danger()->send();

            return null;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $service->discardUnusedUpload($paths);
            Notification::make()->title('Foto gagal disimpan')->body($e->getMessage())->danger()->send();

            return null;
        } catch (Throwable $e) {
            report($e);
            $service->discardUnusedUpload($paths);
            Notification::make()->title('Foto gagal disimpan')->body('Terjadi kesalahan sistem. Data barang tidak diubah.')->danger()->send();

            return null;
        }

        // Buang file yang tidak terpakai (semua dilewati/gagal, atau foto
        // ke-2/3 yang hanya diterapkan ke Stok BHP). File terpakai tidak disentuh.
        $service->discardUnusedUpload($paths);

        return $result;
    }

    /**
     * Barang yang dicentang tapi sudah tidak ada saat aksi diproses
     * (mis. dihapus pengguna lain) tidak ikut terambil oleh Filament.
     */
    protected static function countMissingSelections(Collection $records, mixed $livewire): int
    {
        $selected = is_object($livewire) && property_exists($livewire, 'selectedTableRecords')
            ? count(array_unique($livewire->selectedTableRecords))
            : $records->count();

        return max(0, $selected - $records->count());
    }

    protected static function summaryHtml(Collection $items, int $withPhoto, int $missing): HtmlString
    {
        $rows = $items->map(fn (array $item) => sprintf(
            '<li class="flex justify-between gap-3 py-1"><span>%s</span><span class="shrink-0 text-xs %s">%s</span></li>',
            e($item['label']),
            $item['photo_count'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-500',
            $item['photo_count'] > 0 ? e("Sudah {$item['photo_count']} foto") : 'Belum ada foto',
        ))->implode('');

        $notes = '';
        if ($withPhoto > 0) {
            $notes .= '<p class="mt-2 text-warning-600 dark:text-warning-400">'.e("{$withPhoto} barang sudah memiliki foto dan secara default dilewati.").'</p>';
        }
        if ($missing > 0) {
            $notes .= '<p class="mt-2 text-danger-600 dark:text-danger-400">'.e("{$missing} barang terpilih sudah tidak tersedia dan tidak akan diproses.").'</p>';
        }

        return new HtmlString(
            '<p class="text-sm font-medium">'.e("{$items->count()} barang akan diperbarui:").'</p>'
            .'<ul class="mt-2 max-h-64 overflow-y-auto divide-y divide-gray-200 rounded-lg border border-gray-200 px-3 text-sm dark:divide-white/10 dark:border-white/10">'.$rows.'</ul>'
            .$notes
        );
    }

    protected static function itemIdentity(mixed $record): HtmlString
    {
        if ($record instanceof UnifiedItem) {
            return static::unifiedItemIdentity($record);
        }

        if ($record instanceof Asset) {
            $parts = array_filter([
                $record->inventory_number ?: $record->barcode,
                $record->name,
                $record->brand ? "({$record->brand})" : null,
            ]);

            $meta = array_filter([
                $record->classification?->name,
                $record->location?->name,
            ]);
        } elseif ($record instanceof InventoryBalance) {
            $parts = array_filter([
                $record->name,
                $record->brand ? "({$record->brand})" : null,
            ]);

            $meta = array_filter([
                $record->category?->name,
                $record->location?->name,
            ]);
        } else {
            $parts = [class_basename($record)];
            $meta = [];
        }

        return new HtmlString(
            '<span class="font-medium">'.e(implode(' ', $parts)).'</span>'
            .($meta ? '<br><span class="text-xs text-gray-500">'.e(implode(' · ', $meta)).'</span>' : '')
        );
    }

    protected static function unifiedItemIdentity(UnifiedItem $record): HtmlString
    {
        $parts = array_filter([
            $record->sku ?: $record->barcode,
            $record->item_name,
            $record->brand ? "({$record->brand})" : null,
        ]);

        $meta = array_filter([
            $record->classification_name,
            $record->location_name,
        ]);

        return new HtmlString(
            '<span class="font-medium">'.e(implode(' ', $parts)).'</span>'
            .($meta ? '<br><span class="text-xs text-gray-500">'.e(implode(' · ', $meta)).'</span>' : '')
        );
    }
}
