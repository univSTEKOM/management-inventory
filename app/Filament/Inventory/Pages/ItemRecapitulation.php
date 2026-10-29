<?php

namespace App\Filament\Inventory\Pages;

use App\Models\Campus;
use App\Models\UnifiedItem;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ItemRecapitulation extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.item-recapitulation';

    protected static ?string $title = 'Rekapitulasi Barang';

    #[Url]
    public ?int $campus = null;

    #[Url]
    public ?int $room = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = 'all';

    #[Url]
    public ?string $item = null;

    #[Url]
    public string $tab = 'rooms';

    public function updatingSearch(): void
    {
        $this->item = null;
        if ($this->campus && !$this->room) {
            $this->tab = 'items';
        }
        $this->resetPage();
    }

    public function updatingType(): void
    {
        if ($this->campus && !$this->room) {
            $this->tab = 'items';
        }
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        if ($this->campus && !$this->room) {
            $this->tab = 'items';
        }
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->type = 'all';
        $this->item = null;
        $this->resetPage();
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('reports.view') ?? false;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        abort_if($this->room !== null && $this->campus === null, 404);

        $campus = $this->campus !== null ? Campus::findOrFail($this->campus) : null;
        $room = $this->room !== null
            ? $campus->locations()->findOrFail($this->room)
            : null;

        // Use the same eligibility rules as Semua Barang, without cached totals.
        $query = UnifiedItem::query()->toBase();
        if ($campus) {
            $query->where('campus_id', $campus->id);
        }
        if ($room) {
            $query->where('location_id', $room->id);
        }

        $countSql = "SUM(CASE WHEN row_type = 'asset' AND classification_slug = 'aset' THEN quantity ELSE 0 END) as asset_count,
            SUM(CASE WHEN row_type = 'asset' AND classification_slug = 'inventaris' THEN quantity ELSE 0 END) as inventory_count,
            SUM(CASE WHEN row_type = 'supply' THEN quantity ELSE 0 END) as stock_quantity";

        $groupColumn = $campus ? 'location_id' : 'campus_id';
        $totals = (clone $query)
            ->selectRaw("{$groupColumn}, {$countSql}, SUM(CASE WHEN row_type = 'asset' THEN quantity ELSE 0 END) as individual_count")
            ->groupBy($groupColumn)
            ->get()
            ->keyBy($groupColumn);

        $itemsQuery = clone $query;
        if (filled($this->search)) {
            $searchTerm = '%' . trim($this->search) . '%';
            $itemsQuery->where(function ($q) use ($searchTerm) {
                $q->where('item_name', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm)
                    ->orWhere('brand', 'like', $searchTerm)
                    ->orWhere('classification_name', 'like', $searchTerm)
                    ->orWhere('category_name', 'like', $searchTerm);
            });
        }

        if (in_array($this->type, ['asset', 'inventory'], true)) {
            $itemsQuery->where('row_type', 'asset')
                ->where('classification_slug', $this->type === 'asset' ? 'aset' : 'inventaris');
        } elseif ($this->type === 'supply') {
            $itemsQuery->where('row_type', 'supply');
        }

        // Aggregate in SQL before pagination: one row per name, then per brand.
        if ($this->item !== null) {
            $itemsQuery->where(function ($q) {
                $q->where('item_name', $this->item)
                    ->orWhereRaw('TRIM(item_name) = ?', [trim($this->item)]);
            });
        }
        $dimension = $this->item === null ? "COALESCE(NULLIF(TRIM(item_name), ''), 'Tanpa Nama')" : "COALESCE(NULLIF(TRIM(brand), ''), '')";
        $dimensionAlias = $this->item === null ? 'item_name' : 'brand';
        $itemsQuery->selectRaw("{$dimension} as {$dimensionAlias}, SUM(quantity) as quantity, {$countSql},
            CASE WHEN COUNT(DISTINCT COALESCE(NULLIF(TRIM(unit_name), ''), '—')) = 1
                THEN MIN(COALESCE(NULLIF(TRIM(unit_name), ''), '—')) ELSE 'Beragam satuan' END as unit_name")
            ->groupByRaw($dimension)->orderBy($dimensionAlias);
        $summary = (clone $query)->selectRaw($countSql)->first();

        return [
            'selectedCampus' => $campus,
            'selectedRoom' => $room,
            'groups' => $room ? collect() : ($campus
                ? $campus->locations()->orderBy('name')->get()
                : Campus::orderBy('name')->get()),
            'totals' => $totals,
            'assetCount' => $summary->asset_count ?? 0,
            'inventoryCount' => $summary->inventory_count ?? 0,
            'selectedItem' => $this->item,
            'individualCount' => (clone $query)->where('row_type', 'asset')->sum('quantity'),
            'stockQuantity' => (clone $query)->where('row_type', 'supply')->sum('quantity'),
            'items' => $campus
                ? $itemsQuery->paginate(25)
                : null,
            'search' => $this->search,
            'type' => $this->type,
            'tab' => $this->tab,
        ];
    }
}

