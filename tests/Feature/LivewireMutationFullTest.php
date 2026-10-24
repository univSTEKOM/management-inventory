<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;
use Filament\Facades\Filament;
use Livewire\Livewire;

class LivewireMutationFullTest extends \Tests\MutationTestCase
{
    private function page()
    {
        Filament::setCurrentPanel(Filament::getPanel('inventory'));
        return Livewire::test(CreateMutation::class)
            ->set('data.source_campus_id', $this->header['source_campus_id'])
            ->set('data.source_location_id', $this->header['source_location_id'])
            ->set('data.destination_campus_id', $this->header['destination_campus_id'])
            ->set('data.destination_location_id', $this->header['destination_location_id'])
            ->set('data.reason', 'Pindah ruangan');
    }

    public function test_mixed_selection_clears_when_source_changes(): void
    {
        $this->page()->call('addItem', 'asset:'.$this->asset->id)
            ->call('addItem', 'supply:'.$this->supply->id)->assertCount('items', 2)
            ->set('data.source_location_id', $this->header['destination_location_id'])->assertSet('items', []);
    }

    public function test_confirmation_processes_selected_items_once(): void
    {
        $page = $this->page()->call('addItem', 'asset:'.$this->asset->id)
            ->call('addItem', 'supply:'.$this->supply->id)
            ->set('items.1.quantity', 3)->call('review')->assertSet('confirming', true)
            ->assertSee('Konfirmasi perpindahan barang');
        $this->assertDatabaseCount('mutations', 0);
        $page->call('process')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('mutations', ['status' => 'completed', 'type' => 'mixed']);
        $this->assertEquals(7, $this->supply->refresh()->quantity);
    }

    public function test_source_campus_change_resets_room_and_selection(): void
    {
        $this->page()->call('addItem', 'asset:'.$this->asset->id)
            ->set('data.source_campus_id', \App\Models\Campus::factory()->create()->id)
            ->assertSet('items', [])->assertSet('data.source_location_id', '');
    }
}
