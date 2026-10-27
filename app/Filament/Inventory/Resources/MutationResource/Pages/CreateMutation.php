<?php

namespace App\Filament\Inventory\Resources\MutationResource\Pages;

use App\Filament\Inventory\Resources\MutationResource;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Mutation;
use App\Services\DirectMutationService;
use App\Services\MutationSearchService;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CreateMutation extends Page
{
    protected static string $resource = MutationResource::class;
    protected string $view = 'filament.inventory.mutations.create';
    protected static ?string $title = 'Mutasi Barang';

    public array $data = [
        'source_campus_id' => '', 'source_location_id' => '',
        'destination_campus_id' => '', 'destination_location_id' => '',
        'source_pic_id' => '', 'destination_pic_id' => '', 'reason' => '',
    ];
    public string $search = '';
    public array $items = [];
    public bool $confirming = false;
    #[Locked]
    public string $requestToken;

    public function mount(): void
    {
        Gate::authorize('create', Mutation::class);
        $this->requestToken = (string) Str::uuid();
    }

    public function updatedData(mixed $value, string $key): void
    {
        $this->confirming = false;
        if (in_array($key, ['source_campus_id', 'source_location_id'])) {
            $this->items = [];
            $this->search = '';
        }
        if ($key === 'source_campus_id') {
            $this->data['source_location_id'] = '';
        }
        if ($key === 'destination_campus_id') {
            $this->data['destination_location_id'] = '';
        }
    }

    public function updatedItems(): void
    {
        $this->confirming = false;
    }

    public function addItem(string $key): void
    {
        Gate::authorize('create', Mutation::class);
        if (! preg_match('/^(asset|supply):([1-9][0-9]*)$/', $key, $match)) {
            return;
        }
        foreach ($this->items as $item) {
            if ($item['key'] === $key) {
                return;
            }
        }
        $model = $match[1] === 'asset' ? Asset::class : InventoryBalance::class;
        $item = $model::whereKey($match[2])->where('campus_id', $this->data['source_campus_id'])
            ->where('location_id', $this->data['source_location_id'])->firstOrFail();
        $this->items[] = MutationSearchService::describe($item) + ['quantity' => 1];
        $this->confirming = false;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->confirming = false;
    }

    public function review(): void
    {
        Gate::authorize('create', Mutation::class);
        $this->validate([
            'data.source_campus_id' => ['required'], 'data.source_location_id' => ['required'],
            'data.destination_campus_id' => ['required'],
            'data.destination_location_id' => ['required', 'different:data.source_location_id'],
            'data.reason' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $this->confirming = true;
    }

    public function process(): void
    {
        Gate::authorize('create', Mutation::class);
        abort_unless($this->confirming, 422);
        $data = $this->data;
        foreach (['source_pic_id', 'destination_pic_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }
        try {
            $mutation = app(DirectMutationService::class)->create(auth()->user(), $data, $this->items, $this->requestToken);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->confirming = false;
            throw $exception;
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            $this->confirming = false;
            $this->addError('items', 'Mutasi gagal diproses. Periksa kembali data dan coba lagi.');
            return;
        }
        \Filament\Notifications\Notification::make()->title('Mutasi berhasil diproses')->success()->send();
        if (auth()->user()->can('view', $mutation)) {
            $this->redirect(MutationResource::getUrl('view', ['record' => $mutation]));
        } else {
            $this->redirect(MutationResource::getUrl('create'));
        }
    }

    protected function getViewData(): array
    {
        return [
            'campuses' => Campus::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'sourceLocations' => Location::where('campus_id', $this->data['source_campus_id'])->orderBy('name')->get(),
            'destinationLocations' => Location::where('campus_id', $this->data['destination_campus_id'])->orderBy('name')->get(),
            'results' => MutationSearchService::search((int) $this->data['source_campus_id'], (int) $this->data['source_location_id'], $this->search),
        ];
    }
}
