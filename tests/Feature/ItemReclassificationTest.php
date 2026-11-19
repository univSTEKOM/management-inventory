<?php

namespace Tests\Feature;

use App\Filament\Inventory\Pages\ReclassifyItems;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\ItemReclassificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemReclassificationTest extends TestCase
{
    use RefreshDatabase;

    private ItemReclassificationService $service;

    private User $operator;

    private Category $category;

    private Location $room;

    private array $classes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ItemReclassificationService::class);
        $this->operator = User::factory()->create();
        $this->operator->permissions()->attach(Permission::whereName(ItemReclassificationService::PERMISSION)->sole(), ['type' => 'allow']);
        $this->actingAs($this->operator);
        $this->category = Category::factory()->create();
        $this->room = Location::factory()->create();

        foreach (ItemReclassificationService::slugs() as $slug) {
            $this->classes[$slug] = Classification::factory()->create(['slug' => $slug]);
            $this->category->classifications()->attach($this->classes[$slug]);
        }
    }

    public function test_individual_conversion_retains_identity_and_can_reverse_with_unknown_price(): void
    {
        $asset = $this->asset('inventaris', null, $this->room);
        $before = $asset->only(['inventory_number', 'barcode', 'location_id', 'kondisi', 'purchase_item_id']);

        $log = $this->convert('inventaris', 'aset', [$asset->id]);
        $this->assertSame($asset->id, $log->lines()->sole()->destination_id);
        $this->assertSame($before, $asset->fresh()->only(array_keys($before)));
        $this->assertEquals($this->classes['aset']->id, $asset->fresh()->classification_id);

        $this->convert('aset', 'inventaris', [$asset->id]);
        $this->assertEquals($this->classes['inventaris']->id, $asset->fresh()->classification_id);
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_consolidation_previews_locations_and_preserves_prices_batches_and_units(): void
    {
        $otherRoom = Location::factory()->create();
        $first = $this->asset('inventaris', 25000, $this->room);
        $second = $this->asset('inventaris', 45000, $otherRoom);
        $third = $this->asset('inventaris', 12500, $this->room, 'Box');
        $selection = [$first->id, $second->id, $third->id];
        $options = ['destination_location_id' => $this->room->id];
        $preview = $this->service->preview('inventaris', 'barang-habis-pakai', $selection, $options);
        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['groups']);
        $this->assertCount(2, $preview['groups'][0]['source_locations']);
        $this->assertEquals(70000, $preview['groups'][0]['total_value']);
        $this->assertEquals($preview['destination_location'], $preview['groups'][0]['destination_location']);

        $token = (string) Str::uuid();
        $log = $this->service->execute($this->operator, 'inventaris', 'barang-habis-pakai', $selection, $options,
            $token, ItemReclassificationService::fingerprint($preview));
        $retry = $this->service->execute($this->operator, 'inventaris', 'barang-habis-pakai', $selection, $options, $token);
        $this->assertSame($log->id, $retry->id);
        $this->assertDatabaseCount('item_reclassification_lines', 3);
        $this->assertDatabaseCount('inventory_balances', 2);
        $balance = InventoryBalance::where('unit', 'Pcs')->sole();
        $this->assertEquals(2, $balance->quantity);
        $this->assertEquals([25000, 45000], $balance->purchaseItems()->orderBy('id')->pluck('unit_price')->all());
        $this->assertEquals([$first->purchaseItem->purchase_id, $second->purchaseItem->purchase_id],
            $balance->purchaseItems()->orderBy('id')->pluck('purchase_id')->all());
        $this->assertSame(2, $balance->units()->where('status', 'available')->count());
        $this->assertSoftDeleted($first);
        $this->assertFalse(Asset::withTrashed()->findOrFail($first->id)->restore());

        $out = $this->convert('barang-habis-pakai', 'inventaris', [$balance->id => 1]);
        $this->assertEquals(1, $balance->fresh()->quantity);
        $this->assertEquals(25000, Asset::findOrFail($out->lines()->sole()->destination_id)->purchaseItem->unit_price);
    }

    public function test_consolidation_can_keep_each_source_location(): void
    {
        $otherRoom = Location::factory()->create();
        $first = $this->asset('inventaris', 25000, $this->room);
        $second = $this->asset('inventaris', 45000, $otherRoom);

        $log = $this->convert('inventaris', 'barang-habis-pakai', [$first->id, $second->id]);

        $this->assertEqualsCanonicalizing([$this->room->id, $otherRoom->id],
            InventoryBalance::pluck('location_id')->all());
        $this->assertEqualsCanonicalizing([$this->room->id, $otherRoom->id],
            $log->lines()->pluck('destination_location_id')->all());
    }

    public function test_bhp_allocation_checks_every_fifo_batch_and_rolls_back_on_rejection(): void
    {
        $small = $this->asset('aset', 900000, $this->room);
        $large = $this->asset('aset', 1000000, $this->room);
        $large->purchaseItem->purchase->update(['purchase_date' => '2024-01-01']);
        $small->purchaseItem->purchase->update(['purchase_date' => '2024-02-01']);
        $this->convert('aset', 'barang-habis-pakai', [$small->id, $large->id], ['destination_location_id' => $this->room->id]);
        $balance = InventoryBalance::sole();

        try {
            $this->convert('barang-habis-pakai', 'aset', [$balance->id => 2]);
            $this->fail('Batch di bawah ambang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('batch pembelian', $exception->getMessage());
        }

        $this->assertDatabaseCount('item_reclassifications', 1);
        $this->assertEquals(2, $balance->fresh()->quantity);
        $this->assertEquals(2, $balance->units()->where('status', 'available')->count());

        $log = $this->convert('barang-habis-pakai', 'aset', [$balance->id => 1]);
        $asset = Asset::findOrFail($log->lines()->sole()->destination_id);
        $this->assertEquals(1000000, $asset->purchaseItem->unit_price);
        $this->assertTrue($asset->purchaseItem->is_capitalized);
        $this->assertEquals(1, $balance->fresh()->quantity);
    }

    public function test_unlinked_category_and_stale_preview_are_rejected(): void
    {
        $asset = $this->asset('inventaris', null, $this->room);
        $preview = $this->service->preview('inventaris', 'aset', [$asset->id]);
        $asset->update(['name' => 'Nama telah diubah']);

        try {
            $this->service->execute($this->operator, 'inventaris', 'aset', [$asset->id], [], (string) Str::uuid(),
                ItemReclassificationService::fingerprint($preview));
            $this->fail('Pratinjau kedaluwarsa seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Tampilkan ulang pratinjau', $exception->getMessage());
        }

        $this->assertDatabaseCount('item_reclassifications', 0);
        $this->category->classifications()->detach($this->classes['aset']);
        $this->expectException(ValidationException::class);
        $this->convert('inventaris', 'aset', [$asset->id]);
    }

    public function test_execute_and_page_require_permission(): void
    {
        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized);
        $this->assertFalse(ReclassifyItems::canAccess());

        $this->expectException(AuthorizationException::class);
        $this->service->execute($unauthorized, 'aset', 'inventaris', [], [], (string) Str::uuid());
    }

    public function test_page_previews_and_confirms_selected_items(): void
    {
        $this->withoutVite();
        $asset = $this->asset('inventaris', null, $this->room);

        Livewire::test(ReclassifyItems::class)
            ->set('source', 'inventaris')
            ->set('destination', 'aset')
            ->set('selected', [(string) $asset->id])
            ->call('showPreview')
            ->assertHasNoErrors()
            ->assertSet('preview.total_quantity', 1)
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertEquals($this->classes['aset']->id, $asset->fresh()->classification_id);
    }

    public function test_overselling_and_untracked_stock_are_rejected(): void
    {
        $asset = $this->asset('inventaris', 25000, $this->room);
        $this->convert('inventaris', 'barang-habis-pakai', [$asset->id], ['destination_location_id' => $this->room->id]);
        $balance = InventoryBalance::sole();
        $preview = $this->service->preview('barang-habis-pakai', 'inventaris', [$balance->id => 2]);
        $this->assertStringContainsString('melebihi stok', $preview['errors'][0]);

        $balance->units()->update(['status' => 'used']);
        $this->expectException(ValidationException::class);
        $this->convert('barang-habis-pakai', 'inventaris', [$balance->id => 1]);
    }

    public function test_failure_during_conversion_rolls_back_stock_assets_batches_and_audit(): void
    {
        $first = $this->asset('inventaris', 25000, $this->room);
        $second = $this->asset('inventaris', 45000, $this->room);
        $dispatcher = Asset::getEventDispatcher();
        Asset::setEventDispatcher(clone $dispatcher);
        Asset::deleting(function (Asset $asset) use ($second) {
            if ($asset->is($second)) {
                throw new \RuntimeException('Simulated write failure');
            }
        });

        try {
            $this->convert('inventaris', 'barang-habis-pakai', [$first->id, $second->id], [
                'destination_location_id' => $this->room->id,
            ]);
            $this->fail('Simulated write failure was expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        } finally {
            Asset::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('item_reclassifications', 0);
        $this->assertDatabaseCount('item_reclassification_lines', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('inventory_balance_units', 0);
        $this->assertDatabaseCount('purchase_items', 2);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'reclassified']);
        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    }

    public function test_legacy_unit_backfill_keeps_original_batches_and_uses_latest_known_unit(): void
    {
        $first = $this->asset('inventaris', 25000, $this->room);
        $second = $this->asset('inventaris', 45000, $this->room);
        $this->convert('inventaris', 'barang-habis-pakai', [$first->id, $second->id], [
            'destination_location_id' => $this->room->id,
        ]);
        $balance = InventoryBalance::sole();
        $balance->update(['unit' => null]);
        $balance->purchaseItems()->latest('id')->firstOrFail()->update(['unit' => 'Box']);

        $migration = require database_path('migrations/2026_11_19_000001_add_unit_to_inventory_balances_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('inventory_balances', 'inv_bal_unique_with_unit'));
        $this->assertSame('Box', $balance->fresh()->unit);
        $this->assertSame(['Pcs', 'Box'], $balance->purchaseItems()->orderBy('id')->pluck('unit')->all());
    }

    private function convert(string $source, string $destination, array $selection, array $options = []): \App\Models\ItemReclassification
    {
        return $this->service->execute($this->operator, $source, $destination, $selection, $options, (string) Str::uuid());
    }

    private function asset(string $slug, ?int $price, Location $room, string $unit = 'Pcs'): Asset
    {
        $purchase = Purchase::create(['purchase_date' => '2024-01-01', 'ownership' => 'company', 'total_amount' => $price]);
        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'classification_id' => $this->classes[$slug]->id,
            'category_id' => $this->category->id,
            'name' => 'Barang uji',
            'unit' => $unit,
            'unit_price' => $price,
            'total_price' => $price,
        ]);

        return Asset::factory()->create([
            'name' => 'Barang uji',
            'brand' => 'Merek A',
            'category_id' => $this->category->id,
            'classification_id' => $this->classes[$slug]->id,
            'purchase_item_id' => $batch->id,
            'campus_id' => $room->campus_id,
            'location_id' => $room->id,
            'unit' => $unit,
            'ownership' => 'company',
            'kondisi' => 'good',
        ]);
    }
}
