<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\User;
use App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\FakesPhotoStorage;
use Tests\TestCase;

class AssetBulkAddPhotoTest extends TestCase
{
    use FakesPhotoStorage;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $role = \App\Models\Role::firstOrCreate(['name' => 'superadmin']);
        
        $user = User::factory()->create();
        $user->roles()->attach([$role->id]);
        $this->actingAs($user);
        
        $this->fakePhotoDisks();
    }

    public function test_bulk_add_photo_appends_to_existing_photos()
    {
        $asset1 = Asset::factory()->create();
        $asset2 = Asset::factory()->create();

        // Asset 1 has 1 photo already
        $asset1->photos()->create(['file_path' => 'old1.jpg', 'sort_order' => 0]);
        // Asset 2 has no photos

        $file1 = $this->putTestImage('asset-photos/new_photo_1.png');

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('addPhotoBulk', [
                $asset1->id,
                $asset2->id,
            ], data: [
                'photo' => [$file1],
                // Menambah ke barang yang sudah punya foto harus dipilih eksplisit.
                'existing_mode' => \App\Services\ItemPhotoService::MODE_ADD,
                'confirmed' => true,
            ])
            ->assertSuccessful();

        $this->assertEquals(2, $asset1->photos()->count());
        $this->assertEquals(1, $asset2->photos()->count());

        $this->assertDatabaseHas('asset_photos', ['asset_id' => $asset1->id, 'file_path' => $file1, 'sort_order' => 1]);
        $this->assertDatabaseHas('asset_photos', ['asset_id' => $asset2->id, 'file_path' => $file1, 'sort_order' => 0]);
    }
}