<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'reports.rekapitulasi.view' => ['Tim Inventaris', 'Finance', 'Approver'],
        'intake.view' => ['Tim Inventaris', 'Finance'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name]);

            foreach (Role::whereIn('name', $roles)->get() as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
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
