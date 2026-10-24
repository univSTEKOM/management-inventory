<x-filament-panels::page>
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-danger-500/30 bg-danger-50 dark:bg-danger-950/40 p-4 text-danger-700 dark:text-danger-300 shadow-xs">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-5 h-5 text-danger-600 dark:text-danger-400 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <strong class="font-semibold">Mutasi belum diproses. Periksa data berikut:</strong>
                    <ul class="list-disc list-inside text-sm space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Breadcrumb & Quick Info Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                <x-filament::icon icon="heroicon-o-arrows-right-left" class="w-6 h-6" />
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">Formulir Mutasi Perpindahan Barang</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pindahkan aset fisik inventaris atau barang persediaan (BHP) antar ruangan atau gedung secara langsung.</p>
            </div>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
            <x-filament::button
                :href="\App\Filament\Inventory\Resources\MutationResource::getUrl('index')"
                tag="a"
                size="sm"
                color="gray"
                icon="heroicon-m-arrow-left"
            >
                Daftar Mutasi
            </x-filament::button>
        </div>
    </div>

    <form x-data wire:submit="review" class="space-y-6">
        {{-- Section 1: Lokasi Asal dan Tujuan --}}
        <x-filament::section icon="heroicon-m-map-pin">
            <x-slot name="heading">
                <span class="text-base font-semibold text-gray-900 dark:text-white">Lokasi Asal &amp; Lokasi Tujuan</span>
            </x-slot>
            <x-slot name="description">
                Tentukan ruangan asal barang yang akan dipindahkan dan ruangan tujuan penerima.
            </x-slot>

            <style>
                .mutation-locations-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 1rem;
                    align-items: start;
                }
                @media (max-width: 640px) {
                    .mutation-locations-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            <div class="mutation-locations-grid">
                {{-- Lokasi Asal (Kiri) --}}
                <div class="p-4 rounded-xl border border-blue-200/80 dark:border-blue-900/40 bg-gradient-to-b from-blue-50/50 to-white dark:from-blue-950/20 dark:to-gray-900 shadow-xs space-y-3.5">
                    <div class="flex items-center justify-between border-b border-blue-100 dark:border-blue-900/30 pb-2">
                        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300 font-semibold text-sm">
                            <x-filament::icon icon="heroicon-m-arrow-up-tray" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            <span>1. Lokasi Asal (Sumber)</span>
                        </div>
                        <span class="text-[11px] px-2 py-0.5 rounded-md font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200">Asal</span>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Gedung Asal <span class="text-danger-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="Gedung Asal"
                                    class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                                    wire:model.live="data.source_campus_id"
                                    x-on:change.capture="if ($wire.items.length && ! confirm('Mengganti asal akan mengosongkan daftar barang. Lanjutkan?')) { $event.stopImmediatePropagation(); $event.target.value = $wire.data.source_campus_id; }"
                                >
                                    <option value="">Pilih gedung asal</option>
                                    @foreach ($campuses as $campus)
                                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Ruangan Asal <span class="text-danger-500">*</span>
                            </label>
                            <select
                                aria-label="Ruangan Asal"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                                wire:model.live="data.source_location_id"
                                @disabled(! $data['source_campus_id'])
                                x-on:change.capture="if ($wire.items.length && ! confirm('Mengganti asal akan mengosongkan daftar barang. Lanjutkan?')) { $event.stopImmediatePropagation(); $event.target.value = $wire.data.source_location_id; }"
                            >
                                <option value="">{{ $data['source_campus_id'] ? 'Pilih ruangan asal' : 'Pilih gedung asal terlebih dahulu' }}</option>
                                @foreach ($sourceLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                PIC Asal <span class="text-gray-400 font-normal">(opsional)</span>
                            </label>
                            <select
                                aria-label="PIC Asal"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                                wire:model.live="data.source_pic_id"
                            >
                                <option value="">Tanpa PIC</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if ($data['source_location_id'])
                        <div class="mt-2 pt-2 border-t border-blue-100 dark:border-blue-900/30 text-[11px] text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-3.5 h-3.5 shrink-0" />
                            <span>Lokasi asal aktif: barang siap dicari di bawah.</span>
                        </div>
                    @endif
                </div>

                {{-- Lokasi Tujuan (Kanan) --}}
                <div class="p-4 rounded-xl border border-emerald-200/80 dark:border-emerald-900/40 bg-gradient-to-b from-emerald-50/50 to-white dark:from-emerald-950/20 dark:to-gray-900 shadow-xs space-y-3.5">
                    <div class="flex items-center justify-between border-b border-emerald-100 dark:border-emerald-900/30 pb-2">
                        <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-semibold text-sm">
                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            <span>2. Lokasi Tujuan (Penerima)</span>
                        </div>
                        <span class="text-[11px] px-2 py-0.5 rounded-md font-medium bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">Tujuan</span>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Gedung Tujuan <span class="text-danger-500">*</span>
                            </label>
                            <select
                                aria-label="Gedung Tujuan"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                                wire:model.live="data.destination_campus_id"
                            >
                                <option value="">Pilih gedung tujuan</option>
                                @foreach ($campuses as $campus)
                                    <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Ruangan Tujuan <span class="text-danger-500">*</span>
                            </label>
                            <select
                                aria-label="Ruangan Tujuan"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                                wire:model.live="data.destination_location_id"
                                @disabled(! $data['destination_campus_id'])
                            >
                                <option value="">{{ $data['destination_campus_id'] ? 'Pilih ruangan tujuan' : 'Pilih gedung tujuan terlebih dahulu' }}</option>
                                @foreach ($destinationLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                PIC Tujuan <span class="text-gray-400 font-normal">(opsional)</span>
                            </label>
                            <select
                                aria-label="PIC Tujuan"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                                wire:model.live="data.destination_pic_id"
                            >
                                <option value="">Tanpa PIC</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if ($data['destination_location_id'] && $data['source_location_id'] === $data['destination_location_id'])
                        <div class="mt-2 pt-2 border-t border-danger-200 dark:border-danger-900/30 text-[11px] text-danger-600 dark:text-danger-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-x-circle" class="w-3.5 h-3.5 shrink-0" />
                            <span>Ruangan tujuan tidak boleh sama dengan ruangan asal.</span>
                        </div>
                    @elseif ($data['destination_location_id'])
                        <div class="mt-2 pt-2 border-t border-emerald-100 dark:border-emerald-900/30 text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-3.5 h-3.5 shrink-0" />
                            <span>Lokasi penerima telah ditentukan.</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Summary Rute Bar --}}
            @if ($data['source_location_id'] && $data['destination_location_id'] && $data['source_location_id'] !== $data['destination_location_id'])
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-white/10 rounded-xl flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Rute Mutasi Terpilih:</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-medium">
                            {{ $campuses->firstWhere('id', $data['source_campus_id'])?->name }} / {{ $sourceLocations->firstWhere('id', $data['source_location_id'])?->name }}
                        </span>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-gray-400 shrink-0" />
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-medium">
                            {{ $campuses->firstWhere('id', $data['destination_campus_id'])?->name }} / {{ $destinationLocations->firstWhere('id', $data['destination_location_id'])?->name }}
                        </span>
                    </div>
                    <span class="text-gray-400 text-[11px]">Langsung diperbarui saat diproses</span>
                </div>
            @endif
        </x-filament::section>

        {{-- Section 2: Cari & Tambah Barang --}}
        <x-filament::section icon="heroicon-m-magnifying-glass">
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <span class="text-base font-semibold text-gray-900 dark:text-white">Cari Barang di Lokasi Asal</span>
                </div>
            </x-slot>
            <x-slot name="description">
                Pilih barang inventaris fisik atau stok BHP yang ada di ruangan asal untuk dipindahkan.
            </x-slot>

            <div class="space-y-4">
                {{-- Search Bar --}}
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="flex-1">
                        <label for="mutation-search" class="sr-only">Kode barang, barcode, nomor inventaris, atau nama barang</label>
                        <x-filament::input.wrapper>
                            <x-slot name="prefix">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-5 h-5 text-gray-400" />
                            </x-slot>
                            <x-filament::input
                                id="mutation-search"
                                type="text"
                                wire:model.live.debounce.350ms="search"
                                placeholder="Ketik untuk mencari barang di lokasi asal…"
                                :disabled="! $data['source_location_id']"
                            />
                            @if ($search)
                                <x-slot name="suffix">
                                    <button
                                        type="button"
                                        wire:click="$set('search', '')"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        title="Hapus pencarian"
                                    >
                                        <x-filament::icon icon="heroicon-m-x-mark" class="w-4 h-4" />
                                    </button>
                                </x-slot>
                            @endif
                        </x-filament::input.wrapper>
                    </div>
                </div>

                {{-- Information Hint --}}
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-primary-500 shrink-0" />
                    <span>Hasil dibatasi 30 barang individual dan 30 BHP. Perjelas pencarian jika barang belum terlihat.</span>
                </div>

                {{-- Results Table --}}
                <div class="overflow-hidden border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-gray-900 shadow-2xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs uppercase font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Kode / Barcode</th>
                                    <th scope="col" class="px-4 py-3">Barang / Merek</th>
                                    <th scope="col" class="px-4 py-3">Jenis</th>
                                    <th scope="col" class="px-4 py-3">PIC</th>
                                    <th scope="col" class="px-4 py-3">Tersedia</th>
                                    <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @forelse ($results as $result)
                                    @php($isAdded = in_array($result['key'], array_column($items, 'key')))
                                    <tr wire:key="result-{{ $result['key'] }}" class="hover:bg-gray-50/80 dark:hover:bg-white/5 transition">
                                        <td class="px-4 py-3 font-mono text-xs">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $result['code'] ?: '—' }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $result['barcode'] }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $result['name'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $result['brand'] ?: '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($result['type'] === 'BHP' || str_contains(strtolower($result['type']), 'habis pakai'))
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    <x-filament::icon icon="heroicon-m-circle-stack" class="w-3.5 h-3.5" />
                                                    {{ $result['type'] }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                    <x-filament::icon icon="heroicon-m-cube" class="w-3.5 h-3.5" />
                                                    {{ $result['type'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($result['pic_name'])
                                                <span class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-gray-300">
                                                    <x-filament::icon icon="heroicon-m-user" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                    {{ $result['pic_name'] }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-600">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                                {{ $result['available'] }} unit
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if ($isAdded)
                                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                                                    <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5 text-success-500" />
                                                    Ditambahkan
                                                </span>
                                            @else
                                                <x-filament::button
                                                    type="button"
                                                    size="xs"
                                                    icon="heroicon-m-plus"
                                                    wire:click="addItem('{{ $result['key'] }}')"
                                                >
                                                    Tambah
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                @if (! $data['source_location_id'])
                                                    <x-filament::icon icon="heroicon-o-map-pin" class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                                                    <p class="font-medium">Pilih lokasi asal terlebih dahulu.</p>
                                                    <p class="text-xs text-gray-400">Gedung dan ruangan asal wajib dipilih sebelum mencari barang.</p>
                                                @elseif (trim($search) === '')
                                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                                                    <p class="font-medium">Ketik kode atau nama barang untuk mencari.</p>
                                                    <p class="text-xs text-gray-400">Barang yang tersedia di ruangan asal akan ditampilkan di sini.</p>
                                                @else
                                                    <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                                                    <p class="font-medium">Tidak ada barang yang cocok di lokasi asal.</p>
                                                    <p class="text-xs text-gray-400">Coba ubah kata kunci kode, barcode, atau nama barang.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Section 3: Barang yang Akan Dipindahkan (Keranjang Staging) --}}
        <x-filament::section icon="heroicon-m-shopping-bag">
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <span class="text-base font-semibold text-gray-900 dark:text-white">Barang yang akan dipindahkan</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ count($items) > 0 ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 ring-1 ring-primary-500/20' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        <x-filament::icon icon="heroicon-m-cube" class="w-3.5 h-3.5" />
                        {{ count($items) }} Barang Dipilih
                    </span>
                </div>
            </x-slot>
            <x-slot name="description">
                Daftar barang yang siap dipindahkan ke lokasi tujuan beserta jumlah kuantitasnya.
            </x-slot>

            <div class="overflow-hidden border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-gray-900 shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                            <tr>
                                <th scope="col" class="px-4 py-3 w-12 text-center">#</th>
                                <th scope="col" class="px-4 py-3">Barang</th>
                                <th scope="col" class="px-4 py-3">Identitas</th>
                                <th scope="col" class="px-4 py-3">Jenis</th>
                                <th scope="col" class="px-4 py-3 w-40">Jumlah</th>
                                <th scope="col" class="px-4 py-3 text-right w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @forelse ($items as $index => $item)
                                <tr wire:key="selected-{{ $item['key'] }}" class="hover:bg-gray-50/80 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 text-center text-xs text-gray-400 font-medium">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ $item['name'] }}
                                        </div>
                                        @if ($item['brand'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['brand'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <div class="text-gray-900 dark:text-gray-100 font-medium">{{ $item['code'] ?: '—' }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $item['barcode'] ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($item['type'] === 'BHP' || str_contains(strtolower($item['type']), 'habis pakai'))
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                <x-filament::icon icon="heroicon-m-circle-stack" class="w-3.5 h-3.5" />
                                                {{ $item['type'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                <x-filament::icon icon="heroicon-m-cube" class="w-3.5 h-3.5" />
                                                {{ $item['type'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if (str_starts_with($item['key'], 'supply:'))
                                            <div class="space-y-1">
                                                <input
                                                    aria-label="Jumlah {{ $item['name'] }}"
                                                    class="w-24 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-2.5 py-1.5 text-sm font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                                                    type="number"
                                                    min="1"
                                                    max="{{ $item['available'] }}"
                                                    step="1"
                                                    wire:model.live="items.{{ $index }}.quantity"
                                                >
                                                <div class="text-[10px] text-gray-400">Maks: {{ $item['available'] }} unit</div>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                1 unit fisik
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::button
                                            type="button"
                                            color="danger"
                                            size="xs"
                                            icon="heroicon-m-trash"
                                            wire:click="removeItem({{ $index }})"
                                        >
                                            Hapus
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <x-filament::icon icon="heroicon-o-shopping-bag" class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                                            <p class="font-medium">Belum ada barang dipilih.</p>
                                            <p class="text-xs text-gray-400">Gunakan kolom pencarian di atas dan klik tombol "Tambah" pada barang yang ingin dimutasi.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Table Summary Footer --}}
                @if (count($items) > 0)
                    <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border-t border-gray-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                        <div class="flex items-center gap-4">
                            <span>Total Baris: <strong class="text-gray-900 dark:text-white font-semibold">{{ count($items) }}</strong></span>
                            <span>Total Kuantitas: <strong class="text-gray-900 dark:text-white font-semibold">{{ array_sum(array_column($items, 'quantity')) }} unit</strong></span>
                        </div>
                        <span class="text-gray-400 text-[11px]">Pastikan kuantitas barang persediaan telah sesuai</span>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Section 4: Alasan Mutasi & Tombol Aksi --}}
        <x-filament::section icon="heroicon-m-document-text">
            <x-slot name="heading">
                <span class="text-base font-semibold text-gray-900 dark:text-white">Alasan &amp; Keperluan Mutasi</span>
            </x-slot>
            <x-slot name="description">
                Tuliskan keterangan pendukung atau memo pemindahan barang ini.
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1" for="mutation-reason">
                        Alasan mutasi <span class="text-danger-500">*</span>
                    </label>
                    <textarea
                        id="mutation-reason"
                        class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg p-3 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition shadow-2xs"
                        wire:model.live.debounce.350ms="data.reason"
                        maxlength="5000"
                        rows="3"
                        placeholder="Contoh: Pemindahan unit komputer dan persediaan kertas ke ruang laboratorium baru sesuai memo internal..."
                        required
                    ></textarea>
                    <div class="mt-1 flex items-center justify-between text-[11px] text-gray-400">
                        <span>Wajib diisi sebagai catatan audit pergerakan aset.</span>
                        <span>{{ strlen($data['reason'] ?? '') }} / 5000 karakter</span>
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="pt-4 border-t border-gray-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3">
                    <x-filament::button
                        :href="\App\Filament\Inventory\Resources\MutationResource::getUrl('index')"
                        tag="a"
                        color="gray"
                        icon="heroicon-m-arrow-left"
                    >
                        Batal
                    </x-filament::button>

                    <x-filament::button
                        type="submit"
                        size="lg"
                        icon="heroicon-m-arrow-right-circle"
                        wire:loading.attr="disabled"
                        :disabled="count($items) === 0"
                    >
                        Proses Mutasi
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </form>

    {{-- Dialog Konfirmasi Perpindahan Barang --}}
    @if ($confirming)
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="mutation-confirm-title"
            x-data
            x-trap.inert.noscroll="true"
            x-on:keydown.escape.window="$wire.set('confirming', false)"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs transition-opacity"
        >
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto space-y-5 animate-in fade-in duration-200">
                {{-- Modal Header --}}
                <div class="flex items-start gap-4 border-b border-gray-200 dark:border-white/10 pb-4">
                    <div class="p-3 rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50 shrink-0">
                        <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 id="mutation-confirm-title" class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
                            Konfirmasi perpindahan barang
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Pastikan data rute dan daftar barang berikut sudah sesuai sebelum diproses.
                        </p>
                    </div>
                </div>

                {{-- Visual Route Box --}}
                <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-white/10 space-y-2">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rute Perpindahan</div>
                    <div class="flex items-center gap-2 flex-wrap text-sm font-semibold text-gray-900 dark:text-white">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                            {{ $campuses->find($data['source_campus_id'])?->name }} / {{ $sourceLocations->find($data['source_location_id'])?->name }}
                        </span>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-gray-400 shrink-0" />
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            {{ $campuses->find($data['destination_campus_id'])?->name }} / {{ $destinationLocations->find($data['destination_location_id'])?->name }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        PIC asal: {{ $employees->find($data['source_pic_id'])?->name ?? '—' }} · PIC tujuan: {{ $employees->find($data['destination_pic_id'])?->name ?? '—' }}
                    </p>
                </div>

                {{-- Items Staging List --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Barang yang Akan Dipindahkan ({{ count($items) }}):</span>
                        <span class="text-gray-400">Total kuantitas: {{ array_sum(array_column($items, 'quantity')) }} unit</span>
                    </div>
                    <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-gray-950/40 p-3">
                        <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-1.5 list-disc list-inside">
                            @foreach ($items as $item)
                                <li class="font-mono">
                                    <span class="font-sans font-medium text-gray-900 dark:text-white">{{ $item['name'] }} {{ $item['brand'] }}</span> — {{ $item['code'] }} / {{ $item['barcode'] }} × {{ $item['quantity'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Alasan Box --}}
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 block font-medium">Alasan Mutasi:</span>
                    <p class="text-gray-900 dark:text-white font-medium mt-0.5 whitespace-pre-line">{{ $data['reason'] }}</p>
                </div>

                {{-- Alert Notice --}}
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                    <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <p>Pastikan barang dipindahkan sesuai daftar. Lokasi dan stok langsung diperbarui setelah berhasil.</p>
                </div>

                {{-- Modal Action Buttons --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-white/10">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="$set('confirming', false)"
                        wire:loading.attr="disabled"
                    >
                        Kembali
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        icon="heroicon-m-check"
                        wire:click="process"
                        wire:loading.attr="disabled"
                    >
                        Konfirmasi &amp; Proses
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
