<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\UnifiedItemResource\Pages\ListUnifiedItems;
use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnifiedItem;
use App\Models\User;
use App\Services\ItemPhotoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\FakesPhotoStorage;
use Tests\TestCase;

/**
 * Filter "Status Foto", CTA "Tambah Foto" per baris, dan "Tambah Foto Massal"
 * di halaman Semua Barang (UnifiedItemResource).
 */
class ItemPhotoFilterAndBulkUploadTest extends TestCase
{
    use FakesPhotoStorage;
    use RefreshDatabase;

    private User $admin;

    private Classification $asetClassification;

    private Classification $bhpClassification;

    private Category $category;

    private Category $bhpCategory;

    private Campus $campus;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePhotoDisks();

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);
        $this->actingAs($this->admin);

        $this->asetClassification = Classification::factory()->create(['name' => 'Aset', 'slug' => 'aset']);
        $this->bhpClassification = Classification::factory()->create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);

        $this->category = Category::factory()->create();
        $this->category->classifications()->attach($this->asetClassification->id);

        $this->bhpCategory = Category::factory()->create();
        $this->bhpCategory->classifications()->attach($this->bhpClassification->id);

        $this->campus = Campus::factory()->create();
        $this->location = Location::factory()->create(['campus_id' => $this->campus->id]);
    }

    private function makeAsset(array $overrides = [], bool $bhp = false): Asset
    {
        static $seq = 0;
        $seq++;

        return Asset::create(array_merge([
            'classification_id' => $bhp ? $this->bhpClassification->id : $this->asetClassification->id,
            'category_id' => $bhp ? $this->bhpCategory->id : $this->category->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'name' => "Barang {$seq}",
            'inventory_number' => sprintf('INV-T-%04d', $seq),
            'status' => 'stock',
            'ownership' => 'yayasan',
        ], $overrides));
    }

    private function makeBalance(array $overrides = []): InventoryBalance
    {
        static $seq = 0;
        $seq++;

        return InventoryBalance::create(array_merge([
            'category_id' => $this->bhpCategory->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'name' => "Stok BHP {$seq}",
            'quantity' => 10,
        ], $overrides));
    }

    private function addPhoto(Asset $asset, string $path, int $sortOrder = 0): AssetPhoto
    {
        $this->putTestImage($path);

        return $asset->photos()->create(['file_path' => $path, 'sort_order' => $sortOrder]);
    }

    private function grantAssetsUpdate(User $user): void
    {
        $permission = Permission::firstOrCreate(['name' => 'assets.update']);
        $role = Role::firstOrCreate(['name' => 'operator-foto']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    // ---------------------------------------------------------------------
    // Filter status foto
    // ---------------------------------------------------------------------

    public function test_photo_status_filter_covers_assets_bhp_assets_and_bhp_stock(): void
    {
        // Beberapa test lama tidak memakai RefreshDatabase dan meninggalkan
        // data di DB testing, jadi hitung relatif terhadap kondisi awal.
        $baseAll = UnifiedItem::query()->count();
        $baseWith = UnifiedItem::query()->where('has_photo', 1)->count();
        $baseWithout = $baseAll - $baseWith;

        $assetWith = $this->makeAsset();
        $this->addPhoto($assetWith, 'asset-photos/a.png');
        $assetWithout = $this->makeAsset();

        $bhpAssetWith = $this->makeAsset(bhp: true);
        $this->addPhoto($bhpAssetWith, 'asset-photos/b.png');
        $bhpAssetWithout = $this->makeAsset(bhp: true);

        $stockWith = $this->makeBalance(['photo' => $this->putTestImage('inventory-photos/s.png')]);
        $stockWithout = $this->makeBalance();

        $with = ["asset_{$assetWith->id}", "asset_{$bhpAssetWith->id}", "supply_{$stockWith->id}"];
        $without = ["asset_{$assetWithout->id}", "asset_{$bhpAssetWithout->id}", "supply_{$stockWithout->id}"];

        Livewire::test(ListUnifiedItems::class)
            ->assertCountTableRecords($baseAll + 6)
            ->filterTable('photo_status', 'without')
            ->assertCountTableRecords($baseWithout + 3)
            ->assertCanSeeTableRecords($without)
            ->assertCanNotSeeTableRecords($with)
            ->filterTable('photo_status', 'with')
            ->assertCountTableRecords($baseWith + 3)
            ->assertCanSeeTableRecords($with)
            ->assertCanNotSeeTableRecords($without)
            ->filterTable('photo_status', null)
            ->assertCountTableRecords($baseAll + 6);
    }

    public function test_photo_status_filter_combines_with_search_and_pagination(): void
    {
        $kursiWithout = collect(range(1, 12))->map(fn ($i) => $this->makeAsset(['name' => sprintf('Kursi Lipat %02d', $i)]));
        $kursiWith = $this->makeAsset(['name' => 'Kursi Lipat Berfoto']);
        $this->addPhoto($kursiWith, 'asset-photos/kursi.png');
        $this->makeAsset(['name' => 'Meja Rapat']);

        $component = Livewire::test(ListUnifiedItems::class)
            ->searchTable('Kursi')
            ->filterTable('photo_status', 'without')
            ->set('tableRecordsPerPage', 10)
            ->assertCountTableRecords(12)
            ->assertCanNotSeeTableRecords(["asset_{$kursiWith->id}"]);

        $this->assertCount(10, $component->instance()->getTableRecords());

        $component->call('gotoPage', 2);
        $page2 = $component->instance()->getTableRecords();
        $this->assertCount(2, $page2);

        $page2->each(fn ($record) => $this->assertContains((int) $record->raw_id, $kursiWithout->pluck('id')->all()));
    }

    // ---------------------------------------------------------------------
    // Upload individual
    // ---------------------------------------------------------------------

    public function test_row_add_photo_uploads_and_item_leaves_without_photo_filter(): void
    {
        $asset = $this->makeAsset();
        $stock = $this->makeBalance();

        $component = Livewire::test(ListUnifiedItems::class)
            ->filterTable('photo_status', 'without')
            ->assertCanSeeTableRecords(["asset_{$asset->id}", "supply_{$stock->id}"])
            ->callTableAction('addPhoto', "asset_{$asset->id}", data: [
                'photo' => [UploadedFile::fake()->createWithContent('foto.png', $this->pngBytes())],
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Foto berhasil disimpan ke 1 barang.')
            ->callTableAction('addPhoto', "supply_{$stock->id}", data: [
                'photo' => [$this->putTestImage('asset-photos/stok.png')],
            ])
            ->assertNotified('Foto berhasil disimpan ke 1 barang.');

        $photo = $asset->photos()->sole();
        $this->assertStringStartsWith('asset-photos/', $photo->file_path);
        Storage::disk('s3')->assertExists($photo->file_path);
        $this->assertSame('asset-photos/stok.png', $stock->fresh()->photo);

        // Daftar ter-refresh tanpa reload: kedua barang keluar dari filter Belum Ada Foto.
        $component->assertCanNotSeeTableRecords(["asset_{$asset->id}", "supply_{$stock->id}"]);
    }

    public function test_row_add_photo_is_hidden_for_items_that_already_have_photo(): void
    {
        $asset = $this->makeAsset();
        $this->addPhoto($asset, 'asset-photos/ada.png');

        Livewire::test(ListUnifiedItems::class)
            ->assertTableActionHidden('addPhoto', "asset_{$asset->id}");
    }

    public function test_row_add_photo_rejects_non_image_file(): void
    {
        $asset = $this->makeAsset();

        Livewire::test(ListUnifiedItems::class)
            ->callTableAction('addPhoto', "asset_{$asset->id}", data: [
                'photo' => [UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')],
            ])
            ->assertHasTableActionErrors(['photo']);

        $this->assertSame(0, $asset->photos()->count());
    }

    public function test_row_add_photo_failure_keeps_previous_state(): void
    {
        $asset = $this->makeAsset();

        // File tidak ada di storage (mis. gagal tersimpan) -> tidak ada data diubah.
        Livewire::test(ListUnifiedItems::class)
            ->callTableAction('addPhoto', "asset_{$asset->id}", data: [
                'photo' => ['asset-photos/tidak-ada.png'],
            ])
            ->assertNotified('Foto gagal disimpan');

        $this->assertSame(0, $asset->photos()->count());
    }

    // ---------------------------------------------------------------------
    // Bulk upload
    // ---------------------------------------------------------------------

    public function test_bulk_upload_applies_one_file_only_to_selected_items(): void
    {
        $a1 = $this->makeAsset();
        $a2 = $this->makeAsset(bhp: true);
        $notSelected = $this->makeAsset();
        $stock = $this->makeBalance();

        $component = Livewire::test(ListUnifiedItems::class)
            ->filterTable('photo_status', 'without')
            ->callTableBulkAction('addPhotoBulk', ["asset_{$a1->id}", "asset_{$a2->id}", "supply_{$stock->id}"], data: [
                'photo' => [UploadedFile::fake()->createWithContent('massal.png', $this->pngBytes())],
                'confirmed' => true,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified('Foto berhasil disimpan ke 3 barang.');

        $path = $a1->photos()->sole()->file_path;
        $this->assertSame($path, $a2->photos()->sole()->file_path);
        $this->assertSame($path, $stock->fresh()->photo);
        $this->assertSame(0, $notSelected->photos()->count());

        // Satu file fisik untuk semua barang (tidak diduplikasi).
        $this->assertCount(1, Storage::disk('s3')->files('asset-photos'));

        $component
            ->assertCanNotSeeTableRecords(["asset_{$a1->id}", "asset_{$a2->id}", "supply_{$stock->id}"])
            ->assertCanSeeTableRecords(["asset_{$notSelected->id}"]);
    }

    public function test_bulk_upload_applies_up_to_three_photos_in_order(): void
    {
        $a1 = $this->makeAsset();
        $a2 = $this->makeAsset(bhp: true);
        $stock = $this->makeBalance();
        $paths = [
            $this->putTestImage('asset-photos/depan.png'),
            $this->putTestImage('asset-photos/samping.png'),
            $this->putTestImage('asset-photos/belakang.png'),
        ];

        Livewire::test(ListUnifiedItems::class)
            ->callTableBulkAction('addPhotoBulk', ["asset_{$a1->id}", "asset_{$a2->id}", "supply_{$stock->id}"], data: [
                'photo' => $paths,
                'confirmed' => true,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified('Foto berhasil disimpan ke 3 barang.');

        foreach ([$a1, $a2] as $asset) {
            $this->assertSame($paths, $asset->photos()->orderBy('sort_order')->pluck('file_path')->all());
            $this->assertSame([0, 1, 2], $asset->photos()->orderBy('sort_order')->pluck('sort_order')->all());
        }
        // Stok BHP hanya punya satu slot foto: foto pertama.
        $this->assertSame($paths[0], $stock->fresh()->photo);
        // Tiga file fisik, dipakai bersama, tidak diduplikasi.
        $this->assertCount(3, Storage::disk('s3')->files('asset-photos'));
    }

    public function test_extra_photos_applied_only_to_bhp_stock_are_discarded(): void
    {
        $stock = $this->makeBalance();
        $paths = [
            $this->putTestImage('asset-photos/s1.png'),
            $this->putTestImage('asset-photos/s2.png'),
        ];

        $result = app(ItemPhotoService::class)->apply(['balances' => [$stock->id]], $paths, ItemPhotoService::MODE_SKIP, $this->admin);
        app(ItemPhotoService::class)->discardUnusedUpload($paths);

        $this->assertSame(['Stok BHP hanya menyimpan 1 foto; yang dipakai foto pertama.'], $result->notes);
        $this->assertSame('asset-photos/s1.png', $stock->fresh()->photo);
        Storage::disk('s3')->assertExists('asset-photos/s1.png');
        Storage::disk('s3')->assertMissing('asset-photos/s2.png');
    }

    public function test_add_mode_respects_three_photo_limit(): void
    {
        $fits = $this->makeAsset(['name' => 'Muat']);
        $this->addPhoto($fits, 'asset-photos/fits-0.png');
        $tooMany = $this->makeAsset(['name' => 'Tidak Muat']);
        $this->addPhoto($tooMany, 'asset-photos/many-0.png');
        $this->addPhoto($tooMany, 'asset-photos/many-1.png', 1);

        $result = app(ItemPhotoService::class)->apply(
            ['assets' => [$fits->id, $tooMany->id]],
            [$this->putTestImage('asset-photos/n1.png'), $this->putTestImage('asset-photos/n2.png')],
            ItemPhotoService::MODE_ADD,
            $this->admin,
        );

        $this->assertSame(1, $result->successCount());
        $this->assertCount(1, $result->failed);
        $this->assertStringContainsString('hanya muat 1 lagi', $result->failed[0]['reason']);
        $this->assertSame(
            ['asset-photos/fits-0.png', 'asset-photos/n1.png', 'asset-photos/n2.png'],
            $fits->photos()->orderBy('sort_order')->pluck('file_path')->all(),
        );
        $this->assertSame(2, $tooMany->photos()->count());
    }

    public function test_upload_rejects_more_than_three_photos(): void
    {
        $asset = $this->makeAsset();
        $stock = $this->makeBalance();
        $paths = array_map(fn ($i) => $this->putTestImage("asset-photos/p{$i}.png"), range(1, 4));

        try {
            app(ItemPhotoService::class)->apply(['assets' => [$asset->id]], $paths, ItemPhotoService::MODE_SKIP, $this->admin);
            $this->fail('Empat foto seharusnya ditolak.');
        } catch (\InvalidArgumentException) {
            // expected
        }
        $this->assertSame(0, $asset->photos()->count());

        Livewire::test(ListUnifiedItems::class)
            ->callTableBulkAction('addPhotoBulk', ["asset_{$asset->id}"], data: [
                'photo' => $paths,
                'confirmed' => true,
            ])
            ->assertHasTableBulkActionErrors(['photo']);

        // Baris Stok BHP: hanya 1 foto.
        Livewire::test(ListUnifiedItems::class)
            ->callTableAction('addPhoto', "supply_{$stock->id}", data: [
                'photo' => array_slice($paths, 0, 2),
            ])
            ->assertHasTableActionErrors(['photo']);

        $this->assertSame(0, $asset->photos()->count());
        $this->assertNull($stock->fresh()->photo);
    }

    public function test_bulk_upload_requires_explicit_confirmation(): void
    {
        $asset = $this->makeAsset();

        Livewire::test(ListUnifiedItems::class)
            ->callTableBulkAction('addPhotoBulk', ["asset_{$asset->id}"], data: [
                'photo' => [$this->putTestImage('asset-photos/x.png')],
                'confirmed' => false,
            ])
            ->assertHasTableBulkActionErrors(['confirmed']);

        $this->assertSame(0, $asset->photos()->count());
    }

    public function test_same_brand_action_filters_list_for_selection(): void
    {
        $hp1 = $this->makeAsset(['brand' => 'HP LaserJet M404']);
        $hp2 = $this->makeAsset(['brand' => 'HP LaserJet M404']);
        $other = $this->makeAsset(['brand' => 'Epson L3210']);

        $component = Livewire::test(ListUnifiedItems::class)
            ->callTableAction('filterSameBrand', "asset_{$hp1->id}")
            ->assertSet('tableFilters.brand.value', 'HP LaserJet M404')
            ->assertCanSeeTableRecords(["asset_{$hp1->id}", "asset_{$hp2->id}"])
            ->assertCanNotSeeTableRecords(["asset_{$other->id}"]);

        // Tidak ada foto yang diterapkan otomatis; pengguna tetap memilih & mengonfirmasi.
        $this->assertDatabaseCount('asset_photos', 0);

        $component
            ->callTableBulkAction('addPhotoBulk', ["asset_{$hp1->id}", "asset_{$hp2->id}"], data: [
                'photo' => [$this->putTestImage('asset-photos/hp.png')],
                'confirmed' => true,
            ])
            ->assertNotified('Foto berhasil disimpan ke 2 barang.');

        $this->assertSame(0, $other->photos()->count());
    }

    public function test_bulk_upload_does_not_touch_items_with_photo_by_default(): void
    {
        $withPhoto = $this->makeAsset();
        $this->addPhoto($withPhoto, 'asset-photos/lama.png');
        $stockWithPhoto = $this->makeBalance(['photo' => $this->putTestImage('inventory-photos/lama.png')]);
        $without = $this->makeAsset();

        Livewire::test(ListUnifiedItems::class)
            ->callTableBulkAction('addPhotoBulk', ["asset_{$withPhoto->id}", "supply_{$stockWithPhoto->id}", "asset_{$without->id}"], data: [
                'photo' => [$this->putTestImage('asset-photos/baru.png')],
                'confirmed' => true,
            ])
            ->assertNotified('Foto disimpan ke 1 barang, 2 dilewati, 0 gagal.');

        $this->assertSame(['asset-photos/lama.png'], $withPhoto->photos()->pluck('file_path')->all());
        $this->assertSame('inventory-photos/lama.png', $stockWithPhoto->fresh()->photo);
        Storage::disk('s3')->assertExists(['asset-photos/lama.png', 'inventory-photos/lama.png']);
        $this->assertSame(['asset-photos/baru.png'], $without->photos()->pluck('file_path')->all());
    }

    public function test_bulk_upload_replaces_existing_photos_only_when_chosen(): void
    {
        $asset = $this->makeAsset();
        $this->addPhoto($asset, 'asset-photos/lama.png');
        $stock = $this->makeBalance(['photo' => $this->putTestImage('inventory-photos/lama.png')]);

        Livewire::test(ListUnifiedItems::class)
            ->callTableBulkAction('addPhotoBulk', ["asset_{$asset->id}", "supply_{$stock->id}"], data: [
                'photo' => [$this->putTestImage('asset-photos/baru.png')],
                'existing_mode' => ItemPhotoService::MODE_REPLACE,
                'confirmed' => true,
            ])
            ->assertNotified('Foto berhasil disimpan ke 2 barang.');

        $this->assertSame(['asset-photos/baru.png'], $asset->photos()->pluck('file_path')->all());
        $this->assertSame('asset-photos/baru.png', $stock->fresh()->photo);
        // File lama tidak dipakai barang lain -> dibersihkan.
        Storage::disk('s3')->assertMissing(['asset-photos/lama.png', 'inventory-photos/lama.png']);
    }

    public function test_bulk_upload_reports_partial_failure_accurately(): void
    {
        $ok = $this->makeAsset();
        $full = $this->makeAsset(['name' => 'Proyektor Penuh']);
        foreach (range(0, 2) as $i) {
            $this->addPhoto($full, "asset-photos/full-{$i}.png", $i);
        }
        $deletedLater = $this->makeAsset(['name' => 'Dihapus User Lain']);

        $component = Livewire::test(ListUnifiedItems::class)
            ->selectTableRecords(["asset_{$ok->id}", "asset_{$full->id}", "asset_{$deletedLater->id}"]);

        // Pengguna lain menghapus barang setelah dicentang.
        $deletedLater->delete();

        $component
            ->callTableBulkAction('addPhotoBulk', ["asset_{$ok->id}", "asset_{$full->id}", "asset_{$deletedLater->id}"], data: [
                'photo' => [$this->putTestImage('asset-photos/baru.png')],
                'existing_mode' => ItemPhotoService::MODE_ADD,
                'confirmed' => true,
            ])
            ->assertNotified('Foto disimpan ke 1 barang, 0 dilewati, 2 gagal.');

        $this->assertSame(1, $ok->photos()->count());
        $this->assertSame(3, $full->photos()->count());
        $this->assertSame(0, $deletedLater->photos()->count());
    }

    public function test_service_reports_items_deleted_between_selection_and_processing(): void
    {
        $ok = $this->makeAsset();
        $gone = $this->makeAsset();
        $gone->delete();

        $result = app(ItemPhotoService::class)->apply(
            ['assets' => [$ok->id, $gone->id], 'balances' => [999999]],
            $this->putTestImage('asset-photos/svc.png'),
            ItemPhotoService::MODE_SKIP,
            $this->admin,
        );

        $this->assertSame(1, $result->successCount());
        $this->assertCount(2, $result->failed);
        $this->assertFalse($result->isFullySuccessful());
    }

    // ---------------------------------------------------------------------
    // Otorisasi & validasi file
    // ---------------------------------------------------------------------

    public function test_user_without_permission_cannot_add_photos(): void
    {
        $asset = $this->makeAsset();
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        Livewire::test(ListUnifiedItems::class)
            ->assertTableBulkActionHidden('addPhotoBulk')
            ->assertTableActionHidden('addPhoto', "asset_{$asset->id}");

        $this->expectException(AuthorizationException::class);

        try {
            app(ItemPhotoService::class)->apply(
                ['assets' => [$asset->id]],
                $this->putTestImage('asset-photos/no.png'),
                ItemPhotoService::MODE_SKIP,
                $viewer,
            );
        } finally {
            $this->assertSame(0, $asset->photos()->count());
        }
    }

    public function test_user_with_assets_update_permission_can_add_photos(): void
    {
        $asset = $this->makeAsset();
        $operator = User::factory()->create();
        $this->grantAssetsUpdate($operator);
        $this->actingAs($operator);

        Livewire::test(ListUnifiedItems::class)
            ->assertTableBulkActionVisible('addPhotoBulk')
            ->callTableAction('addPhoto', "asset_{$asset->id}", data: [
                'photo' => [$this->putTestImage('asset-photos/op.png')],
            ])
            ->assertNotified('Foto berhasil disimpan ke 1 barang.');

        $this->assertSame(1, $asset->photos()->count());
    }

    public function test_service_rejects_invalid_or_non_image_files(): void
    {
        $asset = $this->makeAsset();
        $service = app(ItemPhotoService::class);

        Storage::disk('s3')->put('asset-photos/bukan-gambar.png', 'teks biasa');
        Storage::disk('s3')->put('inventory-photos/luar-folder.png', $this->pngBytes());

        foreach (['asset-photos/bukan-gambar.png', 'inventory-photos/luar-folder.png', 'asset-photos/../.env'] as $path) {
            try {
                $service->apply(['assets' => [$asset->id]], $path, ItemPhotoService::MODE_SKIP, $this->admin);
                $this->fail("Path {$path} seharusnya ditolak.");
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        $this->assertSame(0, $asset->photos()->count());
    }

    // ---------------------------------------------------------------------
    // File bersama & cache
    // ---------------------------------------------------------------------

    public function test_shared_file_survives_until_last_reference_is_removed(): void
    {
        $a1 = $this->makeAsset();
        $a2 = $this->makeAsset();
        $stock = $this->makeBalance();
        $shared = $this->putTestImage('asset-photos/bersama.png');

        app(ItemPhotoService::class)->apply(
            ['assets' => [$a1->id, $a2->id], 'balances' => [$stock->id]],
            $shared,
            ItemPhotoService::MODE_SKIP,
            $this->admin,
        );

        // Hapus foto dari satu aset -> file tetap ada untuk barang lain.
        $a1->photos()->sole()->delete();
        Storage::disk('s3')->assertExists($shared);
        $this->assertNotNull($a2->photos()->sole()->url);

        // Force delete aset kedua -> file masih dipakai stok BHP.
        $a2->forceDelete();
        Storage::disk('s3')->assertExists($shared);

        // Ganti foto stok BHP -> referensi terakhir hilang -> file dihapus.
        app(ItemPhotoService::class)->apply(
            ['balances' => [$stock->id]],
            $this->putTestImage('asset-photos/pengganti.png'),
            ItemPhotoService::MODE_REPLACE,
            $this->admin,
        );
        Storage::disk('s3')->assertMissing($shared);
        Storage::disk('s3')->assertExists('asset-photos/pengganti.png');
    }

    public function test_soft_deleted_asset_keeps_shared_file_referenced(): void
    {
        $a1 = $this->makeAsset();
        $a2 = $this->makeAsset();
        $shared = $this->putTestImage('asset-photos/soft.png');
        app(ItemPhotoService::class)->apply(['assets' => [$a1->id, $a2->id]], $shared, ItemPhotoService::MODE_SKIP, $this->admin);

        $a1->delete(); // soft delete, bisa di-restore
        $a2->photos()->sole()->delete();

        Storage::disk('s3')->assertExists($shared);
    }

    public function test_photo_changes_invalidate_unified_item_cache(): void
    {
        Cache::forget('unified_items_version');
        $asset = $this->makeAsset(); // saved -> versi naik

        $before = Cache::get('unified_items_version');
        $this->assertGreaterThan(1, $before, 'Perubahan pertama harus menaikkan versi dari default 1.');

        $this->addPhoto($asset, 'asset-photos/cache.png');
        $afterCreate = Cache::get('unified_items_version');
        $this->assertGreaterThan($before, $afterCreate);

        $asset->photos()->sole()->delete();
        $this->assertGreaterThan($afterCreate, Cache::get('unified_items_version'));
    }
}
