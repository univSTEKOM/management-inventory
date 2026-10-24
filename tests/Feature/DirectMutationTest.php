<?php

namespace Tests\Feature;

use App\Models\{Asset, Campus, Category, Classification, InventoryBalance, Location, Mutation, Permission, User};
use App\Services\DirectMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DirectMutationTest extends \Tests\MutationTestCase
{
    public function test_mixed_mutation_completes_without_approval_and_preserves_identity(): void
    {
        $inventory = Asset::factory()->create([
            'campus_id' => $this->header['source_campus_id'], 'location_id' => $this->header['source_location_id'],
            'classification_id' => Classification::firstOrCreate(['slug' => 'inventaris'], ['name' => 'Inventaris'])->id,
        ]);
        $before = $this->asset->only(['inventory_number', 'barcode', 'classification_id']);
        $mutation = app(DirectMutationService::class)->create($this->operator, $this->header,
            [...$this->selected(), ['key' => 'asset:'.$inventory->id, 'quantity' => 1]], (string) Str::uuid());
        $this->assertSame('completed', $mutation->status);
        $this->assertNull($mutation->approved_by);
        $this->assertCount(3, $mutation->items);
        $this->assertEquals($this->header['destination_location_id'], $this->asset->refresh()->location_id);
        $this->assertEquals($this->header['destination_location_id'], $inventory->refresh()->location_id);
        $this->assertEquals($before, $this->asset->only(array_keys($before)));
        $this->assertEquals(7, $this->supply->refresh()->quantity);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->header['destination_location_id'], 'brand' => 'Merek A', 'quantity' => 3]);
        $name = $this->asset->name;
        $this->asset->update(['name' => 'Nama baru']);
        $this->assertSame($name, $mutation->items()->where('asset_id', $this->asset->id)->first()->display_details['name']);
    }

    public function test_insufficient_stock_rolls_back_every_item_and_mutation(): void
    {
        $items = $this->selected();
        $items[1]['quantity'] = 11;
        try {
            app(DirectMutationService::class)->create($this->operator, $this->header, $items, (string) Str::uuid());
            $this->fail('Expected stock validation failure');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }
        $this->assertDatabaseCount('mutations', 0);
        $this->assertDatabaseCount('mutation_items', 0);
        $this->assertEquals($this->header['source_location_id'], $this->asset->refresh()->location_id);
        $this->assertEquals(10, $this->supply->refresh()->quantity);
    }

    public function test_retry_does_not_deduct_stock_twice(): void
    {
        $token = (string) Str::uuid();
        $service = app(DirectMutationService::class);
        $first = $service->create($this->operator, $this->header, $this->selected(), $token);
        $second = $service->create($this->operator, $this->header, $this->selected(), $token);
        $this->assertSame($first->id, $second->id);
        $this->assertEquals(7, $this->supply->refresh()->quantity);
        $this->assertDatabaseCount('mutations', 1);
    }

    public function test_supply_does_not_merge_into_another_brand(): void
    {
        $other = InventoryBalance::create([
            'category_id' => $this->supply->category_id, 'name' => $this->supply->name, 'brand' => 'Merek B',
            'campus_id' => $this->header['destination_campus_id'], 'location_id' => $this->header['destination_location_id'], 'quantity' => 20,
        ]);
        app(DirectMutationService::class)->create($this->operator, $this->header, $this->selected(), (string) Str::uuid());
        $this->assertEquals(20, $other->refresh()->quantity);
        $this->assertDatabaseHas('inventory_balances', ['brand' => 'Merek A', 'location_id' => $other->location_id, 'quantity' => 3]);
    }

    public function test_moved_asset_is_rejected(): void
    {
        $this->asset->update(['location_id' => $this->header['destination_location_id']]);
        $this->expectException(ValidationException::class);
        app(DirectMutationService::class)->create($this->operator, $this->header, $this->selected(), (string) Str::uuid());
    }

    public function test_duplicate_items_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(DirectMutationService::class)->create($this->operator, $this->header, [$this->selected()[0], $this->selected()[0]], (string) Str::uuid());
    }

    public function test_same_location_is_rejected(): void
    {
        $this->header['destination_location_id'] = $this->header['source_location_id'];
        $this->expectException(ValidationException::class);
        app(DirectMutationService::class)->create($this->operator, $this->header, $this->selected(), (string) Str::uuid());
    }

    public function test_legacy_pending_requires_explicit_processing_and_cannot_be_edited(): void
    {
        $legacy = Mutation::create($this->header + ['type' => 'asset', 'status' => 'pending', 'requested_by' => $this->operator->id]);
        $legacy->items()->create(['asset_id' => $this->asset->id, 'quantity' => 1]);
        $this->assertFalse($this->operator->can('update', $legacy));
        $this->assertFalse($this->operator->can('delete', $legacy));
        $this->assertEquals($this->header['source_location_id'], $this->asset->refresh()->location_id);
        app(DirectMutationService::class)->process($this->operator, $legacy);
        $this->assertSame('completed', $legacy->refresh()->status);
        $this->assertFalse($this->operator->can('process', $legacy));
        $this->assertFalse($this->operator->can('cancel', $legacy));
    }

    public function test_user_without_permission_cannot_process(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(DirectMutationService::class)->create(User::factory()->create(), $this->header, $this->selected(), (string) Str::uuid());
    }

    public function test_cancel_legacy_does_not_move_stock(): void
    {
        $mutation = Mutation::create($this->header + ['type' => 'consumable', 'status' => 'approved']);
        $mutation->items()->create(['inventory_balance_id' => $this->supply->id, 'quantity' => 3]);
        app(DirectMutationService::class)->cancel($this->operator, $mutation);
        $this->assertSame('cancelled', $mutation->refresh()->status);
        $this->assertEquals(10, $this->supply->refresh()->quantity);
    }

    public function test_receipt_uses_snapshot_and_requires_permission(): void
    {
        $mutation = app(DirectMutationService::class)->create($this->operator, $this->header, $this->selected(), (string) Str::uuid());
        $this->get(route('mutation.receipt', $mutation))->assertOk()->assertSee($mutation->mutation_number)->assertSee('Kertas A4');
        $this->actingAs(User::factory()->create())->get(route('mutation.receipt', $mutation))->assertForbidden();
    }

    public function test_fractional_supply_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(DirectMutationService::class)->create($this->operator, $this->header,
            [['key' => 'supply:'.$this->supply->id, 'quantity' => 1.5]], (string) Str::uuid());
    }
}
