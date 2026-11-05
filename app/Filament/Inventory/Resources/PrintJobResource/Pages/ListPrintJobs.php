<?php

namespace App\Filament\Inventory\Resources\PrintJobResource\Pages;

use App\Filament\Inventory\Resources\PrintJobResource;
use App\Models\PrintJob;
use App\Models\PrinterStation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stationStatus')
                ->label(function () {
                    $stations = PrinterStation::all();
                    if ($stations->isEmpty()) {
                        return 'Printer: Belum Didaftarkan';
                    }
                    $onlineCount = $stations->filter(fn ($s) => $s->isOnline())->count();
                    return "Printer Station: {$onlineCount}/{$stations->count()} Online";
                })
                ->color(function () {
                    $stations = PrinterStation::all();
                    if ($stations->isEmpty()) {
                        return 'gray';
                    }
                    $anyOnline = $stations->contains(fn ($s) => $s->isOnline());
                    return $anyOnline ? 'success' : 'danger';
                })
                ->icon('heroicon-o-signal')
                ->modalHeading('Status Printer Station (PC Gudang)')
                ->modalContent(function () {
                    $stations = PrinterStation::all();
                    if ($stations->isEmpty()) {
                        return new HtmlString('<p class="text-sm text-gray-500">Belum ada printer station yang terdaftar. Jalankan <code>php artisan printer:create-station</code>.</p>');
                    }

                    $html = '<div class="space-y-3">';
                    foreach ($stations as $s) {
                        $online = $s->isOnline();
                        $statusBadge = $online
                            ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">ONLINE</span>'
                            : '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">OFFLINE</span>';
                        $lastSeen = $s->last_seen_at ? $s->last_seen_at->diffForHumans() : 'Belum pernah terhubung';

                        $html .= "<div class='flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/40'>
                            <div>
                                <div class='font-semibold text-sm'>{$s->name} <span class='font-mono text-xs text-gray-500'>({$s->station_key})</span></div>
                                <div class='text-xs text-gray-500'>Terakhir aktif: {$lastSeen}</div>
                            </div>
                            <div>{$statusBadge}</div>
                        </div>";
                    }
                    $html .= '</div>';

                    return new HtmlString($html);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            Action::make('resetQueue')
                ->label('Reset Antrean')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->tooltip('Batalkan semua job yang masih Menunggu/Diproses agar printer langsung siap mencetak barang baru')
                ->visible(function () {
                    $user = \Illuminate\Support\Facades\Auth::user();
                    return $user?->hasRole('Superadmin')
                        || $user?->hasRole('superadmin')
                        || ($user?->hasPermissionTo('print_jobs.delete') ?? false);
                })
                ->requiresConfirmation()
                ->modalHeading('Reset Antrean Cetak?')
                ->modalDescription(function () {
                    $count = PrintJob::whereIn('status', ['pending', 'processing'])->count();
                    return "Saat ini ada {$count} job yang masih Menunggu/Diproses. Reset akan membatalkan semua job tersebut agar printer tidak mencetak tumpukan lama yang macet dan siap langsung mencetak data baru.";
                })
                ->modalSubmitActionLabel('Ya, Batalkan Semua Antrean')
                ->action(function () {
                    $userName = \Illuminate\Support\Facades\Auth::user()?->name ?? 'User';
                    $affected = PrintJob::whereIn('status', ['pending', 'processing'])
                        ->update([
                            'status'        => 'failed',
                            'error_message' => "Dibatalkan melalui Reset Antrean oleh {$userName} pada " . now()->format('d/m/Y H:i'),
                        ]);

                    if ($affected > 0) {
                        Notification::make()
                            ->title("{$affected} antrean berhasil dibatalkan!")
                            ->body('Antrean cetak sekarang bersih (0 pending). Anda bisa langsung mencetak data baru.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Antrean sudah bersih.')
                            ->body('Tidak ada job pending atau processing yang perlu dibatalkan.')
                            ->info()
                            ->send();
                    }
                }),

            Action::make('recoverStuck')
                ->label('Pulihkan')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Reset semua job yang statusnya masih Diproses lebih dari 5 menit kembali ke Menunggu')
                ->requiresConfirmation()
                ->modalHeading('Pulihkan Job yang Terjebak?')
                ->modalDescription('Job yang berstatus "Sedang Diproses" lebih dari 5 menit (biasanya karena terminal ditutup mendadak) akan dikembalikan ke status "Menunggu" agar bisa dicetak.')
                ->action(function () {
                    $recovered = PrintJob::where('status', 'processing')
                        ->where('updated_at', '<', now()->subMinutes(5))
                        ->update([
                            'status'        => 'pending',
                            'attempts'      => DB::raw('attempts + 1'),
                            'error_message' => 'Dipulihkan manual dari antrean macet.',
                        ]);

                    if ($recovered > 0) {
                        Notification::make()
                            ->title("{$recovered} job macet berhasil dipulihkan ke status Menunggu.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Tidak ada job yang macet saat ini.')
                            ->info()
                            ->send();
                    }
                }),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'semua';
    }

    public function getTabs(): array
    {
        $baseQuery = static::getResource()::getEloquentQuery();

        return [
            'semua' => Tab::make('Semua')
                ->badge((clone $baseQuery)->count()),

            'pending' => Tab::make('Menunggu')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge((clone $baseQuery)->where('status', 'pending')->count())
                ->badgeColor('warning'),

            'processing' => Tab::make('Diproses')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge((clone $baseQuery)->where('status', 'processing')->count())
                ->badgeColor('info'),

            'completed' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge((clone $baseQuery)->where('status', 'completed')->count())
                ->badgeColor('success'),

            'failed' => Tab::make('Gagal')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge((clone $baseQuery)->where('status', 'failed')->count())
                ->badgeColor('danger'),
        ];
    }
}

