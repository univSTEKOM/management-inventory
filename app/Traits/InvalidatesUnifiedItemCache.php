<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait InvalidatesUnifiedItemCache
{
    public static function bootInvalidatesUnifiedItemCache()
    {
        static::saved(fn () => static::bumpUnifiedItemCacheVersion());

        static::deleted(fn () => static::bumpUnifiedItemCacheVersion());

        if (method_exists(static::class, 'restored')) {
            static::restored(fn () => static::bumpUnifiedItemCacheVersion());
        }
    }

    protected static function bumpUnifiedItemCacheVersion(): void
    {
        // UnifiedItemBuilder membaca versi dengan default 1. Increment pada key
        // yang belum ada menghasilkan 1 juga, sehingga perubahan pertama tidak
        // meng-invalidate cache. Seed dulu ke 1 baru increment (=> 2).
        Cache::add('unified_items_version', 1);
        Cache::increment('unified_items_version');
    }
}
