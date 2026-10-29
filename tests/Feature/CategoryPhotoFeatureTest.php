<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\AssetCategoryResource\Pages\ListAssetCategory;
use App\Filament\Inventory\Resources\InventoryCategoryResource\Pages\ListInventoryCategory;
use App\Filament\Inventory\Resources\InventoryBalanceResource\Pages\ListInventoryBalances;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryPhotoFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Classification $asetClass;
    protected Classification $invClass;
    protected Classification $bhpClass;
    protected Campus $campus;
    protected Location $location;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('s3_thumb');

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);
        $this->actingAs($this->admin);

        $this->asetClass = Classification::firstOrCreate(['slug' => 'aset'], ['name' => 'Aset']);
        $this->invClass = Classification::firstOrCreate(['slug' => 'inventaris'], ['name' => 'Inventaris']);
        $this->bhpClass = Classification::firstOrCreate(['slug' => 'barang-habis-pakai'], ['name' => 'Barang Habis Pakai']);

        $this->campus = Campus::factory()->create();
        $this->location = Location::factory()->create(['campus_id' => $this->campus->id]);
        $this->category = Category::factory()->create();
        $this->category->classifications()->attach([$this->asetClass->id, $this->invClass->id, $this->bhpClass->id]);
    }

    public function test_asset_category_table_has_photo_column_filter_and_actions(): void
    {
        $asset = Asset::create([
            'classification_id' => $this->asetClass->id,
            'category_id' => $this->category->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'name' => 'Laptop Aset Uji',
            'inventory_number' => 'AST-001',
            'status' => 'active',
            'ownership' => 'yayasan',
        ]);

        Livewire::test(ListAssetCategory::class)
            ->assertTableColumnExists('has_photo')
            ->assertTableFilterExists('photo_status')
            ->assertTableActionExists('addPhoto')
            ->assertTableActionExists('viewPhoto');
    }

    public function test_inventory_category_table_has_photo_column_filter_and_actions(): void
    {
        $inventory = Asset::create([
            'classification_id' => $this->invClass->id,
            'category_id' => $this->category->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'name' => 'Kursi Inventaris Uji',
            'inventory_number' => 'INV-001',
            'status' => 'active',
            'ownership' => 'yayasan',
        ]);

        Livewire::test(ListInventoryCategory::class)
            ->assertTableColumnExists('has_photo')
            ->assertTableFilterExists('photo_status')
            ->assertTableActionExists('addPhoto')
            ->assertTableActionExists('viewPhoto');
    }

    public function test_inventory_balance_table_has_photo_column_filter_and_actions(): void
    {
        $balance = InventoryBalance::create([
            'category_id' => $this->category->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'name' => 'Kertas A4 Uji',
            'brand' => 'PaperOne',
            'quantity' => 50,
            'last_unit' => 'Rim',
        ]);

        Livewire::test(ListInventoryBalances::class)
            ->assertTableColumnExists('has_photo')
            ->assertTableFilterExists('photo_status')
            ->assertTableActionExists('addPhoto')
            ->assertTableActionExists('viewPhoto');
    }
}

