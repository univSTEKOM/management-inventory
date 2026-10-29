<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Mutation;
use App\Models\StockOpname;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Widget statistik dashboard panel Inventory.
 *
 * Setiap stat hanya dirender jika pengguna memiliki permission
 * widget yang sesuai. Widget ini tidak ditampilkan sama sekali
 * jika pengguna tidak memiliki dashboard.view.
 */
class DashboardStatsOverview extends StatsOverviewWidget
{
    /**
     * Widget tidak ditampilkan jika user tidak punya akses dashboard.
     */
    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('dashboard.view');
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $stats = [];

        if ($user->hasPermissionTo('dashboard.widget.total_assets')) {
            $stats[] = Stat::make('Total Barang / Aset', Asset::count())
                ->description('Semua barang terdaftar')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'stat-primary',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.pending_mutations')) {
            $stats[] = Stat::make('Mutasi Lama Belum Diproses', Mutation::whereIn('status', ['pending', 'approved'])->count())
                ->description('Perlu ditindaklanjuti')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'stat-warning',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.stock_opname_active')) {
            $stats[] = Stat::make('Stock Opname Berjalan', StockOpname::where('status', 'in_progress')->count())
                ->description('Sesi opname aktif')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('info')
                ->extraAttributes([
                    'class' => 'stat-info',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.total_locations')) {
            $stats[] = Stat::make('Total Lokasi', Location::count())
                ->description('Lokasi penyimpanan aset')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success')
                ->extraAttributes([
                    'class' => 'stat-success',
                ]);
        }

        return $stats;
    }
}
