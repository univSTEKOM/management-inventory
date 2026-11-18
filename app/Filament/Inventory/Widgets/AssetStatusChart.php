<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class AssetStatusChart extends ChartWidget
{
    protected ?string $heading = 'Status Aset';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('dashboard.widget.asset_status_chart');
    }

    public function getDescription(): string | Htmlable | null
    {
        $total = Asset::query()
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['baru_dilaporkan', 'menunggu_pengecekan'])
            ->count();

        return "Total {$total} unit aset berdasarkan status operasional";
    }

    protected function getMaxHeight(): ?string
    {
        return '280px';
    }

    protected static array $statusMeta = [
        'stock' => [
            'label' => 'Tersedia',
            'color' => '#10b981', // Emerald
        ],
        'active' => [
            'label' => 'Aktif Digunakan',
            'color' => '#3b82f6', // Blue
        ],
        'borrowed' => [
            'label' => 'Dipinjam',
            'color' => '#8b5cf6', // Purple
        ],
        'maintenance' => [
            'label' => 'Dalam Perbaikan',
            'color' => '#f59e0b', // Amber
        ],
        'lost' => [
            'label' => 'Hilang',
            'color' => '#ef4444', // Red
        ],
        'sold' => [
            'label' => 'Terjual',
            'color' => '#06b6d4', // Cyan
        ],
        'disposed' => [
            'label' => 'Dihapuskan',
            'color' => '#64748b', // Slate
        ],
        'administratively_deleted' => [
            'label' => 'Penghapusan Admin',
            'color' => '#78716c', // Stone
        ],
        'destroyed' => [
            'label' => 'Dimusnahkan',
            'color' => '#334155', // Slate dark
        ],
    ];

    protected function getData(): array
    {
        $counts = Asset::query()
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['baru_dilaporkan', 'menunggu_pengecekan'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByRaw('count(*) desc')
            ->pluck('total', 'status');

        $labels = [];
        $data = [];
        $backgroundColors = [];

        foreach ($counts as $status => $total) {
            $meta = static::$statusMeta[$status] ?? [
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'color' => '#9ca3af',
            ];

            $labels[] = $meta['label'];
            $data[] = (int) $total;
            $backgroundColors[] = $meta['color'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Aset',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
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
                    'position' => 'bottom',
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
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
