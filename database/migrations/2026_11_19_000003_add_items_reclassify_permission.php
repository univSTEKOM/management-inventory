<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSION = 'items.reclassify';

    public function up(): void
    {
        // Tidak diberikan ke role mana pun secara default; Superadmin lolos otomatis.
        Permission::firstOrCreate(['name' => self::PERMISSION]);
    }

    public function down(): void
    {
        foreach (Permission::where('name', self::PERMISSION)->get() as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
