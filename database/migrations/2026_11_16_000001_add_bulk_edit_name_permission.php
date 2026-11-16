<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSION = 'assets.bulk_edit_name';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION]);

        foreach (Role::whereIn('name', ['Tim Inventaris'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        foreach (Permission::where('name', self::PERMISSION)->get() as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
