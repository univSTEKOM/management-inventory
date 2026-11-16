<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Support\ChangeNameActions;
use App\Models\Asset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkChangeNameTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Superadmin'])->id);

        return $user;
    }

    public function test_superadmin_can_bulk_rename_assets(): void
    {
        $assets = Asset::factory()->count(3)->create(['name' => 'Lama']);

        $this->actingAs($this->superadmin());

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('changeNameBulk', $assets, ['name' => '  Laptop Baru  '])
            ->assertHasNoTableBulkActionErrors();

        foreach ($assets as $asset) {
            $this->assertSame('Laptop Baru', $asset->fresh()->name);
        }
    }

    public function test_name_is_required_and_max_255(): void
    {
        $assets = Asset::factory()->count(2)->create(['name' => 'Lama']);

        $this->actingAs($this->superadmin());

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('changeNameBulk', $assets, ['name' => ''])
            ->assertHasTableBulkActionErrors(['name' => 'required']);

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('changeNameBulk', $assets, ['name' => str_repeat('a', 256)])
            ->assertHasTableBulkActionErrors(['name' => 'max']);

        foreach ($assets as $asset) {
            $this->assertSame('Lama', $asset->fresh()->name);
        }
    }

    public function test_permission_authorization(): void
    {
        $user = User::factory()->create();
        $this->assertFalse(ChangeNameActions::userCanBulkEditName($user));

        $perm = Permission::firstOrCreate(['name' => 'assets.bulk_edit_name']);
        $user->permissions()->attach($perm->id, ['type' => 'allow']);

        $this->assertTrue(ChangeNameActions::userCanBulkEditName($user->fresh()));
    }
}
