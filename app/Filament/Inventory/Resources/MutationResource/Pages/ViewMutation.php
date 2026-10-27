<?php

namespace App\Filament\Inventory\Resources\MutationResource\Pages;

use App\Filament\Inventory\Resources\MutationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewMutation extends ViewRecord
{
    protected static string $resource = MutationResource::class;
    protected string $view = 'filament.inventory.mutations.view';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Cetak Bukti Mutasi')->icon('heroicon-o-printer')
                ->visible(fn () => $this->record->status === 'completed')
                ->url(fn () => route('mutation.receipt', $this->record))->openUrlInNewTab(),
        ];
    }
}
