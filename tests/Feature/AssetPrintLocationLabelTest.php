<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetPrintLocationLabelTest extends TestCase
{
    public static function labelCases(): array
    {
        return [
            'building code' => ['GD01', 'Gedung Utama', 'R101', 'GD01 - R101'],
            'trim code' => [' GD01 ', 'Gedung Utama', 'R101', 'GD01 - R101'],
            'missing code' => [null, 'Gedung Utama', 'R101', 'Gedung Utama - R101'],
            'empty code' => ['', 'Gedung Utama', 'R101', 'Gedung Utama - R101'],
            'blank code' => ['   ', 'Gedung Utama', 'R101', 'Gedung Utama - R101'],
            'zero code' => ['0', 'Gedung Utama', 'R101', '0 - R101'],
            'no room' => ['GD01', 'Gedung Utama', null, 'GD01'],
            'no building' => [null, null, 'R101', 'R101'],
            'no location' => [null, null, null, ''],
        ];
    }

    #[DataProvider('labelCases')]
    public function test_print_label_uses_building_code_with_name_fallback(
        ?string $code,
        ?string $building,
        ?string $room,
        string $expected,
    ): void {
        // Set relations in memory so this test does not read or write the database.
        $asset = new Asset();
        $asset->setRelation('campus', $building === null ? null : new Campus([
            'name' => $building,
            'code' => $code,
        ]));
        $asset->setRelation('location', $room === null ? null : new Location(['name' => $room]));

        $this->assertSame($expected, $asset->printLocationLabel());
    }
}
