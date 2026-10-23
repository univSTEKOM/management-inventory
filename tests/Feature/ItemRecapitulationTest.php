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
            ['asset_'.$asset->id, 'supply_'.$balance->id],
            $detail['items']->pluck('id')->all(),
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
        $this->assertEquals('asset_'.$asset->id, $searchData['items']->first()->id);

        $pageType = $this->page($campus, $room);
        $pageType->type = 'supply';
        $typeData = $pageType->data();
        $this->assertEquals(1, $typeData['items']->total());
        $this->assertEquals('supply_'.$balance->id, $typeData['items']->first()->id);
    }
}

