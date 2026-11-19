<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak asal-hasil satu barang/batch pada sebuah reklasifikasi.
 * source_type / destination_type: `asset` (tabel assets) atau
 * `inventory_balance` (tabel inventory_balances).
 */
class ItemReclassificationLine extends Model
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_BALANCE = 'inventory_balance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
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

    public function reclassification(): BelongsTo
    {
        return $this->belongsTo(ItemReclassification::class, 'item_reclassification_id');
    }

    public function sourcePurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'source_purchase_item_id');
    }

    public function destinationPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'destination_purchase_item_id');
    }
}
