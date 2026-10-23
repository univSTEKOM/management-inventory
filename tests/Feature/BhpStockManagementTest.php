<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\InventoryBalanceResource\Pages\ListInventoryBalances;
use App\Filament\Inventory\Resources\UnifiedItemResource\Pages\CreateUnifiedItem;
use App\Filament\Support\ItemBrandOptions;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BhpStockManagementTest extends TestCase
{
    use RefreshDatabase;

    private Classification $classification;

    private Category $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Superadmin')->firstOrFail());
        $this->actingAs($user);
        $this->classification = Classification::factory()->create(['slug' => 'barang-habis-pakai']);
        $this->category = Category::factory()->create();
        $this->category->classifications()->attach($this->classification);
        $this->location = Location::factory()->create(['campus_id' => Campus::factory()->create()->id]);
    }

    private function createSupply(int $quantity = 1): InventoryBalance
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->classification->id,
                'category_id' => $this->category->id,
                'name' => 'Kertas A4',
                'brand' => 'Paper A4 80gsm',
                'campus_id' => $this->location->campus_id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit' => 'Paket', 'quantity' => $quantity, 'ownership' => 'company',
                    'unit_price' => 50000,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return InventoryBalance::where('name', 'Kertas A4')->firstOrFail();
    }

    public function test_creation_preserves_identity_and_repeated_creation_increases_the_same_stock(): void
    {
        $balance = $this->createSupply();
        $this->assertSame('Paper A4 80gsm', $balance->brand);
        $this->assertEquals($this->category->id, $balance->category_id);
        $this->createSupply(2);

        $this->assertDatabaseCount('inventory_balances', 1);
        $this->assertEquals(3, $balance->fresh()->quantity);
        $this->assertSame(3, $balance->units()->where('status', 'available')->count());
        $this->assertSame(3, $balance->units()->distinct()->count('sub_barcode'));
        $this->assertSame(2, $balance->purchaseItems()->count());
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_brand_options_are_scoped_to_bhp_category_and_exclude_assets(): void
    {
        $this->createSupply();
        $other = Category::factory()->create();
        InventoryBalance::create(['category_id' => $other->id, 'name' => 'Tinta', 'brand' => 'Ink Brand', 'quantity' => 0]);
        $assetClass = Classification::factory()->create(['slug' => 'aset']);
        $this->category->classifications()->attach($assetClass);
        Asset::factory()->create([
            'category_id' => $this->category->id,
            'classification_id' => $assetClass->id,
            'brand' => 'Asset Brand',
        ]);

        $this->assertSame(['Paper A4 80gsm' => 'Paper A4 80gsm'], ItemBrandOptions::forCategory($this->category->id, $this->classification->id));
        $this->assertSame([], ItemBrandOptions::forCategory(null, $this->classification->id));

        Livewire::test(CreateUnifiedItem::class)
            ->fillForm(['classification_id' => $this->classification->id, 'category_id' => $this->category->id, 'brand' => 'Paper A4 80gsm'])
            ->set('data.category_id', $other->id)
            ->assertSet('data.brand', null);
    }

    public function test_stock_action_records_units_purchase_and_audit_without_duplicate_balance(): void
    {
        $balance = $this->createSupply();
        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('stock_in', $balance, data: [
                'quantity' => 2, 'unit' => 'Paket', 'unit_price' => 60000,
                'ownership' => 'grant', 'purchase_date' => '2026-10-19',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('inventory_balances', 1);
        $this->assertEquals(3, $balance->fresh()->quantity);
        $this->assertSame(3, $balance->units()->where('status', 'available')->count());
        $this->assertDatabaseHas('purchase_items', [
            'inventory_balance_id' => $balance->id, 'quantity' => 2,
            'total_price' => 120000, 'is_capitalized' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InventoryBalance::class, 'auditable_id' => $balance->id]);
    }

    public function test_stock_action_rejects_zero_and_fractional_quantities(): void
    {
        $balance = $this->createSupply();
        foreach ([0, -1, 1.5] as $quantity) {
            Livewire::test(ListInventoryBalances::class)
                ->callTableAction('stock_in', $balance, data: ['quantity' => $quantity, 'ownership' => 'company'])
                ->assertHasActionErrors(['quantity']);
        }
        $this->assertEquals(1, $balance->fresh()->quantity);
        $this->assertSame(1, $balance->purchaseItems()->count());
    }

    public function test_edit_persists_category_and_brand_without_changing_stock(): void
    {
        $balance = $this->createSupply();
        $category = Category::factory()->create();
        $category->classifications()->attach($this->classification);

        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('edit_info', $balance, data: [
                'name' => 'Kertas A4', 'category_id' => $category->id, 'brand' => 'Paper Premium',
                'campus_id' => $this->location->campus_id, 'location_id' => $this->location->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('inventory_balances', [
            'id' => $balance->id, 'category_id' => $category->id, 'brand' => 'Paper Premium', 'quantity' => 1,
        ]);
    }

    public function test_edit_rejects_a_category_not_linked_to_bhp(): void
    {
        $balance = $this->createSupply();
        $assetCategory = Category::factory()->create();

        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('edit_info', $balance, data: ['category_id' => $assetCategory->id])
            ->assertHasActionErrors(['category_id']);

        $this->assertEquals($this->category->id, $balance->fresh()->category_id);
    }

    public function test_stock_action_is_unavailable_without_supplies_stock_in_permission(): void
    {
        $balance = $this->createSupply();

        // User dengan role Approver tidak memiliki permission supplies.stock_in
        $approver = User::factory()->create();
        $approver->roles()->attach(Role::where('name', 'Approver')->firstOrFail());
        $this->actingAs($approver);

        Livewire::test(ListInventoryBalances::class)->assertTableActionHidden('stock_in', $balance);
        $this->assertEquals(1, $balance->fresh()->quantity);

        // User dengan role Finance memiliki permission supplies.stock_in
        $finance = User::factory()->create();
        $finance->roles()->attach(Role::where('name', 'Finance')->firstOrFail());
        $this->actingAs($finance);

        Livewire::test(ListInventoryBalances::class)->assertTableActionVisible('stock_in', $balance);

        // User dengan role Tim Inventaris juga memiliki permission supplies.stock_in
        $inventaris = User::factory()->create();
        $inventaris->roles()->attach(Role::where('name', 'Tim Inventaris')->firstOrFail());
        $this->actingAs($inventaris);

        Livewire::test(ListInventoryBalances::class)->assertTableActionVisible('stock_in', $balance);
    }

    public function test_empty_stock_can_be_replenished_without_reusing_old_barcodes(): void
    {
        $balance = $this->createSupply();
        $originalBarcode = $balance->units()->sole()->sub_barcode;
        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('stock_out', $balance, data: [
                'sub_barcodes' => [$originalBarcode], 'reason' => 'Digunakan',
            ])
            ->assertHasNoActionErrors();
        $this->assertEquals(0, $balance->fresh()->quantity);

        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('stock_in', $balance, data: ['quantity' => 2, 'ownership' => 'company'])
            ->assertHasNoActionErrors();

        $this->assertEquals(2, $balance->fresh()->quantity);
        $this->assertSame(2, $balance->units()->where('status', 'available')->count());
        $this->assertSame(3, $balance->units()->distinct()->count('sub_barcode'));
        $this->assertDatabaseHas('inventory_balance_units', ['sub_barcode' => $originalBarcode, 'status' => 'used']);
    }

    public function test_edit_into_an_existing_identity_preserves_combined_stock_and_history(): void
    {
        $balance = $this->createSupply(2);
        $category = Category::factory()->create();
        $category->classifications()->attach($this->classification);
        $target = InventoryBalance::create([
            'category_id' => $category->id, 'name' => $balance->name,
            'brand' => $balance->brand, 'location_id' => $balance->location_id,
            'quantity' => 0,
        ]);

        Livewire::test(ListInventoryBalances::class)
            ->callTableAction('edit_info', $balance, data: [
                'category_id' => $category->id, 'brand' => $balance->brand,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('inventory_balances', ['id' => $balance->id]);
        $this->assertEquals(2, $target->fresh()->quantity);
        $this->assertSame(2, $target->units()->count());
        $this->assertSame(1, $target->purchaseItems()->count());
    }

    public function test_asset_and_inventory_brand_options_remain_separate_in_a_shared_category(): void
    {
        $this->createSupply();
        foreach (['aset' => 'Laptop Brand', 'inventaris' => 'Mouse Brand'] as $slug => $brand) {
            $classification = Classification::factory()->create(['slug' => $slug]);
            $this->category->classifications()->attach($classification);
            Asset::factory()->create([
                'classification_id' => $classification->id, 'category_id' => $this->category->id,
                'brand' => $brand,
            ]);
            $this->assertSame([$brand => $brand], ItemBrandOptions::forCategory($this->category->id, $classification->id));
        }
        $this->assertSame(['Paper A4 80gsm' => 'Paper A4 80gsm'], ItemBrandOptions::forCategory($this->category->id, $this->classification->id));
    }
}
