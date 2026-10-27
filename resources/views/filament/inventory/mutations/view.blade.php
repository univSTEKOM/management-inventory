<x-filament-panels::page>
    @php
        /** @var \App\Models\Mutation $record */
        $record = $this->getRecord();
        $status = $record->status;
        $statusLabel = \App\Filament\Inventory\Resources\MutationResource::statusLabel($status);
        $totalQuantity = $record->items->sum('quantity');
    @endphp

    <div class="space-y-6">
        {{-- Header Summary Card --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl shadow-xs">
            <div class="flex items-start gap-4">
                <div class="p-3.5 rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50 shrink-0">
                    <x-filament::icon icon="heroicon-o-arrows-right-left" class="w-8 h-8" />
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
                            {{ $record->mutation_number }}
                        </h1>
                        @if ($status === 'completed')
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                {{ $statusLabel }}
                            </span>
                        @elseif ($status === 'cancelled' || $status === 'rejected')
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-danger-50 text-danger-700 dark:bg-danger-950/60 dark:text-danger-300 border border-danger-200 dark:border-danger-800">
                                <x-filament::icon icon="heroicon-m-x-circle" class="w-4 h-4 text-danger-600 dark:text-danger-400" />
                                {{ $statusLabel }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                <x-filament::icon icon="heroicon-m-clock" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                {{ $statusLabel }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Dibuat oleh <strong class="text-gray-700 dark:text-gray-200">{{ $record->requestedBy?->name ?? 'Sistem' }}</strong> pada {{ $record->created_at?->format('d M Y, H:i') }}
                        @if ($record->mutation_date)
                            · Diproses pada <strong class="text-gray-700 dark:text-gray-200">{{ $record->mutation_date->format('d M Y, H:i') }}</strong>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-start md:self-auto flex-wrap">
                <x-filament::button
                    :href="\App\Filament\Inventory\Resources\MutationResource::getUrl('index')"
                    tag="a"
                    color="gray"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali
                </x-filament::button>

                @if ($status === 'completed')
                    <x-filament::button
                        :href="route('mutation.receipt', $record)"
                        tag="a"
                        target="_blank"
                        color="primary"
                        icon="heroicon-m-printer"
                    >
                        Cetak Bukti Mutasi
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- Visual Route (Source -> Destination) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
            {{-- Asal --}}
            <div class="lg:col-span-5 p-5 rounded-2xl border border-blue-200/80 dark:border-blue-900/40 bg-gradient-to-b from-blue-50/50 to-white dark:from-blue-950/20 dark:to-gray-900 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-blue-100 dark:border-blue-900/30 pb-2.5">
                    <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-arrow-up-tray" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        <span>Lokasi Asal (Sumber)</span>
                    </div>
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200">Asal</span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-m-building-office-2" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">Gedung / Kampus</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $record->sourceCampus?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">Ruangan</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $record->sourceLocation?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 pt-2 border-t border-blue-100 dark:border-blue-900/20">
                        <x-filament::icon icon="heroicon-m-user" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">PIC Penyerah</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->sourcePic?->name ?? 'Tanpa PIC Asal' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tengah Arrow --}}
            <div class="lg:col-span-2 flex flex-col items-center justify-center py-2 lg:py-0">
                <div class="hidden lg:flex flex-col items-center gap-2">
                    <div class="w-px h-8 bg-gradient-to-b from-transparent via-gray-300 dark:via-gray-600 to-primary-500"></div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-800 shadow-sm">
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-6 h-6" />
                    </div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Transfer</span>
                    <div class="w-px h-8 bg-gradient-to-t from-transparent via-gray-300 dark:via-gray-600 to-primary-500"></div>
                </div>
                <div class="flex lg:hidden items-center justify-center my-1 gap-2">
                    <div class="h-px w-12 bg-gray-300 dark:bg-gray-700"></div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-800">
                        <x-filament::icon icon="heroicon-m-arrow-down" class="w-4 h-4" />
                    </div>
                    <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Dipindahkan ke</span>
                    <div class="h-px w-12 bg-gray-300 dark:bg-gray-700"></div>
                </div>
            </div>

            {{-- Tujuan --}}
            <div class="lg:col-span-5 p-5 rounded-2xl border border-emerald-200/80 dark:border-emerald-900/40 bg-gradient-to-b from-emerald-50/50 to-white dark:from-emerald-950/20 dark:to-gray-900 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-emerald-100 dark:border-emerald-900/30 pb-2.5">
                    <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Lokasi Tujuan (Penerima)</span>
                    </div>
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-medium bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">Tujuan</span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-m-building-office-2" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">Gedung / Kampus</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $record->destinationCampus?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">Ruangan</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $record->destinationLocation?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 pt-2 border-t border-emerald-100 dark:border-emerald-900/20">
                        <x-filament::icon icon="heroicon-m-user" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                        <div>
                            <span class="text-xs text-gray-400 block">PIC Penerima</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->destinationPic?->name ?? 'Tanpa PIC Tujuan' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alasan Mutasi Card --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-xs space-y-2">
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-primary-500" />
                <span>Alasan / Keterangan Mutasi</span>
            </div>
            <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-line bg-gray-50 dark:bg-gray-800/50 p-3.5 rounded-xl border border-gray-100 dark:border-white/5">
                {{ $record->reason }}
            </p>
        </div>

        {{-- Daftar Barang yang Dimutasi --}}
        <div class="overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl shadow-xs">
            <div class="p-5 border-b border-gray-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-m-cube" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Rincian Barang yang Dimutasi</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total {{ $record->items->count() }} jenis barang fisik dan persediaan</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800">
                    <x-filament::icon icon="heroicon-m-check-badge" class="w-4 h-4" />
                    Total: {{ $totalQuantity }} unit
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 w-12 text-center">#</th>
                            <th scope="col" class="px-5 py-3.5">Kode / Barcode</th>
                            <th scope="col" class="px-5 py-3.5">Barang &amp; Merek</th>
                            <th scope="col" class="px-5 py-3.5">Jenis</th>
                            <th scope="col" class="px-5 py-3.5 text-right w-32">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($record->items as $index => $item)
                            @php($detail = $item->display_details)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5 transition">
                                <td class="px-5 py-3.5 text-center text-xs text-gray-400 font-medium">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $detail['code'] ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $detail['barcode'] ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $detail['name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $detail['brand'] ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if (($detail['type'] ?? '') === 'BHP' || str_contains(strtolower($detail['type'] ?? ''), 'habis pakai'))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            <x-filament::icon icon="heroicon-m-circle-stack" class="w-3.5 h-3.5" />
                                            {{ $detail['type'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                            <x-filament::icon icon="heroicon-m-cube" class="w-3.5 h-3.5" />
                                            {{ $detail['type'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-gray-900 dark:text-white">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs">
                                        {{ $item->quantity }} unit
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-800/40 border-t border-gray-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                <div class="flex items-center gap-4">
                    <span>Total Baris: <strong class="text-gray-900 dark:text-white">{{ $record->items->count() }}</strong></span>
                    <span>Total Kuantitas Fisik: <strong class="text-gray-900 dark:text-white">{{ $totalQuantity }} unit</strong></span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Status transaksi: <strong class="text-primary-600 dark:text-primary-400 uppercase">{{ $statusLabel }}</strong>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
