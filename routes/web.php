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

    $assets = $query->orderBy('name')->orderBy('inventory_number')->get();

    abort_if($assets->isEmpty(), 404, 'Tidak ada barang untuk dicetak.');

    return view('checklist-print', ['assets' => $assets]);
})->name('checklist.print')->middleware('auth');

Route::get('/asset-photo-thumb/{path}', function (string $path) {
    $disk = Storage::disk(config('filesystems.default', 's3'));
    if (! $disk->exists($path)) {
        if (Storage::disk('public')->exists($path)) {
            $disk = Storage::disk('public');
        } else {
            abort(404);
        }
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

Route::get('/asset-photo-download/{path}', function (Request $request, string $path) {
    $disk = Storage::disk(config('filesystems.default', 's3'));
    if (! $disk->exists($path)) {
        if (Storage::disk('public')->exists($path)) {
            $disk = Storage::disk('public');
        } else {
            abort(404, 'File foto tidak ditemukan.');
        }
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
