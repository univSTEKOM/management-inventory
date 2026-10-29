<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Widget statistik dashboard panel Admin.
 *
 * Setiap stat hanya dirender jika pengguna memiliki permission
 * widget yang sesuai. Superadmin mendapat semua widget.
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
            $stats[] = Stat::make('Total Aset', Asset::count())
                ->description('Semua aset terdaftar')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'stat-primary',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.total_categories')) {
            $stats[] = Stat::make('Total Kategori', Category::count())
                ->description('Kategori aset & inventaris')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success')
                ->extraAttributes([
                    'class' => 'stat-success',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.total_locations')) {
            $stats[] = Stat::make('Total Lokasi', Location::count())
                ->description('Lokasi penyimpanan aset')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'stat-warning',
                ]);
        }

        if ($user->hasPermissionTo('dashboard.widget.total_employees')) {
            $stats[] = Stat::make('Total Karyawan', Employee::count())
                ->description('PIC pengelola aset')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->extraAttributes([
                    'class' => 'stat-info',
                ]);
        }

        return $stats;
    }
}
