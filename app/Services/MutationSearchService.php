<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\InventoryBalance;

class MutationSearchService
{
    public static function describe(Asset|InventoryBalance $item): array
    {
        $asset = $item instanceof Asset;

        return [
            'key' => ($asset ? 'asset:' : 'supply:').$item->id,
            'name' => $item->name,
            'code' => $asset ? $item->inventory_number : $item->master_barcode,
            'barcode' => $asset ? $item->barcode : $item->master_barcode,
            'brand' => $item->brand,
            'type' => $asset ? ($item->classification?->name ?? 'Barang individual') : 'Barang Habis Pakai',
            'available' => $asset ? 1 : (int) $item->quantity,
            'pic_name' => $asset ? ($item->pic?->name ?? null) : null,
        ];
    }

    public static function search(int $campus, int $location, string $search): array
    {
        $search = trim($search);
        if ($search === '' || ! $campus || ! $location) {
            return [];
        }
        $term = '%'.$search.'%';
        $assets = Asset::with(['classification', 'pic'])
            ->where('campus_id', $campus)->where('location_id', $location)
            ->whereIn('status', MutationValidationService::ELIGIBLE_STATUSES)
            ->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('inventory_number', 'like', $term)->orWhere('barcode', 'like', $term))
            ->orderBy('name')->orderBy('id')->limit(30)->get();
        $supplies = InventoryBalance::where('campus_id', $campus)->where('location_id', $location)
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('master_barcode', 'like', $term))
            ->orderBy('name')->orderBy('id')->limit(30)->get();

        return $assets->concat($supplies)->map(fn ($item) => self::describe($item))->all();
    }
}
