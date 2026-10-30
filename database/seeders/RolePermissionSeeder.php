<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Define all permissions exactly as required
        $permissions = [
            // Asset
            'assets.view', 'assets.create', 'assets.update', 'assets.delete',
            'assets.widget.total_items', 'assets.widget.acquisition_value',
            // Supplies (Barang Habis Pakai)
            'supplies.stock_in', 'supplies.update',
            // Intake (Antrian Barang Masuk — Finance "Lengkapi Data")
            'intake.complete',
            // Master Data
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'campuses.view', 'campuses.create', 'campuses.update', 'campuses.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            // Movement
            'movements.view', 'movements.create', 'movements.approve', 'movements.reject', 'movements.complete',
            // Status
            'status.view', 'status.update', 'status.approve',
            // Stock Opname
            'stock_opname.view', 'stock_opname.create', 'stock_opname.update', 'stock_opname.complete',
            // QR / Barcode
            'asset_qr.view', 'asset_qr.generate', 'asset_scanner.use',
            // Financial
            'financial.view', 'financial.update', 'financial.export',
            // Reports
            'reports.view', 'reports.export',
            // Audit
            'audit.view', 'audit.export',
            // User Administration
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.manage',
            // Panel Access
            'panel.admin', 'panel.inventory',
            // Dashboard & Widget Visibility
            'dashboard.view',
            'dashboard.widget.total_assets',
            'dashboard.widget.total_categories',
            'dashboard.widget.total_locations',
            'dashboard.widget.total_employees',
            'dashboard.widget.pending_mutations',
            'dashboard.widget.stock_opname_active',
            'dashboard.widget.asset_status_chart',
            'dashboard.widget.latest_movements',
            'dashboard.widget.asset_acquisition_value',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define Roles and their specific permissions
        $roles = [
            'Superadmin' => [], // Bypass via User model

            'Tim Inventaris' => [
                'panel.inventory',
                'assets.widget.total_items',
                'assets.view', 'assets.create', 'assets.update',
                'supplies.stock_in', 'supplies.update',
                'categories.view', 'categories.create', 'categories.update',
                'campuses.view', 'campuses.create', 'campuses.update',
                'locations.view', 'locations.create', 'locations.update',
                'employees.view', 'employees.create', 'employees.update',
                'movements.view', 'movements.create',
                'status.view', 'status.update',
                'stock_opname.view', 'stock_opname.create', 'stock_opname.update', 'stock_opname.complete',
                'asset_qr.view', 'asset_qr.generate', 'asset_scanner.use',
                'reports.view', 'reports.export',
                'audit.view',
                // Dashboard permissions
                'dashboard.view',
                'dashboard.widget.total_assets',
                'dashboard.widget.total_categories',
                'dashboard.widget.total_locations',
                'dashboard.widget.total_employees',
                'dashboard.widget.pending_mutations',
                'dashboard.widget.stock_opname_active',
                'dashboard.widget.asset_status_chart',
                'dashboard.widget.latest_movements',
            ],

            'Finance' => [
                'panel.inventory',
                'assets.widget.total_items',
                'assets.view',
                'supplies.stock_in',
                'intake.complete',
                'assets.widget.acquisition_value',
                'financial.view', 'financial.export',
                'reports.view', 'reports.export',
                // Dashboard permissions (Finance melihat finansial)
                'dashboard.view',
                'dashboard.widget.total_assets',
                'dashboard.widget.asset_acquisition_value',
            ],

            'Approver' => [
                'panel.inventory',
                'assets.widget.total_items',
                'assets.view',
                'movements.view', 'movements.approve', 'movements.reject', 'movements.complete',
                'status.view', 'status.approve',
                'reports.view',
                // Dashboard permissions
                'dashboard.view',
                'dashboard.widget.total_assets',
                'dashboard.widget.pending_mutations',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if (! empty($rolePermissions)) {
                $permissionIds = Permission::whereIn('name', $rolePermissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }

        // Assign Superadmin role to existing Superadmin user (assumed first user)
        $user = User::first();
        if ($user) {
            $superadminRole = Role::where('name', 'Superadmin')->first();
            if (! $user->roles()->where('role_id', $superadminRole->id)->exists()) {
                $user->roles()->attach($superadminRole->id);
            }
        }
    }
}
