<?php

namespace App\Filament\Inventory\Pages;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\ItemReclassification;
use App\Models\Location;
use App\Services\ItemReclassificationService;
use App\Services\MutationValidationService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class ReclassifyItems extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.reclassify-items';

    protected static ?string $slug = 'reclassify-items';

    protected static ?string $title = 'Reklasifikasi Barang';

    public string $source = '';

    public string $destination = '';

    public string $search = '';

    public array $selected = [];

    public array $quantities = [];

    public ?int $locationId = null;

    public string $reason = '';

    #[Locked]
    public array $preview = [];

    #[Locked]
    public string $token;

    #[Locked]
    public ?int $resultId = null;

    public static function canAccess(): bool
    {
        return ItemReclassificationService::userCan(Auth::user());
    }

    public function mount(): void
    {
        $this->token = (string) Str::uuid();
    }

    public function updated(string $property): void
    {
        if ($property === 'source') {
            $this->selected = $this->quantities = [];
        }

        if (in_array($property, ['source', 'destination', 'search'], true)) {
            $this->resetPage();
        }

        if ($property !== 'search') {
            $this->preview = [];
        }
    }

    public function selectAllVisible(array $ids): void
    {
        $stringIds = array_map('strval', $ids);
        $this->selected = array_values(array_unique(array_merge($this->selected, $stringIds)));
    }

    public function deselectAll(): void
    {
        $this->selected = [];
        $this->quantities = [];
        $this->preview = [];
    }

    public function toggleGroup(array $ids): void
    {
        $stringIds = array_map('strval', $ids);
        $currentSelected = array_map('strval', $this->selected);
        $intersect = array_intersect($stringIds, $currentSelected);

        if (count($intersect) === count($stringIds)) {
            // Unselect all in this group
            $this->selected = array_values(array_diff($currentSelected, $stringIds));
        } else {
            // Select all in this group
            $this->selected = array_values(array_unique(array_merge($currentSelected, $stringIds)));
        }
    }

    public function showPreview(ItemReclassificationService $service): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetErrorBag();
        $this->validate([
            'source' => ['required', 'in:'.implode(',', ItemReclassificationService::slugs())],
            'destination' => ['required', 'different:source', 'in:'.implode(',', ItemReclassificationService::slugs())],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->preview = $service->preview($this->source, $this->destination, $this->selection(), $this->options());
    }

    public function confirm(ItemReclassificationService $service): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->preview === [] || $this->preview['errors'] !== []) {
            return;
        }

        $transaction = $service->execute(Auth::user(), $this->source, $this->destination, $this->selection(),
            $this->options(), $this->token, ItemReclassificationService::fingerprint($this->preview));

        $this->resultId = $transaction->id;
        $this->preview = $this->selected = $this->quantities = [];
        $this->token = (string) Str::uuid();
    }

    private function selection(): array
    {
        if ($this->source === ItemReclassificationService::SLUG_SUPPLY) {
            return array_intersect_key($this->quantities, array_flip($this->selected))
                + array_fill_keys($this->selected, 1);
        }

        return $this->selected;
    }

    private function options(): array
    {
        return ['destination_location_id' => $this->locationId, 'reason' => $this->reason];
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $supply = $this->source === ItemReclassificationService::SLUG_SUPPLY;
        $rows = null;

        if (in_array($this->source, ItemReclassificationService::slugs(), true)
            && in_array($this->destination, ItemReclassificationService::slugs(), true)
            && $this->destination !== $this->source) {
            $query = $supply
                ? InventoryBalance::query()->where('quantity', '>', 0)
                : Asset::query()->whereIn('status', MutationValidationService::ELIGIBLE_STATUSES)
                    ->whereHas('classification', fn ($query) => $query->where('slug', $this->source));

            $barcode = $supply ? 'master_barcode' : 'barcode';
            $term = '%'.trim($this->search).'%';
            $rows = $query->with(['location.campus', 'category'])
                ->whereHas('category.classifications', fn ($query) => $query->where('slug', $this->destination))
                ->when(filled($this->search), fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)->orWhere($barcode, 'like', $term)))
                ->orderBy('name')->orderBy('id')->paginate(25);
        }

        return [
            'labels' => ['aset' => 'Aset', 'inventaris' => 'Inventaris', 'barang-habis-pakai' => 'BHP'],
            'supply' => $supply,
            'rows' => $rows,
            'locations' => Location::with('campus')->orderBy('name')->get(),
            'history' => ItemReclassification::with(['user', 'sourceClassification', 'destinationClassification'])
                ->latest('id')->paginate(15, pageName: 'historyPage'),
            'result' => $this->resultId ? ItemReclassification::with('lines')->find($this->resultId) : null,
        ];
    }

    public function viewResult(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resultId = $id;
    }
}
