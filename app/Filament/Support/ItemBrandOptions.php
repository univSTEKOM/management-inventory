<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Classification;
use App\Models\InventoryBalance;

class ItemBrandOptions
{
    public static function forCategory(int|string|null $categoryId, int|string|null $classificationId): array
    {
        if (! $categoryId || ! $classificationId) {
            return [];
        }

        $isSupply = Classification::whereKey($classificationId)->value('slug') === 'barang-habis-pakai';
        $query = $isSupply
            ? InventoryBalance::query()
            : Asset::where('classification_id', $classificationId);

        return $query->where('category_id', $categoryId)
            ->whereNotNull('brand')->where('brand', '<>', '')
            ->distinct()->orderBy('brand')->pluck('brand', 'brand')->all();
    }
}
