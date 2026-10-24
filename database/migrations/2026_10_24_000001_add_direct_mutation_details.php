<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mutations', function (Blueprint $table) {
            $table->uuid('request_token')->nullable()->unique();
        });
        Schema::table('mutation_items', function (Blueprint $table) {
            $table->json('snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mutation_items', fn (Blueprint $table) => $table->dropColumn('snapshot'));
        Schema::table('mutations', function (Blueprint $table) {
            $table->dropUnique(['request_token']);
            $table->dropColumn('request_token');
        });
    }
};
