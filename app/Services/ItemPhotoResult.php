<?php

namespace App\Services;

use Filament\Notifications\Notification;

/**
 * Hasil penerapan foto ke beberapa barang. Tiap barang masuk tepat satu
 * kelompok: berhasil, dilewati (sengaja tidak diubah), atau gagal.
 */
class ItemPhotoResult
{
    /** @var array<int, string> label barang */
    public array $succeeded = [];

    /** @var array<int, array{label: string, reason: string}> */
    public array $skipped = [];

    /** @var array<int, array{label: string, reason: string}> */
    public array $failed = [];

    /** @var array<int, string> catatan umum (unik), mis. batas foto Stok BHP */
    public array $notes = [];

    public function succeed(string $label): void
    {
        $this->succeeded[] = $label;
    }

    public function skip(string $label, string $reason): void
    {
        $this->skipped[] = ['label' => $label, 'reason' => $reason];
    }

    public function fail(string $label, string $reason): void
    {
        $this->failed[] = ['label' => $label, 'reason' => $reason];
    }

    public function note(string $note): void
    {
        if (! in_array($note, $this->notes, true)) {
            $this->notes[] = $note;
        }
    }

    public function successCount(): int
    {
        return count($this->succeeded);
    }

    public function isFullySuccessful(): bool
    {
        return $this->successCount() > 0 && $this->skipped === [] && $this->failed === [];
    }

    public function toNotification(int $listLimit = 10): Notification
    {
        $success = $this->successCount();
        $skipped = count($this->skipped);
        $failed = count($this->failed);

        $title = match (true) {
            $this->isFullySuccessful() => "Foto berhasil disimpan ke {$success} barang.",
            $success > 0 => "Foto disimpan ke {$success} barang, {$skipped} dilewati, {$failed} gagal.",
            default => "Tidak ada barang yang diperbarui ({$skipped} dilewati, {$failed} gagal).",
        };

        $lines = [];
        foreach (array_slice([...$this->failed, ...$this->skipped], 0, $listLimit) as $row) {
            $lines[] = "• {$row['label']}: {$row['reason']}";
        }
        $remaining = $failed + $skipped - count($lines);
        if ($remaining > 0) {
            $lines[] = "… dan {$remaining} barang lainnya.";
        }

        foreach ($this->notes as $note) {
            $lines[] = "ℹ {$note}";
        }

        $notification = Notification::make()->title($title);

        if ($lines !== []) {
            $notification->body(implode("\n", $lines))->persistent();
        }

        return match (true) {
            $this->isFullySuccessful() => $notification->success(),
            $success > 0 => $notification->warning(),
            default => $notification->danger(),
        };
    }
}
