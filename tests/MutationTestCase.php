<?php

namespace Tests;

use App\Models\{Asset, Campus, Category, Classification, InventoryBalance, Location, Mutation, Permission, User};
use App\Services\DirectMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

abstract class MutationTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $operator;
    protected array $header;
    protected Asset $asset;
    protected InventoryBalance $supply;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create();
        foreach (['movements.create', 'movements.view', 'panel.inventory'] as $name) {
            $this->operator->permissions()->attach(Permission::firstOrCreate(['name' => $name])->id);
        }
        $this->actingAs($this->operator);
        $campus = Campus::factory()->create();
        $source = Location::factory()->create(['campus_id' => $campus->id]);
        $target = Location::factory()->create(['campus_id' => $campus->id]);
        $this->header = [
            'source_campus_id' => $campus->id, 'source_location_id' => $source->id,
            'destination_campus_id' => $campus->id, 'destination_location_id' => $target->id,
            'reason' => 'Pemindahan ruangan',
        ];
        $this->asset = Asset::factory()->create([
            'campus_id' => $campus->id, 'location_id' => $source->id,
            'classification_id' => Classification::firstOrCreate(['slug' => 'aset'], ['name' => 'Aset'])->id,
        ]);
        $this->supply = InventoryBalance::create([
            'category_id' => Category::factory()->create()->id, 'name' => 'Kertas A4', 'brand' => 'Merek A',
            'campus_id' => $campus->id, 'location_id' => $source->id, 'quantity' => 10,
        ]);
    }

    protected function selected(): array
    {
        return [['key' => 'asset:'.$this->asset->id, 'quantity' => 1], ['key' => 'supply:'.$this->supply->id, 'quantity' => 3]];
    }

}
