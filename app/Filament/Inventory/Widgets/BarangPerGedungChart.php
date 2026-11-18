<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\UnifiedItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class BarangPerGedungChart extends ChartWidget
{
    protected ?string $heading = 'Perbandingan Barang per Gedung';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('dashboard.view');
    }

    public function getDescription(): string | Htmlable | null
    {
        return 'Komparasi jumlah Semua Barang, Aset Tetap, Inventaris, dan BHP di tiap gedung';
    }

    protected function getMaxHeight(): ?string
    {
        return '280px';
    }

    protected function getData(): array
    {
        try {
            $rows = UnifiedItem::query()
                ->selectRaw("
                    COALESCE(campus_name, 'Tanpa Gedung') as campus_name,
                    SUM(CASE WHEN row_type = 'asset' AND classification_slug = 'aset' THEN quantity ELSE 0 END) as aset_count,
                    SUM(CASE WHEN row_type = 'asset' AND classification_slug = 'inventaris' THEN quantity ELSE 0 END) as inventaris_count,
                    SUM(CASE WHEN row_type = 'supply' THEN quantity ELSE 0 END) as bhp_count,
                    SUM(quantity) as total_count
                ")
                ->groupBy('campus_name')
                ->orderByDesc('total_count')
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $labels = [];
        $totalData = [];
        $asetData = [];
        $inventarisData = [];
        $bhpData = [];

        foreach ($rows as $row) {
            $labels[] = $row->campus_name;
            $totalData[] = (int) $row->total_count;
            $asetData[] = (int) $row->aset_count;
            $inventarisData[] = (int) $row->inventaris_count;
            $bhpData[] = (int) $row->bhp_count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Semua Barang',
                    'data' => $totalData,
                    'backgroundColor' => '#6366f1', // Indigo
                    'borderColor' => '#4f46e5',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Aset',
                    'data' => $asetData,
                    'backgroundColor' => '#10b981', // Emerald
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Inventaris',
                    'data' => $inventarisData,
                    'backgroundColor' => '#3b82f6', // Blue
                    'borderColor' => '#2563eb',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'BHP',
                    'data' => $bhpData,
                    'backgroundColor' => '#f59e0b', // Amber
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 12,
                        'font' => [
                            'family' => 'Outfit, sans-serif',
                            'size' => 11,
                            'weight' => '600',
                        ],
                    ],
                ],
                'tooltip' => [
                    'padding' => 10,
                    'cornerRadius' => 8,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(156, 163, 175, 0.12)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'font' => [
                            'family' => 'Outfit, sans-serif',
                            'size' => 11,
                            'weight' => '600',
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
