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
