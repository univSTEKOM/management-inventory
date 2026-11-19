<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan menjadi bagian identitas stok BHP: (kategori, nama, merk, satuan, ruangan).
 *
 * - Kolom dibuat nullable dan hanya 250 karakter supaya index unik gabungan
 *   tetap di bawah batas 3072 byte InnoDB (utf8mb4).
 * - Backfill aman: dari purchase_items.unit terbaru (bukan soft-deleted) tiap
 *   stok. Tidak ada data yang dihapus/diubah selain kolom baru.
 * - Index unik baru dibuat SEBELUM index lama dibuang, dan index baru lebih
 *   longgar, jadi tidak mungkin terjadi duplikat saat migrasi.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'inv_bal_unique_with_brand';

    private const NEW_INDEX = 'inv_bal_unique_with_unit';

    public function up(): void
    {
        if (! Schema::hasColumn('inventory_balances', 'unit')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->string('unit', 250)->nullable()->after('brand');
            });
        }

        DB::table('inventory_balances')->whereNull('unit')->orderBy('id')->chunkById(200, function ($balances) {
            foreach ($balances as $balance) {
                $unit = DB::table('purchase_items')
                    ->where('inventory_balance_id', $balance->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('unit')
                    ->where('unit', '<>', '')
                    ->orderByDesc('id')
                    ->value('unit');

                $unit = trim((string) $unit);
                if ($unit !== '') {
                    DB::table('inventory_balances')
                        ->where('id', $balance->id)
                        ->update(['unit' => mb_substr($unit, 0, 250)]);
                }
            }
        });

        if (! Schema::hasIndex('inventory_balances', self::NEW_INDEX)) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->unique(['category_id', 'name', 'brand', 'unit', 'location_id'], self::NEW_INDEX);
            });
        }

        foreach ([self::OLD_INDEX, 'inventory_balances_category_id_name_location_id_unique'] as $index) {
            if (Schema::hasIndex('inventory_balances', $index)) {
                Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }
    }

    public function down(): void
    {
        if (DB::table('inventory_balances')->selectRaw('COUNT(*)')
            ->groupBy('category_id', 'name', 'brand', 'location_id')
            ->havingRaw("COUNT(DISTINCT COALESCE(unit, '')) > 1")->exists()) {
            throw new \RuntimeException('Stok dengan satuan berbeda mencegah rollback migrasi satuan BHP.');
        }

        if (! Schema::hasIndex('inventory_balances', self::OLD_INDEX)) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->unique(['category_id', 'name', 'brand', 'location_id'], self::OLD_INDEX);
            });
        }

        if (Schema::hasIndex('inventory_balances', self::NEW_INDEX)) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        if (Schema::hasColumn('inventory_balances', 'unit')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }
};
