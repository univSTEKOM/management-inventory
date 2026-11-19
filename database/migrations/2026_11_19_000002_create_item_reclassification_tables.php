<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reclassifications', function (Blueprint $table) {
            $table->id();
            // Token idempoten: submit ganda dengan token yang sama tidak membuat transaksi kedua.
            $table->uuid('request_token')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // asset_inventory | to_supply | from_supply
            $table->string('type', 30)->index();
            $table->foreignId('source_classification_id')->constrained('classifications')->restrictOnDelete();
            $table->foreignId('destination_classification_id')->constrained('classifications')->restrictOnDelete();
            $table->foreignId('destination_campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedInteger('total_quantity')->default(0);
            // Null bila tidak ada satu pun item yang punya harga perolehan.
            $table->decimal('total_value', 18, 2)->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('item_reclassification_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_reclassification_id')->constrained('item_reclassifications')->cascadeOnDelete();
            // asset | inventory_balance
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('destination_type', 30);
            $table->unsignedBigInteger('destination_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->foreignId('source_purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->foreignId('destination_purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'irl_source_idx');
            $table->index(['destination_type', 'destination_id'], 'irl_destination_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('item_reclassifications')->exists()) {
            throw new \RuntimeException('Riwayat reklasifikasi yang sudah terisi tidak dapat dihapus lewat rollback.');
        }

        Schema::dropIfExists('item_reclassification_lines');
        Schema::dropIfExists('item_reclassifications');
    }
};
