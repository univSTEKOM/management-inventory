<?php

namespace Tests\Feature;

use App\Filament\Support\PrintBarcodeActions;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Memastikan urutan Cetak Barcode konsisten dengan Cetak Checklist:
 * orderByRaw("LOWER(COALESCE(notes, '')) ASC") -> orderBy('id'),
 * sama persis dengan route checklist.print di web.php.
 *
 * Kolom yang dipakai untuk sorting adalah "notes" (ditampilkan sebagai
 * kolom "Keterangan" di UI checklist), bukan "name" atau "inventory_number".
 */
class PrintBarcodeSortingTest extends TestCase
{
    use RefreshDatabase;

    private function resolveViaReflection($records, string $sortBy = 'notes'): \Illuminate\Support\Collection
    {
        $method = new \ReflectionMethod(PrintBarcodeActions::class, 'resolveAssets');
        $method->setAccessible(true);
        return $method->invoke(null, $records, $sortBy);
    }

    /**
     * Buat aset dengan notes yang SENGAJA terbalik dari abjad,
     * kemudian verifikasi resolveAssets() mengembalikannya urut A->Z
     * berdasarkan kolom notes (Keterangan), case-insensitive.
     */
    public function test_barcode_bulk_sorted_by_notes_asc(): void
    {
        $printer = Asset::factory()->create(['notes' => 'Printer ruang server']);
        $meja    = Asset::factory()->create(['notes' => 'Meja kerja']);
        $laptop  = Asset::factory()->create(['notes' => 'Laptop staf IT']);
        $ac      = Asset::factory()->create(['notes' => 'AC split 1PK']);

        $records = collect([$printer, $meja, $laptop, $ac]);
        $sorted  = $this->resolveViaReflection($records);

        $this->assertSame(
            ['AC split 1PK', 'Laptop staf IT', 'Meja kerja', 'Printer ruang server'],
            $sorted->pluck('notes')->values()->all(),
            'Cetak Barcode harus mengurutkan barang sama seperti Cetak Checklist (notes A->Z, case-insensitive)'
        );
    }

    /**
     * Aset dengan notes kapital harus diperlakukan case-insensitif.
     */
    public function test_barcode_bulk_sorted_case_insensitive(): void
    {
        $z = Asset::factory()->create(['notes' => 'Zebra']);
        $a = Asset::factory()->create(['notes' => 'alfa']);

        $records = collect([$z, $a]);
        $sorted  = $this->resolveViaReflection($records);

        $this->assertSame(
            ['alfa', 'Zebra'],
            $sorted->pluck('notes')->values()->all(),
            'Sorting harus case-insensitive: "alfa" < "Zebra"'
        );
    }

    /**
     * Saat notes sama, tiebreaker stabil menggunakan id (ascending).
     */
    public function test_barcode_bulk_tiebreaker_by_id(): void
    {
        $first  = Asset::factory()->create(['notes' => 'Kursi kantor']);
        $second = Asset::factory()->create(['notes' => 'Kursi kantor']);
        $third  = Asset::factory()->create(['notes' => 'Kursi kantor']);

        $sorted = $this->resolveViaReflection(collect([$third, $first, $second]));

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $sorted->pluck('id')->values()->all(),
            'Saat notes sama, urut berdasarkan id ASC (tiebreaker stabil)'
        );
    }

    /**
     * Aset tanpa notes (NULL) harus muncul paling awal karena
     * COALESCE(notes, '') menghasilkan string kosong.
     */
    public function test_null_notes_sorted_first(): void
    {
        $withNotes    = Asset::factory()->create(['notes' => 'Ada keterangan']);
        $withoutNotes = Asset::factory()->create(['notes' => null]);

        $sorted = $this->resolveViaReflection(collect([$withNotes, $withoutNotes]));

        $this->assertNull(
            $sorted->first()->notes,
            'Aset tanpa notes (NULL / Keterangan kosong) harus muncul paling awal'
        );
    }

    /**
     * Urutan resolveAssets() harus identik dengan query yang dipakai
     * route checklist.print di web.php.
     */
    public function test_barcode_sort_matches_checklist_sort(): void
    {
        $assets = collect([
            Asset::factory()->create(['notes' => 'Printer inkjet']),
            Asset::factory()->create(['notes' => null]),
            Asset::factory()->create(['notes' => 'AC split']),
            Asset::factory()->create(['notes' => 'Meja kayu']),
        ]);

        $ids = $assets->pluck('id');
        $checklistOrder = Asset::whereIn('id', $ids)
            ->orderByRaw("LOWER(COALESCE(notes, '')) ASC")
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        $barcodeOrder = $this->resolveViaReflection($assets)
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame(
            $checklistOrder,
            $barcodeOrder,
            'Urutan Cetak Barcode harus identik dengan Cetak Checklist'
        );
    }

    /**
     * queueForLocation() harus menggunakan sorting yang sama: notes ASC, lalu id.
     */
    public function test_queueForLocation_sorted_by_notes_then_id(): void
    {
        $campus   = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);

        Asset::factory()->create([
            'notes'       => 'Printer dot matrix',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'notes'       => 'AC split 2PK',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'notes'       => 'Meja rapat',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);

        $queued = Asset::where('location_id', $location->id)
            ->whereNotNull('barcode')
            ->orderByRaw("LOWER(COALESCE(notes, '')) ASC")
            ->orderBy('id')
            ->get();

        $this->assertSame(
            ['AC split 2PK', 'Meja rapat', 'Printer dot matrix'],
            $queued->pluck('notes')->values()->all()
        );
    }

    /**
     * Aset tanpa barcode harus dikeluarkan dari hasil resolveAssets().
     */
    public function test_assets_without_barcode_excluded_from_barcode_print(): void
    {
        $withBarcode    = Asset::factory()->create(['notes' => 'Laptop']);
        $withoutBarcode = Asset::factory()->create(['notes' => 'Monitor']);

        DB::table('assets')->where('id', $withoutBarcode->id)->update(['barcode' => null]);
        $withoutBarcode->refresh();

        $records = collect([$withBarcode, $withoutBarcode]);
        $result  = $this->resolveViaReflection($records);

        $this->assertCount(1, $result);
        $this->assertEquals($withBarcode->id, $result->first()->id);
    }

    /**
     * Verifikasi resolveAssets() dapat mengurutkan berdasarkan nama (A->Z, case-insensitive).
     */
    public function test_barcode_bulk_sorted_by_name_asc(): void
    {
        $printer = Asset::factory()->create(['name' => 'Printer Zebra', 'notes' => 'A']);
        $kursi   = Asset::factory()->create(['name' => 'kursi Ergonomis', 'notes' => 'Z']);
        $laptop  = Asset::factory()->create(['name' => 'Apple MacBook', 'notes' => 'M']);

        $records = collect([$printer, $kursi, $laptop]);
        $sorted  = $this->resolveViaReflection($records, 'name');

        $this->assertSame(
            ['Apple MacBook', 'kursi Ergonomis', 'Printer Zebra'],
            $sorted->pluck('name')->values()->all()
        );
    }

    /**
     * Verifikasi resolveAssets() dapat mengurutkan berdasarkan kode inventaris (A->Z).
     */
    public function test_barcode_bulk_sorted_by_inventory_number_asc(): void
    {
        $b = Asset::factory()->create(['inventory_number' => 'INV/2026/B']);
        $a = Asset::factory()->create(['inventory_number' => 'INV/2026/A']);
        $c = Asset::factory()->create(['inventory_number' => 'INV/2026/C']);

        $records = collect([$c, $a, $b]);
        $sorted  = $this->resolveViaReflection($records, 'inventory_number');

        $this->assertSame(
            ['INV/2026/A', 'INV/2026/B', 'INV/2026/C'],
            $sorted->pluck('inventory_number')->values()->all()
        );
    }

    /**
     * Verifikasi queueForLocation dapat menerima custom sortBy (contoh: name).
     */
    public function test_queueForLocation_sorted_by_name(): void
    {
        $campus   = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);

        Asset::factory()->create([
            'name'        => 'Zebra Printer',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'name'        => 'Apple Mac',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'name'        => 'Dell Monitor',
            'campus_id'   => $campus->id,
            'location_id' => $location->id,
        ]);

        $queued = Asset::where('location_id', $location->id)
            ->whereNotNull('barcode')
            ->orderedForPrint('name')
            ->get();

        $this->assertSame(
            ['Apple Mac', 'Dell Monitor', 'Zebra Printer'],
            $queued->pluck('name')->values()->all()
        );
    }

    /**
     * Verifikasi route /checklist/print mendukung sort_by dan menyimpan preferensi ke session.
     */
    public function test_checklist_print_supports_flexible_sort_and_saves_session(): void
    {
        $user = \App\Models\User::factory()->create();
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);

        Asset::factory()->create([
            'name' => 'Zebra',
            'notes' => 'A First',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'name' => 'Alfa',
            'notes' => 'Z Last',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('checklist.print', ['location_id' => $location->id, 'sort_by' => 'name']));

        $response->assertOk();
        $response->assertSee('Urut: Nama Barang (A–Z)');
        $this->assertSame('name', session('last_print_sort_' . $location->id));
        $this->assertSame('name', session('last_print_sort'));
    }
}