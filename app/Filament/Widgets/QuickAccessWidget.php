<?php

namespace App\Filament\Widgets;

use App\Filament\Inventory\Pages\ItemRecapitulation;
use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use App\Filament\Inventory\Resources\MutationResource;
use App\Filament\Inventory\Resources\UnifiedItemResource;
use App\Models\Asset;
use App\Models\Mutation;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuickAccessWidget extends Widget
{
    protected static ?int $sort = -1;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-access-widget';

    public static function canView(): bool
    {
        if (! Auth::check() || ! Auth::user()->hasPermissionTo('dashboard.view')) {
            return false;
        }

        return MutationResource::canAccess()
            || AntrianBarangMasukResource::canAccess()
            || UnifiedItemResource::canAccess()
            || ItemRecapitulation::canAccess();
    }

    protected function getViewData(): array
    {
        $items = [];

        // 1. Mutasi Asset
        if (MutationResource::canAccess()) {
            $pending = 0;
            try {
                $pending = Mutation::whereIn('status', ['pending', 'approved'])->count();
            } catch (\Throwable $e) {}

            $url = '#';
            try {
                $url = MutationResource::getUrl('index');
            } catch (\Throwable $e) {
                $url = url('/admin/mutations');
            }

            $items[] = [
                'title' => 'Mutasi Asset',
                'description' => 'Proses pemindahan aset dan barang antar ruangan atau gedung serta riwayat mutasi.',
                'url' => $url,
                'icon' => 'heroicon-o-arrows-right-left',
                'icon_bg' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400',
                'badge' => $pending > 0 ? "{$pending} aktif" : null,
                'badge_class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
            ];
        }

        // 2. Lihat Barang Masuk
        if (AntrianBarangMasukResource::canAccess()) {
            $pendingIntake = 0;
            try {
                $pendingIntake = Asset::whereNotNull('reported_by')->whereNull('purchase_item_id')->count();
            } catch (\Throwable $e) {}

            $url = '#';
            try {
                $url = AntrianBarangMasukResource::getUrl('index');
            } catch (\Throwable $e) {
                $url = url('/admin/antrian-barang-masuks');
            }

            $items[] = [
                'title' => 'Barang Masuk',
                'description' => 'Verifikasi antrian barang yang baru tiba dan lengkapi data pembelian aset.',
                'url' => $url,
                'icon' => 'heroicon-o-inbox-arrow-down',
                'icon_bg' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400',
                'badge' => $pendingIntake > 0 ? "{$pendingIntake} baru" : null,
                'badge_class' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300',
            ];
        }

        // 3. Lihat Semua Barang
        if (UnifiedItemResource::canAccess()) {
            $url = '#';
            try {
                $url = UnifiedItemResource::getUrl('index');
            } catch (\Throwable $e) {
                $url = url('/admin/semua-barang');
            }

            $items[] = [
                'title' => 'Semua Barang',
                'description' => 'Katalog master seluruh aset tetap, inventaris kantor, dan stok persediaan.',
                'url' => $url,
                'icon' => 'heroicon-o-squares-2x2',
                'icon_bg' => 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400',
                'badge' => null,
                'badge_class' => '',
            ];
        }

        // 4. Lihat Rekapitulasi
        if (ItemRecapitulation::canAccess()) {
            $url = '#';
            try {
                $url = ItemRecapitulation::getUrl();
            } catch (\Throwable $e) {
                $url = url('/admin/item-recapitulation');
            }

            $items[] = [
                'title' => 'Rekapitulasi',
                'description' => 'Laporan ringkasan jumlah, kondisi fisik, dan sebaran barang per gedung serta ruangan.',
                'url' => $url,
                'icon' => 'heroicon-o-table-cells',
                'icon_bg' => 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400',
                'badge' => null,
                'badge_class' => '',
            ];
        }

        return [
            'items' => $items,
        ];
    }
}
