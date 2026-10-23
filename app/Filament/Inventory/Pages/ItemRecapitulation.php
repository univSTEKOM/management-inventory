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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->type = 'all';
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

        $groupColumn = $campus ? 'location_id' : 'campus_id';
        $totals = (clone $query)
            ->selectRaw("{$groupColumn}, SUM(CASE WHEN row_type = 'asset' THEN quantity ELSE 0 END) as individual_count, SUM(CASE WHEN row_type = 'supply' THEN quantity ELSE 0 END) as stock_quantity")
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

        if (filled($this->type) && $this->type !== 'all') {
            $itemsQuery->where('row_type', $this->type);
        }

        return [
            'selectedCampus' => $campus,
            'selectedRoom' => $room,
            'groups' => $room ? collect() : ($campus
                ? $campus->locations()->orderBy('name')->get()
                : Campus::orderBy('name')->get()),
            'totals' => $totals,
            'individualCount' => (clone $query)->where('row_type', 'asset')->sum('quantity'),
            'stockQuantity' => (clone $query)->where('row_type', 'supply')->sum('quantity'),
            'items' => $campus
                ? (clone $itemsQuery)->orderBy('item_name')->orderBy('id')->paginate(25)
                : null,
            'search' => $this->search,
            'type' => $this->type,
        ];
    }
}

