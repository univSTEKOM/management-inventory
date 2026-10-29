<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom `type` ke pivot table `permission_user`
 * untuk mendukung mekanisme override per-akun:
 *  - `allow`   : izin eksplisit (mengalahkan role yang tidak punya)
 *  - `deny`    : tolak eksplisit (mengalahkan role yang punya izin)
 *  - `inherit` : ikuti role (sama dengan tidak ada record, cadangan)
 *
 * Record lama (sebelum migration ini) dianggap `allow` secara default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_user', function (Blueprint $table) {
            // Tambah kolom type, default 'allow' untuk backward-compat
            $table->string('type', 10)->default('allow')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('permission_user', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

