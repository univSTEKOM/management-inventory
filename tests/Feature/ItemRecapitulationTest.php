<?php

namespace Tests\Feature;

use App\Filament\Inventory\Pages\ItemRecapitulation;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ItemRecapitulationTest extends TestCase
{
    use RefreshDatabase;

    private function page(?int $campus = null, ?int $room = null): ItemRecapitulation
    {
        $page = new class extends ItemRecapitulation
        {
            public function data(): array
            {
                return $this->getViewData();
            }
        };
        $page->campus = $campus;
        $page->room = $room;

        return $page;
    }

    private function authorizeReports(): void
    {
        $user = User::factory()->create();
        $user->permissions()->attach(Permission::firstOrCreate(['name' => 'reports.view'])->id);
        $this->actingAs($user);
    }

    public function test_recap_counts_individual_items_and_bulk_stock_by_assignment(): void
    {
        $this->authorizeReports();
        $asset = Asset::factory()->create(['name' => 'Komputer Rekap']);
        $campus = $asset->campus_id;
        $room = $asset->location_id;
        $emptyRoom = Location::factory()->create(['campus_id' => $campus]);
        $emptyCampus = Campus::factory()->create();
        $other = Asset::factory()->create();
        Asset::factory()->create(['campus_id' => $campus, 'location_id' => $room, 'status' => 'baru_dilaporkan']);
        Asset::factory()->create(['campus_id' => $campus, 'location_id' => $room, 'status' => 'menunggu_pengecekan']);
        Asset::factory()->create(['campus_id' => $campus, 'location_id' => $room, 'deleted_at' => now()]);
        $balance = InventoryBalance::create([
            'category_id' => $asset->category_id,
            'campus_id' => $campus,
            'location_id' => $room,
            'name' => 'Kertas Rekap',
            'quantity' => 17,
        ]);

        $all = $this->page()->data();
        $this->assertEquals(2, $all['individualCount']);
        $this->assertEquals(17, $all['stockQuantity']);
        $this->assertTrue($all['groups']->contains('id', $emptyCampus->id));
        $this->assertEquals(1, $all['totals']->get($campus)->individual_count);
        $this->assertEquals(17, $all['totals']->get($campus)->stock_quantity);

        $home = $this->page($campus)->data();
        $this->assertTrue($home['groups']->contains('id', $emptyRoom->id));
        $this->assertEquals(1, $home['totals']->get($room)->individual_count);
        $this->assertEquals(17, $home['totals']->get($room)->stock_quantity);
        $this->assertEquals(2, $home['items']->total());

        $detail = $this->page($campus, $room)->data();
        $this->assertEqualsCanonicalizing(
            [$asset->name, $balance->name],
            $detail['items']->pluck('item_name')->all(),
        );
        $this->assertEquals(0, $this->page($campus, $emptyRoom->id)->data()['items']->total());

        // Reflect moves and stock changes immediately, without stale cached totals.
        $asset->update(['campus_id' => $other->campus_id, 'location_id' => $other->location_id]);
        $balance->update(['quantity' => 4]);
        $updated = $this->page($campus, $room)->data();
        $this->assertEquals(0, $updated['individualCount']);
        $this->assertEquals(4, $updated['stockQuantity']);
    }

    public function test_room_from_another_homebase_is_rejected(): void
    {
        $this->authorizeReports();
        $campus = Campus::factory()->create();
        $otherRoom = Location::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->page($campus->id, $otherRoom->id)->data();
    }

    public function test_room_without_homebase_is_rejected(): void
    {
        $this->authorizeReports();
        $this->expectException(HttpException::class);
        $this->page(null, 1)->data();
    }

    public function test_report_permission_is_required_even_when_rendering_directly(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertFalse(ItemRecapitulation::canAccess());
        $this->expectException(HttpException::class);
        $this->page()->data();
    }

    public function test_recap_items_can_be_filtered_by_search_and_type(): void
    {
        $this->authorizeReports();
        $asset = Asset::factory()->create(['name' => 'Laptop Dell XPS']);
        $campus = $asset->campus_id;
        $room = $asset->location_id;
        $balance = InventoryBalance::create([
            'category_id' => $asset->category_id,
            'campus_id' => $campus,
            'location_id' => $room,
            'name' => 'Kertas A4 Sinar Dunia',
            'quantity' => 10,
        ]);

        $pageSearch = $this->page($campus, $room);
        $pageSearch->search = 'Dell';
        $searchData = $pageSearch->data();
        $this->assertEquals(1, $searchData['items']->total());
        $this->assertEquals($asset->name, $searchData['items']->first()->item_name);

        $pageType = $this->page($campus, $room);
        $pageType->type = 'supply';
        $typeData = $pageType->data();
        $this->assertEquals(1, $typeData['items']->total());
        $this->assertEquals($balance->name, $typeData['items']->first()->item_name);
    }
    public function test_names_are_grouped_before_pagination_and_drill_down_by_brand(): void
    {
        $this->authorizeReports();
        $aset = \App\Models\Classification::factory()->create(['name' => 'Aset', 'slug' => 'aset']);
        $inventaris = \App\Models\Classification::factory()->create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        $first = Asset::factory()->create(['name' => 'AC', 'brand' => 'Midea', 'unit' => 'Unit', 'classification_id' => $aset->id]);
        $placement = ['campus_id' => $first->campus_id, 'location_id' => $first->location_id];
        Asset::factory()->count(2)->create($placement + ['name' => 'AC', 'brand' => 'Midea', 'unit' => 'Unit', 'classification_id' => $aset->id]);
        Asset::factory()->create($placement + ['name' => 'AC', 'brand' => 'Daikin', 'unit' => 'Unit', 'classification_id' => $inventaris->id]);
        Asset::factory()->create(['name' => 'AC', 'brand' => 'Midea', 'classification_id' => $aset->id]);
        $otherRoom = Location::factory()->create(['campus_id' => $first->campus_id]);
        Asset::factory()->create(['campus_id' => $first->campus_id, 'location_id' => $otherRoom->id, 'name' => 'AC', 'classification_id' => $aset->id]);

        $page = $this->page($first->campus_id, $first->location_id);
        $data = $page->data();
        $this->assertEquals(3, $data['assetCount']);
        $this->assertEquals(1, $data['inventoryCount']);
        $homeTotals = $this->page($first->campus_id)->data()['totals']->get($first->location_id);
        $this->assertEquals(3, $homeTotals->asset_count);
        $this->assertEquals(1, $homeTotals->inventory_count);
        $this->assertEquals(1, $data['items']->total());
        $this->assertEquals('AC', $data['items']->first()->item_name);
        $this->assertEquals(4, $data['items']->first()->quantity);
        $this->assertEquals(5, $this->page($first->campus_id)->data()['items']->first()->quantity);

        $page->item = 'AC';
        $brands = $page->data()['items']->getCollection()->keyBy('brand');
        $this->assertCount(2, $brands);
        $this->assertEquals(3, $brands['Midea']->quantity);
        $this->assertEquals(1, $brands['Daikin']->quantity);
        $page->type = 'inventory';
        $this->assertEquals(1, $page->data()['items']->first()->quantity);
        $page->type = 'asset';
        $this->assertEquals(3, $page->data()['items']->first()->quantity);
        $page->item = "AC' OR 1=1 --";
        $this->assertEquals(0, $page->data()['items']->total());
    }

    public function test_duplicate_names_do_not_consume_pagination_slots_and_missing_brands_are_combined(): void
    {
        $this->authorizeReports();
        $first = Asset::factory()->create(['name' => 'AC', 'brand' => null, 'unit' => 'Unit']);
        $placement = ['campus_id' => $first->campus_id, 'location_id' => $first->location_id];
        Asset::factory()->count(25)->create($placement + ['name' => 'AC', 'brand' => '', 'unit' => 'Unit']);
        Asset::factory()->create($placement + ['name' => 'AC ', 'brand' => '', 'unit' => 'Unit']);
        Asset::factory()->create($placement + ['name' => 'AC', 'brand' => ' ', 'unit' => 'Paket']);
        $page = $this->page($first->campus_id, $first->location_id);
        $this->assertEquals(1, $page->data()['items']->total());
        $this->assertEquals(28, $page->data()['items']->first()->quantity);
        $this->assertEquals('Beragam satuan', $page->data()['items']->first()->unit_name);
        $page->item = 'AC';
        $this->assertEquals(1, $page->data()['items']->total());
        $this->assertEquals(28, $page->data()['items']->first()->quantity);
        $page->search = 'Tidak ada';
        $this->assertEquals(0, $page->data()['items']->total());
    }
}

