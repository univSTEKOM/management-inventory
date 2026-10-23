<?php

namespace Tests\Feature;

use App\Models\Asset;
use Tests\TestCase;

class ChecklistPrintViewTest extends TestCase
{
    public function test_checklist_prints_item_notes_as_escaped_multiline_text(): void
    {
        $asset = $this->assetWithNotes("Kaki meja longgar\n<script>alert('test')</script>");

        $this->view('checklist-print', ['assets' => collect([$asset])])
            ->assertSee("Kaki meja longgar\n&lt;script&gt;alert(&#039;test&#039;)&lt;/script&gt;", false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('Catatan laporan intake');
    }

    public function test_checklist_keeps_notes_cell_empty_when_item_has_no_notes(): void
    {
        $this->view('checklist-print', ['assets' => collect([$this->assetWithNotes(null)])])
            ->assertSee('<td class="col-note"></td>', false);
    }

    public function test_checklist_omits_automatic_notes_in_parentheses(): void
    {
        $assetWithUnknown = $this->assetWithNotes('Baterai Rusak (tahun perolehan tidak diketahui, harga perolehan tidak diketahui)');
        $this->view('checklist-print', ['assets' => collect([$assetWithUnknown])])
            ->assertSee('<td class="col-note">Baterai Rusak</td>', false)
            ->assertDontSee('tahun perolehan tidak diketahui')
            ->assertDontSee('harga perolehan tidak diketahui');

        $assetOnlyAutomatic = $this->assetWithNotes('(tahun perolehan tidak diketahui, harga perolehan tidak diketahui)');
        $this->view('checklist-print', ['assets' => collect([$assetOnlyAutomatic])])
            ->assertSee('<td class="col-note"></td>', false);

        $assetDashAndAutomatic = $this->assetWithNotes('- (tahun perolehan tidak diketahui)');
        $this->view('checklist-print', ['assets' => collect([$assetDashAndAutomatic])])
            ->assertSee('<td class="col-note"></td>', false);

        $assetDashOnly = $this->assetWithNotes('-');
        $this->view('checklist-print', ['assets' => collect([$assetDashOnly])])
            ->assertSee('<td class="col-note"></td>', false);
    }

    public function test_checklist_preserves_manual_parentheses_in_notes(): void
    {
        $asset = $this->assetWithNotes('Sering Bluescreen (Saat Mengedit) (tahun perolehan tidak diketahui)');
        $this->view('checklist-print', ['assets' => collect([$asset])])
            ->assertSee('<td class="col-note">Sering Bluescreen (Saat Mengedit)</td>', false)
            ->assertDontSee('tahun perolehan tidak diketahui');
    }

    private function assetWithNotes(?string $notes): Asset
    {
        $asset = new Asset([
            'inventory_number' => 'INV0000001',
            'name' => 'Meja',
            'brand' => 'Test',
            'notes' => $notes,
            'keterangan' => 'Catatan laporan intake',
        ]);

        foreach (['campus', 'location', 'pic'] as $relation) {
            $asset->setRelation($relation, null);
        }

        return $asset;
    }
}
