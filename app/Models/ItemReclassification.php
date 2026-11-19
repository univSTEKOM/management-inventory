<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header transaksi reklasifikasi barang (Aset / Inventaris / BHP).
 * Baris detail ada di ItemReclassificationLine. Transaksi bersifat
 * append-only: tidak diubah/dihapus setelah dibuat.
 */
class ItemReclassification extends Model
{
    public const TYPE_ASSET_INVENTORY = 'asset_inventory';

    public const TYPE_TO_SUPPLY = 'to_supply';

    public const TYPE_FROM_SUPPLY = 'from_supply';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'total_value' => 'decimal:2',
            'total_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Riwayat reklasifikasi tidak dapat diubah.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Riwayat reklasifikasi tidak dapat dihapus.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceClassification(): BelongsTo
    {
        return $this->belongsTo(Classification::class, 'source_classification_id');
    }

    public function destinationClassification(): BelongsTo
    {
        return $this->belongsTo(Classification::class, 'destination_classification_id');
    }

    public function destinationCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'destination_campus_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ItemReclassificationLine::class);
    }
}
