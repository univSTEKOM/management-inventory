<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'assets.bulk_change_category' => [],
        'assets.bulk_change_kondisi' => [],
        'assets.bulk_change_status' => [],
        'assets.bulk_move_location' => [],
        'assets.bulk_print_checklist' => ['Tim Inventaris'],
        'assets.bulk_print_barcode' => ['Tim Inventaris'],
        'assets.bulk_edit_brand' => ['Tim Inventaris'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name]);

            if (! empty($roles)) {
                foreach (Role::whereIn('name', $roles)->get() as $role) {
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (Permission::whereIn('name', array_keys(self::PERMISSIONS))->get() as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
