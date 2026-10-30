<?php

namespace App\Filament\Inventory\Resources\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Asset;

class CategoryAssetStatsWidget extends StatsOverviewWidget
{
    public string $categorySlug = 'aset';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasPermissionTo('assets.widget.total_items')
            || ($user->hasPermissionTo('financial.view')
                && $user->hasPermissionTo('assets.widget.acquisition_value'))
        );
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $stats = [];
        $slug = $this->categorySlug;

        $query = Asset::whereHas('classification', fn($q) => $q->where('slug', $slug));

        if ($user->hasPermissionTo('assets.widget.total_items')) {
            $stats[] = Stat::make('Total Barang', number_format((clone $query)->count(), 0, ',', '.'))
                ->description('Jumlah barang keseluruhan di kategori ini')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary');
        }

        if (! $user->hasPermissionTo('financial.view')
            || ! $user->hasPermissionTo('assets.widget.acquisition_value')) {
            return $stats;
        }

        // Dual-path price calculation:
        // - New assets: use purchaseItem.unit_price (per-unit, correct)
        // - Legacy assets (no purchaseItem): fallback to asset_purchases.total_price
        $newArchPrice = (clone $query)
            ->whereHas('purchaseItem')
            ->join('purchase_items', 'assets.purchase_item_id', '=', 'purchase_items.id')
            ->sum('purchase_items.unit_price');

        $legacyPrice = (clone $query)
            ->whereNull('purchase_item_id')
            ->leftJoin('asset_purchases', 'assets.id', '=', 'asset_purchases.asset_id')
            ->sum('asset_purchases.total_price');

        $totalPrice = $newArchPrice + $legacyPrice;

        $stats[] = Stat::make('Total Harga Perolehan', 'Rp ' . number_format($totalPrice, 0, ',', '.'))
            ->description('Total nilai aset di kategori ini')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('success');

        return $stats;
    }
}
