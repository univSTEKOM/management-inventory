<?php

namespace Tests\Feature;

use App\Filament\Inventory\Pages\AssetScanner;
use App\Filament\Inventory\Pages\ItemRecapitulation;
use App\Filament\Inventory\Pages\TransaksiPembelian;
use App\Filament\Inventory\Pages\TransaksiPenjualan;
use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use App\Filament\Inventory\Resources\AssetMovementResource;
use App\Filament\Inventory\Resources\AssetResource;
use App\Filament\Inventory\Resources\BaseCategoryAssetResource;
use App\Filament\Inventory\Resources\InventoryBalanceResource;
use App\Filament\Inventory\Resources\MutationResource;
use App\Filament\Inventory\Resources\StockOpnameResource;
use App\Filament\Inventory\Resources\UnifiedItemResource;
use App\Filament\Inventory\Widgets\AssetStatusChart;
use App\Filament\Inventory\Widgets\DashboardStatsOverview as InventoryDashboardStatsOverview;
use App\Filament\Inventory\Widgets\LatestAssetMovements;
use App\Filament\Widgets\DashboardStatsOverview as AdminDashboardStatsOverview;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class PermissionSystemTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * 16.1. Role dan permission:
     * Role dapat memiliki beberapa permission dan diwariskan ke user.
     */
    public function test_user_inherits_permissions_from_assigned_roles()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermissionTo('assets.view'));
        $this->assertTrue($user->hasPermissionTo('stock_opname.view'));
        $this->assertTrue($user->hasPermissionTo('dashboard.view'));
        $this->assertFalse($user->hasPermissionTo('users.delete'));
    }

    /**
     * 16.1. Resolusi Override:
     * Permission 'deny' pada akun mengalahkan izin yang diwariskan dari role.
     */
    public function test_user_deny_override_overrules_role_permission()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        // Sebelum override: user punya assets.view
        $this->assertTrue($user->hasPermissionTo('assets.view'));

        // Berikan override 'deny' untuk assets.view pada user ini
        $permission = Permission::where('name', 'assets.view')->firstOrFail();
        $user->permissions()->attach($permission->id, ['type' => 'deny']);

        // Sekarang assets.view DITOLAK meskipun role Tim Inventaris mengizinkannya
        $this->assertFalse($user->fresh()->hasPermissionTo('assets.view'));
        // Permission lain dari role tetap utuh
        $this->assertTrue($user->fresh()->hasPermissionTo('stock_opname.view'));
    }

    /**
     * 16.1. Resolusi Override:
     * Permission 'allow' pada akun memberikan izin khusus meskipun role tidak memilikinya.
     */
    public function test_user_allow_override_grants_permission_not_in_role()
    {
        $role = Role::where('name', 'Finance')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        // Finance bawaan tidak punya permission users.view
        $this->assertFalse($user->hasPermissionTo('users.view'));

        // Berikan override 'allow' khusus akun ini
        $permission = Permission::where('name', 'users.view')->firstOrFail();
        $user->permissions()->attach($permission->id, ['type' => 'allow']);

        // Akun ini sekarang bisa melihat users
        $this->assertTrue($user->fresh()->hasPermissionTo('users.view'));
        // User lain di role Finance tetap tidak bisa
        $otherUser = User::factory()->create();
        $otherUser->roles()->attach($role->id);
        $this->assertFalse($otherUser->hasPermissionTo('users.view'));
    }

    /**
     * 16.1. Resolusi Override:
     * Permission 'inherit' mengikuti hasil role.
     */
    public function test_user_inherit_override_follows_role()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $permission = Permission::where('name', 'assets.view')->firstOrFail();
        $user->permissions()->attach($permission->id, ['type' => 'inherit']);

        // Karena role punya assets.view dan override-nya inherit, hasilnya tetap true
        $this->assertTrue($user->fresh()->hasPermissionTo('assets.view'));
    }

    /**
     * 16.1. Akun tanpa role dan tanpa override tidak mendapatkan akses.
     */
    public function test_user_without_roles_or_permissions_has_no_access()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermissionTo('assets.view'));
        $this->assertFalse($user->hasPermissionTo('dashboard.view'));
        $this->assertFalse($user->hasPermissionTo('financial.view'));
    }

    /**
     * 16.4. Superadmin selalu memiliki akses penuh (bypass).
     */
    public function test_superadmin_has_full_bypass_access()
    {
        $superadminRole = Role::where('name', 'Superadmin')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($superadminRole->id);

        $this->assertTrue($user->hasPermissionTo('assets.view'));
        $this->assertTrue($user->hasPermissionTo('financial.view'));
        $this->assertTrue($user->hasPermissionTo('non_existent.permission'));
        $this->assertEquals('superadmin', $user->effectivePermissionStatus('assets.view'));
    }

    /**
     * 16.2 & 16.3. Visibilitas Resource dan Backend Authorization:
     * canAccess() menolak akses jika permission tidak dimiliki.
     */
    public function test_resource_can_access_enforces_permissions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Awalnya ditolak
        $this->assertFalse(AssetResource::canAccess());
        $this->assertFalse(UnifiedItemResource::canAccess());
        $this->assertFalse(MutationResource::canAccess());
        $this->assertFalse(StockOpnameResource::canAccess());
        $this->assertFalse(InventoryBalanceResource::canAccess());

        // Berikan assets.view
        $perm = Permission::where('name', 'assets.view')->firstOrFail();
        $user->permissions()->attach($perm->id, ['type' => 'allow']);

        $this->assertTrue(AssetResource::canAccess());
        $this->assertTrue(UnifiedItemResource::canAccess());
        $this->assertTrue(InventoryBalanceResource::canAccess());
        $this->assertFalse(MutationResource::canAccess());

        // Berikan movements.view
        $permMove = Permission::where('name', 'movements.view')->firstOrFail();
        $user->permissions()->attach($permMove->id, ['type' => 'allow']);
        $this->assertTrue(MutationResource::canAccess());
        $this->assertTrue(AssetMovementResource::canAccess());
    }

    /**
     * 16.2. Visibilitas Widget Dashboard:
     * Widget dashboard canView() hanya aktif jika permission widget dimiliki.
     */
    public function test_dashboard_widgets_visibility_enforcement()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Tanpa permission, widget dashboard tidak dirender
        $this->assertFalse(InventoryDashboardStatsOverview::canView());
        $this->assertFalse(AdminDashboardStatsOverview::canView());
        $this->assertFalse(AssetStatusChart::canView());
        $this->assertFalse(LatestAssetMovements::canView());

        // Berikan dashboard.view
        $pDashboard = Permission::where('name', 'dashboard.view')->firstOrFail();
        $user->permissions()->attach($pDashboard->id, ['type' => 'allow']);

        $this->assertTrue(InventoryDashboardStatsOverview::canView());
        $this->assertTrue(AdminDashboardStatsOverview::canView());

        // Chart dan Movements tetap false karena punya permission widget terpisah
        $this->assertFalse(AssetStatusChart::canView());
        $this->assertFalse(LatestAssetMovements::canView());

        // Berikan permission chart
        $pChart = Permission::where('name', 'dashboard.widget.asset_status_chart')->firstOrFail();
        $user->permissions()->attach($pChart->id, ['type' => 'allow']);
        $this->assertTrue(AssetStatusChart::canView());
    }

    /**
     * 16.2. Card / Stat Dashboard dapat disembunyikan secara independen:
     * User A dan User B memiliki visibilitas card berbeda.
     */
    public function test_dashboard_stats_can_be_hidden_independently()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();

        // User A: default Tim Inventaris (melihat total_assets)
        $userA = User::factory()->create();
        $userA->roles()->attach($role->id);

        // User B: Tim Inventaris tapi dengan override deny pada total_assets
        $userB = User::factory()->create();
        $userB->roles()->attach($role->id);
        $pTotalAssets = Permission::where('name', 'dashboard.widget.total_assets')->firstOrFail();
        $userB->permissions()->attach($pTotalAssets->id, ['type' => 'deny']);

        $this->assertTrue($userA->hasPermissionTo('dashboard.widget.total_assets'));
        $this->assertFalse($userB->fresh()->hasPermissionTo('dashboard.widget.total_assets'));

        // Kedua user tetap bisa melihat permission lain
        $this->assertTrue($userA->hasPermissionTo('dashboard.widget.total_locations'));
        $this->assertTrue($userB->fresh()->hasPermissionTo('dashboard.widget.total_locations'));
    }

    /**
     * 16.3. Perlindungan data finansial:
     * User tanpa financial.view tidak dapat melihat data harga/biaya.
     */
    public function test_financial_permission_protection()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        // Tim Inventaris bawaan tidak punya financial.view
        $this->assertFalse($user->hasPermissionTo('financial.view'));

        // Finance memiliki financial.view
        $financeRole = Role::where('name', 'Finance')->firstOrFail();
        $financeUser = User::factory()->create();
        $financeUser->roles()->attach($financeRole->id);

        $this->assertTrue($financeUser->hasPermissionTo('financial.view'));
    }

    /**
     * 16.4. Method helper effectivePermissionStatus mengembalikan status akurat.
     */
    public function test_effective_permission_status_helper()
    {
        $role = Role::where('name', 'Tim Inventaris')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        // Status via role
        $this->assertEquals('role', $user->effectivePermissionStatus('assets.view'));

        // Status none
        $this->assertEquals('none', $user->effectivePermissionStatus('users.view'));

        // Override allow
        $pUsers = Permission::where('name', 'users.view')->firstOrFail();
        $user->permissions()->attach($pUsers->id, ['type' => 'allow']);
        $this->assertEquals('allow', $user->fresh()->effectivePermissionStatus('users.view'));

        // Override deny
        $pAssets = Permission::where('name', 'assets.view')->firstOrFail();
        $user->permissions()->attach($pAssets->id, ['type' => 'deny']);
        $this->assertEquals('deny', $user->fresh()->effectivePermissionStatus('assets.view'));
    }
    public function test_category_cards_follow_independent_permissions_and_financial_access()
    {
        $role = Role::where('name', 'Finance')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $widgetClass = \App\Filament\Inventory\Resources\Widgets\CategoryAssetStatsWidget::class;
        $method = new \ReflectionMethod($widgetClass, 'getStats');

        // Dashboard overrides must not affect cards in the category menus.
        foreach (['dashboard.widget.total_assets', 'dashboard.widget.asset_acquisition_value'] as $name) {
            $permission = Permission::where('name', $name)->firstOrFail();
            $user->permissions()->attach($permission->id, ['type' => 'deny']);
        }

        $cases = [
            [true, true, true, ['Total Barang', 'Total Harga Perolehan']],
            [false, true, true, ['Total Harga Perolehan']],
            [true, false, true, ['Total Barang']],
            [false, false, true, []],
            [true, true, false, ['Total Barang']],
            [false, true, false, []],
        ];

        foreach ($cases as [$items, $price, $financial, $expected]) {
            foreach ([
                'assets.widget.total_items' => $items,
                'assets.widget.acquisition_value' => $price,
                'financial.view' => $financial,
            ] as $name => $allowed) {
                $permission = Permission::where('name', $name)->firstOrFail();
                $user->permissions()->syncWithoutDetaching([
                    $permission->id => ['type' => $allowed ? 'allow' : 'deny'],
                ]);
            }

            $this->actingAs($user->fresh());
            $this->assertSame($expected !== [], $widgetClass::canView());

            foreach (['aset', 'inventaris'] as $slug) {
                $widget = new $widgetClass;
                $widget->categorySlug = $slug;
                $stats = $method->invoke($widget);
                $this->assertSame($expected, array_map(fn ($stat) => $stat->getLabel(), $stats));
            }
        }
    }

    public function test_category_cards_are_hidden_for_guests_and_users_without_permissions()
    {
        $widgetClass = \App\Filament\Inventory\Resources\Widgets\CategoryAssetStatsWidget::class;
        $method = new \ReflectionMethod($widgetClass, 'getStats');

        $this->assertFalse($widgetClass::canView());
        $this->assertSame([], $method->invoke(new $widgetClass));

        $this->actingAs(User::factory()->create());
        $this->assertFalse($widgetClass::canView());
        $this->assertSame([], $method->invoke(new $widgetClass));
    }

    public function test_transaksi_pages_can_access_enforces_permissions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Awalnya ditolak (tidak ada permission)
        $this->assertFalse(TransaksiPembelian::canAccess());
        $this->assertFalse(TransaksiPenjualan::canAccess());

        // Berikan transactions.pembelian.view
        $permPembelian = Permission::where('name', 'transactions.pembelian.view')->firstOrFail();
        $user->permissions()->attach($permPembelian->id, ['type' => 'allow']);

        $this->assertTrue(TransaksiPembelian::canAccess());
        $this->assertFalse(TransaksiPenjualan::canAccess());

        // Berikan transactions.penjualan.view
        $permPenjualan = Permission::where('name', 'transactions.penjualan.view')->firstOrFail();
        $user->permissions()->attach($permPenjualan->id, ['type' => 'allow']);

        $this->assertTrue(TransaksiPembelian::canAccess());
        $this->assertTrue(TransaksiPenjualan::canAccess());
    }

    public function test_rekapitulasi_and_intake_pages_can_access_enforces_permissions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Awalnya ditolak
        $this->assertFalse(ItemRecapitulation::canAccess());
        $this->assertFalse(AntrianBarangMasukResource::canAccess());
        $this->assertFalse(AssetScanner::canAccess());

        // Berikan reports.rekapitulasi.view
        $permRecap = Permission::where('name', 'reports.rekapitulasi.view')->firstOrFail();
        $user->permissions()->attach($permRecap->id, ['type' => 'allow']);
        $this->assertTrue(ItemRecapitulation::canAccess());

        // Berikan intake.view
        $permIntake = Permission::where('name', 'intake.view')->firstOrFail();
        $user->permissions()->attach($permIntake->id, ['type' => 'allow']);
        $this->assertTrue(AntrianBarangMasukResource::canAccess());

        // Berikan asset_scanner.use
        $permScanner = Permission::where('name', 'asset_scanner.use')->firstOrFail();
        $user->permissions()->attach($permScanner->id, ['type' => 'allow']);
        $this->assertTrue(AssetScanner::canAccess());
    }

    public function test_bulk_edit_brand_permission_authorization()
    {
        $superadmin = User::factory()->create();
        $superadminRole = Role::firstOrCreate(['name' => 'Superadmin']);
        $superadmin->roles()->attach($superadminRole->id);

        $regularUser = User::factory()->create();

        $this->assertTrue(\App\Filament\Support\ChangeBrandActions::userCanBulkEdit($superadmin));
        $this->assertFalse(\App\Filament\Support\ChangeBrandActions::userCanBulkEdit($regularUser));

        $perm = Permission::where('name', 'assets.bulk_edit_brand')->firstOrFail();
        $regularUser->permissions()->attach($perm->id, ['type' => 'allow']);

        $this->assertTrue(\App\Filament\Support\ChangeBrandActions::userCanBulkEdit($regularUser->fresh()));
    }
}

