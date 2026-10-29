<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Menambahkan permission granular untuk visibilitas widget dashboard
 * dan komponen UI. Permission ini digunakan untuk mengontrol apakah
 * suatu widget atau komponen dirender untuk pengguna tertentu.
 *
 * Migration ini aman dijalankan berulang kali (idempotent via firstOrCreate).
 */
return new class extends Migration
{
    /**
     * Permission baru yang ditambahkan dalam migration ini.
     * Format: {group}.{action}
     */
    private const NEW_PERMISSIONS = [
        // Dashboard global access
        'dashboard.view',

        // Widget spesifik — admin panel
        'dashboard.widget.total_assets',
        'dashboard.widget.total_categories',
        'dashboard.widget.total_locations',
        'dashboard.widget.total_employees',

        // Widget spesifik — inventory panel
        'dashboard.widget.pending_mutations',
        'dashboard.widget.stock_opname_active',
        'dashboard.widget.asset_status_chart',
        'dashboard.widget.latest_movements',

        // Nilai finansial aset (card & kolom)
        'dashboard.widget.asset_acquisition_value',
    ];

    /**
     * Role yang mendapatkan semua permission dashboard baru secara default.
     */
    private const ROLES_WITH_FULL_DASHBOARD = ['Tim Inventaris'];

    /**
     * Role yang mendapatkan permission dashboard terbatas.
     */
    private const ROLES_FINANCE_DASHBOARD = ['Finance'];

    public function up(): void
    {
        // Buat semua permission baru (idempotent)
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // Tim Inventaris mendapat semua permission dashboard kecuali acquisition_value
        $timInventarisPermissions = collect(self::NEW_PERMISSIONS)
            ->reject(fn (string $p): bool => $p === 'dashboard.widget.asset_acquisition_value')
            ->all();

        $timInventarisRole = Role::where('name', 'Tim Inventaris')->first();
        if ($timInventarisRole) {
            $ids = Permission::whereIn('name', $timInventarisPermissions)->pluck('id');
            // Sync hanya tambah, tidak cabut yang sudah ada
            $timInventarisRole->permissions()->syncWithoutDetaching($ids);
        }

        // Finance mendapat dashboard.view + widget finansial
        $financePermissions = [
            'dashboard.view',
            'dashboard.widget.total_assets',
            'dashboard.widget.asset_acquisition_value',
        ];

        $financeRole = Role::where('name', 'Finance')->first();
        if ($financeRole) {
            $ids = Permission::whereIn('name', $financePermissions)->pluck('id');
            $financeRole->permissions()->syncWithoutDetaching($ids);
        }

        // Approver mendapat dashboard dasar
        $approverPermissions = [
            'dashboard.view',
            'dashboard.widget.total_assets',
            'dashboard.widget.pending_mutations',
        ];

        $approverRole = Role::where('name', 'Approver')->first();
        if ($approverRole) {
            $ids = Permission::whereIn('name', $approverPermissions)->pluck('id');
            $approverRole->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        // Cabut dari semua role terlebih dahulu
        $permissions = Permission::whereIn('name', self::NEW_PERMISSIONS)->get();

        foreach ($permissions as $permission) {
            $permission->roles()->detach();
            $permission->users()->detach();
            $permission->delete();
        }
    }
};

