<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'print_jobs.view'   => ['Superadmin', 'superadmin', 'Tim Inventaris'],
        'print_jobs.retry'  => ['Superadmin', 'superadmin', 'Tim Inventaris'],
        'print_jobs.delete' => ['Superadmin', 'superadmin'],
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

