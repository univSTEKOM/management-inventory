<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'assets.bulk_edit_brand',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach (Permission::whereIn('name', self::PERMISSIONS)->get() as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};

