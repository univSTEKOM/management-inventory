<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'supplies.update']);

        $role = Role::where('name', 'Tim Inventaris')->first();
        if ($role && ! $role->permissions()->where('permission_id', $permission->id)->exists()) {
            $role->permissions()->attach($permission->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'supplies.update')->first();
        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
