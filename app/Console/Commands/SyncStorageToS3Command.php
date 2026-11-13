<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncStorageToS3Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:sync-to-s3 
                            {--dir= : Direktori spesifik yang ingin disinkronkan, misal: asset-photos}
                            {--force : Tetap upload dan timpa file yang sudah ada di S3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi file dari storage lokal (public) ke Contabo S3 Object Storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai pengecekan koneksi ke Contabo S3 Object Storage...');

        $localDisk = Storage::disk('public');
        $s3Disk = Storage::disk('s3_direct');

        try {
            // Test connection
            $s3Disk->put('.s3_test_connection.txt', 'test');
            $s3Disk->delete('.s3_test_connection.txt');
            $this->info('✓ Koneksi ke Contabo S3 berhasil!');
        } catch (Throwable $e) {
            $this->error('✗ Gagal terhubung ke Contabo S3: ' . $e->getMessage());
            $this->warn('Pastikan kredensial S3 di .env sudah aktif dan valid.');
            return self::FAILURE;
        }

        $specificDir = $this->option('dir');
        $force = (bool) $this->option('force');

        $directories = $specificDir ? [$specificDir] : ['asset-photos', 'foto-resi', 'invoices', 'inventory-photos'];

        $totalUploaded = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($directories as $dir) {
            $files = $localDisk->allFiles($dir);

            if (empty($files)) {
                $this->line("Direktori [{$dir}]: Tidak ada file lokal.");
                continue;
            }

            $this->info("\nMemproses direktori [{$dir}] (" . count($files) . ' file)...');
            $bar = $this->output->createProgressBar(count($files));
            $bar->start();

            foreach ($files as $file) {
                try {
                    if (! $force && $s3Disk->exists($file)) {
                        $totalSkipped++;
                        $bar->advance();
                        continue;
                    }

                    $stream = $localDisk->readStream($file);
                    if ($stream) {
                        $s3Disk->writeStream($file, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        $totalUploaded++;
                    }
                } catch (Throwable $e) {
                    $totalFailed++;
                    $this->newLine();
                    $this->error("Gagal mengupload {$file}: " . $e->getMessage());
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Berhasil diupload ke S3', $totalUploaded],
                ['Dilewati (Sudah ada di S3)', $totalSkipped],
                ['Gagal', $totalFailed],
            ]
        );

        $this->info('Proses sinkronisasi selesai!');
        return self::SUCCESS;
    }
}

