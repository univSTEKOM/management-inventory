<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;
use Filament\Facades\Filament;
use Livewire\Livewire;

class LivewireMutationTest extends \Tests\MutationTestCase
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

    public function test_searches_by_code_and_name(): void
    {
        $page = $this->page()->set('search', $this->asset->inventory_number)->assertSee($this->asset->name);
        $page->set('search', $this->asset->name)->assertSee($this->asset->inventory_number);
        $page->call('addItem', 'asset:'.$this->asset->id)->call('addItem', 'asset:'.$this->asset->id)->assertCount('items', 1);
    }

    public function test_search_excludes_other_rooms_and_ineligible_assets(): void
    {
        $this->asset->update(['name' => 'Komputer asal']);
        $other = \App\Models\Asset::factory()->create([
            'name' => 'Komputer tujuan', 'campus_id' => $this->header['source_campus_id'],
            'location_id' => $this->header['destination_location_id'],
        ]);
        $this->page()->set('search', 'Komputer')->assertSee('Komputer asal')->assertDontSee('Komputer tujuan');
        $this->asset->update(['status' => 'lost']);
        $this->page()->set('search', 'Komputer')->assertSee('Tidak ada barang yang cocok');
    }
}
