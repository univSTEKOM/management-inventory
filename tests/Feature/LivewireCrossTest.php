<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;
use Filament\Facades\Filament;
use Livewire\Livewire;

class LivewireCrossTest extends \Tests\MutationTestCase
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

    public function test_rejects_submission_after_source_changes(): void
    {
        $this->page()->call('addItem', 'asset:'.$this->asset->id)
            ->set('data.source_location_id', $this->header['destination_location_id'])
            ->call('review')->assertHasErrors(['items']);
    }
}
