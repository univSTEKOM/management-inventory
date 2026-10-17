<?php

namespace App\Filament\Support;

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

    public static function rowAction(): Action
    {
        return Action::make('addPhoto')
            ->label('Tambah Foto')
            ->icon('heroicon-o-camera')
            ->color('success')
            ->visible(fn (UnifiedItem $record) => ! $record->has_photo && ItemPhotoService::userCanManage(Auth::user()))
            ->authorize(fn () => ItemPhotoService::userCanManage(Auth::user()))
            ->modalHeading('Tambah Foto Barang')
            ->modalWidth('lg')
            ->schema(fn (UnifiedItem $record): array => [
                Placeholder::make('item')
                    ->label('Barang')
                    ->content(static::unifiedItemIdentity($record)),
                static::photoUpload($record->row_type === 'supply' ? 1 : ItemPhotoService::MAX_ASSET_PHOTOS),
            ])
            ->action(function (UnifiedItem $record, array $data) {
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

    protected static function photoUpload(int $maxFiles, bool $mentionSupplyLimit = false): FileUpload
    {
        $helper = $maxFiles > 1
            ? "Maks {$maxFiles} foto, urutan pertama jadi foto utama. Format: JPG, PNG, WebP. Maks 5MB per foto."
            : 'Satu foto. Format: JPG, PNG, WebP. Maks 5MB.';

        if ($mentionSupplyLimit) {
            $helper .= ' Stok BHP hanya menyimpan foto pertama.';
        }

        // Validasi & penyimpanan sama dengan form AssetResource (disk s3_thumb, folder asset-photos, maks 3).
        return FileUpload::make('photo')
            ->label('Foto')
            ->multiple()
            ->maxFiles($maxFiles)
            ->reorderable()
            ->image()
            ->imageEditor()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2000')
            ->imageResizeTargetHeight('2000')
            ->imagePreviewHeight('200')
            ->maxSize(5120)
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
