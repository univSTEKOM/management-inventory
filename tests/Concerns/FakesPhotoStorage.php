<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Disk foto palsu untuk test. Di production `s3` dan `s3_thumb` adalah bucket
 * yang sama (hanya URL publik berbeda), jadi di sini `s3_thumb` diarahkan ke
 * fake disk `s3` yang sama.
 */
trait FakesPhotoStorage
{
    /** PNG 1x1 valid, supaya deteksi MIME dari isi file menghasilkan image/png. */
    protected const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function fakePhotoDisks(): void
    {
        Storage::fake('s3');
        Storage::set('s3_thumb', Storage::disk('s3'));
        Storage::fake('public');
        Storage::fake('tmp-for-tests');
        config(['filesystems.default' => 's3']);
    }

    protected function putTestImage(string $path): string
    {
        Storage::disk('s3')->put($path, base64_decode(self::PNG_1X1));

        return $path;
    }

    protected function pngBytes(): string
    {
        return base64_decode(self::PNG_1X1);
    }
}
