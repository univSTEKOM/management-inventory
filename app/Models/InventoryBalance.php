<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBalance extends Model
{
    use \App\Traits\InvalidatesUnifiedItemCache;
    protected $guarded = ['id'];

    /** Panjang kolom `unit`; dibatasi agar index unik gabungan muat di InnoDB. */
    public const UNIT_MAX_LENGTH = 250;

    /**
     * Normalisasi satuan untuk identitas stok: trim, kosong => null, dipotong
     * sesuai panjang kolom. Selalu dipakai saat mencari/membuat stok.
     */
    public static function normalizeUnit(?string $unit): ?string
    {
        $unit = trim((string) $unit);

        return $unit === '' ? null : mb_substr($unit, 0, self::UNIT_MAX_LENGTH);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function pic(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Employee::class, 'pic_id'); }
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryBalanceUnit::class);
    }
}
