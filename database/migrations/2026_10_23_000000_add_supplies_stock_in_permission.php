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
        $permission = Permission::firstOrCreate(['name' => 'supplies.stock_in']);

        $roles = Role::whereIn('name', ['Tim Inventaris', 'Finance'])->get();
        foreach ($roles as $role) {
            if (! $role->permissions()->where('permission_id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'supplies.stock_in')->first();
        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
