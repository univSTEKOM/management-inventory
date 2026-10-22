<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Inventory\Resources\UnifiedItemResource\Pages\ListUnifiedItems;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class AssetImportFileDiskTest extends TestCase
{
    public static function importPages(): array
    {
        return [
            'assets' => [ListAssets::class],
            'unified items' => [ListUnifiedItems::class],
        ];
    }

    #[DataProvider('importPages')]
    public function test_import_reads_local_upload_when_default_disk_is_s3(string $pageClass): void
    {
        // Isolate both disks: no real S3 requests or database writes.
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        Storage::fake('local');

        $filename = '01M5GS6RAW2MKRQ7C2V1N3R2EE.xlsx';
        $spreadsheet = new Spreadsheet();
        // Deliberately invalid headers stop the action after parsing, before import.
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Invalid header');
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($filename));
        $spreadsheet->disconnectWorksheets();

        Storage::disk('s3')->assertMissing($filename);

        $page = new $pageClass();
        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        $group = $method->invoke($page)[0];
        $action = $group->getFlatActions()['importAsset'];
        $callback = $action->getActionFunction();
        $callback(['import_file' => $filename], $page);

        // Header validation proves the uploaded workbook was found and parsed.
        Notification::assertNotified('Header file tidak valid');
        Notification::assertNotNotified('Gagal membaca file');
    }
}
