<?php

use App\Http\Controllers\AssetImportTemplateController;
use App\Http\Controllers\ReportController;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\BeritaAcaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->hasRole('Superadmin') || $user->hasPermissionTo('panel.admin')) {
            return redirect('admin');
        } elseif ($user->hasPermissionTo('panel.inventory')) {
            return redirect('inventory');
        }
    }

    return redirect('admin');
});

Route::get('/movement/{movement}/berita-acara', function (AssetMovement $movement, BeritaAcaraService $baService) {
    abort_unless(Auth::check(), 403);
    abort_unless($movement->status === 'completed', 404, 'Mutasi belum selesai.');

    return $baService->generateForMovement($movement);
})->name('asset.movement.ba')->middleware('auth');

Route::get('/report/export', [ReportController::class, 'exportExcel'])
    ->name('report.export.excel')
    ->middleware('auth');

Route::get('/asset/import/template', [AssetImportTemplateController::class, 'downloadCsv'])
    ->name('asset.import.template')
    ->middleware('auth');

Route::get('/checklist/print', function (Request $request) {
    $query = Asset::query()->with(['category', 'classification', 'campus', 'location', 'pic']);

    if ($request->filled('location_id')) {
        $query->where('location_id', $request->integer('location_id'));
    } elseif ($request->filled('ids')) {
        $query->whereIn('id', array_filter(explode(',', $request->string('ids'))));
    } else {
        abort(404);
    }

    $sortBy = $request->string('sort_by', 'notes')->toString();

    if ($request->filled('location_id') && $request->filled('sort_by')) {
        session(['last_print_sort_' . $request->integer('location_id') => $sortBy]);
        session(['last_print_sort' => $sortBy]);
    }

    $assets = $query->orderedForPrint($sortBy)->get();

    abort_if($assets->isEmpty(), 404, 'Tidak ada barang untuk dicetak.');

    $sortLabels = [
        'notes' => 'Keterangan (A–Z)',
        'name' => 'Nama Barang (A–Z)',
        'inventory_number' => 'Kode Inventaris (A–Z)',
    ];
    $sortLabel = $sortLabels[$sortBy] ?? $sortLabels['notes'];

    return view('checklist-print', [
        'assets' => $assets,
        'sortLabel' => $sortLabel,
    ]);
})->name('checklist.print')->middleware('auth');

/**
 * Coba cek keberadaan file di disk dengan toleran terhadap exception S3.
 * Mengembalikan disk yang mengandung file, atau null jika tidak ditemukan di mana pun.
 */
$resolvePhotoDisk = function (string $path): ?\Illuminate\Contracts\Filesystem\Filesystem {
    foreach (['s3', 'public', 'local'] as $diskName) {
        try {
            $d = Storage::disk($diskName);
            if ($d->exists($path)) {
                return $d;
            }
        } catch (Throwable) {
            // S3 tidak bisa diakses (credentials salah / jaringan) — coba disk berikutnya
        }
    }

    return null;
};

Route::get('/asset-photo-thumb/{path}', function (string $path) use ($resolvePhotoDisk) {
    $disk = $resolvePhotoDisk($path);
    if (! $disk) {
        abort(404);
    }

    $cacheKey = 'thumb_b64_'.md5($path);
    $base64Data = Cache::rememberForever($cacheKey, function () use ($disk, $path) {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->decode($disk->get($path));
            $image->scaleDown(width: 400);
            $raw = (string) $image->encode(new JpegEncoder(quality: 75));

            return base64_encode($raw);
        } catch (Throwable $e) {
            return base64_encode($disk->get($path));
        }
    });

    return response(base64_decode($base64Data), 200)
        ->header('Content-Type', 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=31536000');
})->where('path', '.*')->name('asset.photo.thumb');

Route::get('/asset-photo-view/{path}', function (string $path) use ($resolvePhotoDisk) {
    $disk = $resolvePhotoDisk($path);
    if (! $disk) {
        abort(404, 'File foto tidak ditemukan.');
    }

    return response($disk->get($path), 200, [
        'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*')->name('asset.photo.view');

Route::get('/asset-photo-download/{path}', function (Request $request, string $path) use ($resolvePhotoDisk) {
    $disk = $resolvePhotoDisk($path);
    if (! $disk) {
        abort(404, 'File foto tidak ditemukan.');
    }

    $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
    $customName = $request->query('name');
    $downloadName = $customName ? (Str::slug($customName, '_').'.'.$ext) : basename($path);

    return response()->streamDownload(function () use ($disk, $path) {
        $stream = $disk->readStream($path);
        if (is_resource($stream)) {
            fpassthru($stream);
            fclose($stream);
        }
    }, $downloadName, [
        'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
    ]);
})->where('path', '.*')->name('asset.photo.download')->middleware('auth');

Route::get('/mutation/{mutation}/receipt', function (\App\Models\Mutation $mutation) {
    \Illuminate\Support\Facades\Gate::authorize('view', $mutation);
    abort_unless($mutation->status === 'completed', 404);
    $mutation->load(['items.asset.classification', 'items.inventoryBalance', 'sourceCampus', 'sourceLocation', 'destinationCampus', 'destinationLocation', 'sourcePic', 'destinationPic', 'requestedBy']);
    return view('documents.mutation-receipt', compact('mutation'));
})->middleware('auth')->name('mutation.receipt');
