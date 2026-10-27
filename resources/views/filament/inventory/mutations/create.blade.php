<x-filament-panels::page>
    <div class="space-y-6" x-data="{
        incrementQty(index, max) {
            let el = document.getElementById('item-qty-' + index);
            if (el) {
                let current = parseInt(el.value) || 1;
                if (current < max) {
                    el.value = current + 1;
                    el.dispatchEvent(new Event('input'));
                }
            }
        },
        decrementQty(index) {
            let el = document.getElementById('item-qty-' + index);
            if (el) {
                let current = parseInt(el.value) || 1;
                if (current > 1) {
                    el.value = current - 1;
                    el.dispatchEvent(new Event('input'));
                }
            }
        }
    }">

        {{-- Header Card --}}
        <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 p-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-primary-600 to-primary-400 text-white shadow-md shadow-primary-500/20">
                        <x-filament::icon icon="heroicon-o-arrows-right-left" class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Mutasi Barang Antar Lokasi
                            </h1>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200/60 dark:border-primary-800/60">
                                Transaksi Baru
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Pindahkan aset inventaris fisik atau stok persediaan (BHP) langsung ke ruangan lain secara aman.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <x-filament::button
                        :href="\App\Filament\Inventory\Resources\MutationResource::getUrl('index')"
                        tag="a"
                        color="gray"
                        icon="heroicon-m-arrow-left"
                    >
                        Riwayat Mutasi
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Global Validation Errors Alert --}}
        @if ($errors->any())
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-xs text-danger-800 dark:border-danger-900/50 dark:bg-danger-950/40 dark:text-danger-200">
                <div class="flex items-start gap-2.5">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-5 h-5 text-danger-600 dark:text-danger-400 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="font-semibold text-danger-900 dark:text-danger-100">Mutasi belum dapat diproses. Harap periksa:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit="review" class="space-y-6">
            {{-- Section: Tentukan Lokasi (Asal & Tujuan Side-by-Side) --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 p-5 shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/5 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 font-bold text-xs">
                            1
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Rute Perpindahan Barang</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Tentukan lokasi asal sumber barang dan lokasi tujuan penerima.</p>
                        </div>
                    </div>

                    @if ($data['source_location_id'] && $data['destination_location_id'] && $data['source_location_id'] !== $data['destination_location_id'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            Rute Tervalidasi
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Card Lokasi Asal --}}
                    <div class="rounded-xl border border-blue-200/90 bg-blue-50/25 p-4 dark:border-blue-900/50 dark:bg-blue-950/20 space-y-3.5">
                        <div class="flex items-center justify-between border-b border-blue-100 dark:border-blue-900/40 pb-2">
                            <div class="flex items-center gap-2 text-blue-800 dark:text-blue-300 font-semibold text-xs uppercase tracking-wider">
                                <x-filament::icon icon="heroicon-m-arrow-up-tray" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                <span>Lokasi Asal (Sumber)</span>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">
                                Asal
                            </span>
                        </div>

                        {{-- Gedung Asal --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Gedung Asal <span class="text-danger-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="Gedung Asal"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none"
                                    wire:model.live="data.source_campus_id"
                                >
                                    <option value="">-- Pilih Gedung Asal --</option>
                                    @foreach ($campuses as $campus)
                                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        {{-- Ruangan Asal --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Ruangan Asal <span class="text-danger-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="Ruangan Asal"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                                    wire:model.live="data.source_location_id"
                                    @disabled(! $data['source_campus_id'])
                                >
                                    <option value="">{{ $data['source_campus_id'] ? '-- Pilih Ruangan Asal --' : 'Pilih gedung asal terlebih dahulu' }}</option>
                                    @foreach ($sourceLocations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        {{-- PIC Asal --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                PIC Asal <span class="text-gray-400 font-normal">(opsional)</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="PIC Asal"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none"
                                    wire:model.live="data.source_pic_id"
                                >
                                    <option value="">-- Tanpa PIC Khusus --</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Card Lokasi Tujuan --}}
                    <div class="rounded-xl border border-emerald-200/90 bg-emerald-50/25 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20 space-y-3.5">
                        <div class="flex items-center justify-between border-b border-emerald-100 dark:border-emerald-900/40 pb-2">
                            <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-semibold text-xs uppercase tracking-wider">
                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                <span>Lokasi Tujuan (Penerima)</span>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300">
                                Tujuan
                            </span>
                        </div>

                        {{-- Gedung Tujuan --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Gedung Tujuan <span class="text-danger-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="Gedung Tujuan"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none"
                                    wire:model.live="data.destination_campus_id"
                                >
                                    <option value="">-- Pilih Gedung Tujuan --</option>
                                    @foreach ($campuses as $campus)
                                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        {{-- Ruangan Tujuan --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Ruangan Tujuan <span class="text-danger-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="Ruangan Tujuan"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                                    wire:model.live="data.destination_location_id"
                                    @disabled(! $data['destination_campus_id'])
                                >
                                    <option value="">{{ $data['destination_campus_id'] ? '-- Pilih Ruangan Tujuan --' : 'Pilih gedung tujuan terlebih dahulu' }}</option>
                                    @foreach ($destinationLocations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        {{-- PIC Tujuan --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                PIC Tujuan <span class="text-gray-400 font-normal">(opsional)</span>
                            </label>
                            <div class="relative">
                                <select
                                    aria-label="PIC Tujuan"
                                    class="w-full h-10 pl-3 pr-9 text-xs font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-lg shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer appearance-none"
                                    wire:model.live="data.destination_pic_id"
                                >
                                    <option value="">-- Tanpa PIC Khusus --</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status Route Alert --}}
                @if ($data['destination_location_id'] && $data['source_location_id'] === $data['destination_location_id'])
                    <div class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-xs text-danger-700 dark:border-danger-900/40 dark:bg-danger-950/30 dark:text-danger-300 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4 text-danger-600 dark:text-danger-400 shrink-0" />
                        <span class="font-medium">Ruangan tujuan tidak boleh sama dengan ruangan asal. Silakan pilih ruangan yang berbeda.</span>
                    </div>
                @elseif ($data['source_location_id'] && $data['destination_location_id'])
                    <div class="p-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-white/10 rounded-xl flex items-center justify-between flex-wrap gap-2 text-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Rute Mutasi:</span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-100/70 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 font-semibold">
                                {{ $campuses->firstWhere('id', $data['source_campus_id'])?->name }} · {{ $sourceLocations->firstWhere('id', $data['source_location_id'])?->name }}
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-primary-500 shrink-0" />
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-100/70 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200 font-semibold">
                                {{ $campuses->firstWhere('id', $data['destination_campus_id'])?->name }} · {{ $destinationLocations->firstWhere('id', $data['destination_location_id'])?->name }}
                            </span>
                        </div>
                        <span class="text-gray-400 text-[11px]">Stok akan otomatis berpindah saat konfirmasi</span>
                    </div>
                @endif
            </div>

            {{-- 2-Column Responsive Layout: Left (Search & Table) | Right (Cart & Confirmation) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                {{-- LEFT COLUMN: Cari & Tambah Barang (7 Cols) --}}
                <div class="lg:col-span-7 space-y-5">
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 p-5 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/5 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 font-bold text-xs">
                                    2
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Cari Barang di Ruangan Asal</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Cari aset inventaris fisik atau stok persediaan (BHP) untuk dipindahkan.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Search Input --}}
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-5 h-5" />
                            </div>
                            <input
                                id="mutation-search-input"
                                type="text"
                                wire:model.live.debounce.350ms="search"
                                placeholder="{{ $data['source_location_id'] ? 'Ketik nama barang, kode inventaris, atau barcode…' : 'Pilih ruangan asal terlebih dahulu untuk mulai mencari…' }}"
                                @disabled(! $data['source_location_id'])
                                class="w-full h-11 pl-10 pr-10 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-xl shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                            />
                            @if ($search)
                                <button
                                    type="button"
                                    wire:click="$set('search', '')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    <x-filament::icon icon="heroicon-m-x-mark" class="w-5 h-5" />
                                </button>
                            @endif
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-gray-400 px-1">
                            <span>Maksimal menampilkan 30 aset dan 30 BHP per pencarian.</span>
                            <span wire:loading wire:target="search" class="text-primary-600 dark:text-primary-400 font-medium animate-pulse">
                                Mencari data...
                            </span>
                        </div>

                        {{-- Search Results Display --}}
                        @if (! $data['source_location_id'])
                            {{-- State: Lokasi asal belum dipilih --}}
                            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center bg-gray-50/50 dark:bg-gray-800/30">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                                    <x-filament::icon icon="heroicon-o-map-pin" class="w-6 h-6" />
                                </div>
                                <h3 class="mt-3 text-xs font-semibold text-gray-700 dark:text-gray-200">Lokasi Asal Belum Dipilih</h3>
                                <p class="mt-1 text-[11px] text-gray-400 max-w-sm mx-auto">
                                    Tentukan Gedung dan Ruangan Asal pada bagian atas untuk membuka katalog barang yang tersedia di ruangan tersebut.
                                </p>
                            </div>
                        @elseif (trim($search) === '')
                            {{-- State: Belum ketik pencarian --}}
                            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center bg-gray-50/50 dark:bg-gray-800/30">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-500 dark:bg-primary-950/50">
                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-6 h-6" />
                                </div>
                                <h3 class="mt-3 text-xs font-semibold text-gray-700 dark:text-gray-200">Ketik Kata Kunci Pencarian</h3>
                                <p class="mt-1 text-[11px] text-gray-400 max-w-sm mx-auto">
                                    Ketik nama barang, nomor inventaris, atau barcode untuk mencari barang yang siap dipindahkan dari ruangan ini.
                                </p>
                            </div>
                        @elseif (count($results) === 0)
                            {{-- State: Hasil tidak ditemukan --}}
                            <div class="rounded-xl border border-dashed border-amber-300 dark:border-amber-700/50 p-8 text-center bg-amber-50/30 dark:bg-amber-950/20">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/50">
                                    <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-6 h-6" />
                                </div>
                                <h3 class="mt-3 text-xs font-semibold text-amber-900 dark:text-amber-200">Barang Tidak Ditemukan</h3>
                                <p class="mt-1 text-[11px] text-amber-700/80 dark:text-amber-400 max-w-sm mx-auto">
                                    Tidak ada aset atau BHP di ruangan ini yang cocok dengan "<span class="font-semibold">{{ $search }}</span>". Coba kata kunci lain.
                                </p>
                            </div>
                        @else
                            {{-- State: Tabel Hasil Pencarian --}}
                            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-2xs">
                                <div class="max-h-[380px] overflow-y-auto">
                                    <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10 z-10">
                                            <tr>
                                                <th scope="col" class="px-3.5 py-2.5">Barang &amp; Merek</th>
                                                <th scope="col" class="px-3 py-2.5">Kode / Barcode</th>
                                                <th scope="col" class="px-2.5 py-2.5">Jenis</th>
                                                <th scope="col" class="px-3 py-2.5">PIC</th>
                                                <th scope="col" class="px-2.5 py-2.5 text-center">Stok</th>
                                                <th scope="col" class="px-3 py-2.5 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 bg-white dark:bg-gray-900">
                                            @php
                                                $selectedKeys = array_column($items, 'key');
                                            @endphp
                                            @foreach ($results as $result)
                                                @php
                                                    $isAlreadySelected = in_array($result['key'], $selectedKeys);
                                                    $isBhp = str_starts_with($result['key'], 'supply:');
                                                @endphp
                                                <tr class="hover:bg-primary-50/40 dark:hover:bg-primary-950/20 transition-colors">
                                                    {{-- Barang & Merek --}}
                                                    <td class="px-3.5 py-3">
                                                        <div class="font-semibold text-gray-900 dark:text-white leading-tight">
                                                            {{ $result['name'] }}
                                                        </div>
                                                        @if ($result['brand'])
                                                            <div class="text-[11px] text-gray-400 mt-0.5">
                                                                {{ $result['brand'] }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Kode / Barcode --}}
                                                    <td class="px-3 py-3 font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                                        <div>{{ $result['code'] }}</div>
                                                        @if ($result['barcode'] && $result['barcode'] !== $result['code'])
                                                            <div class="text-[10px] text-gray-400">{{ $result['barcode'] }}</div>
                                                        @endif
                                                    </td>

                                                    {{-- Jenis --}}
                                                    <td class="px-2.5 py-3 whitespace-nowrap">
                                                        @if ($isBhp)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                                BHP
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                                Aset
                                                            </span>
                                                        @endif
                                                    </td>

                                                    {{-- PIC (Tampilkan PIC sesuai request user!) --}}
                                                    <td class="px-3 py-3 whitespace-nowrap">
                                                        @if ($result['pic_name'])
                                                            <div class="flex items-center gap-1.5" title="Penanggung Jawab: {{ $result['pic_name'] }}">
                                                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900/60 dark:text-primary-300">
                                                                    {{ strtoupper(substr($result['pic_name'], 0, 1)) }}
                                                                </span>
                                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate max-w-[90px]">
                                                                    {{ $result['pic_name'] }}
                                                                </span>
                                                            </div>
                                                        @else
                                                            <span class="text-gray-400 text-xs">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Stok Tersedia --}}
                                                    <td class="px-2.5 py-3 text-center whitespace-nowrap">
                                                        <span class="font-bold text-gray-800 dark:text-gray-200">
                                                            {{ $result['available'] }}
                                                        </span>
                                                        <span class="text-[10px] text-gray-400 block">unit</span>
                                                    </td>

                                                    {{-- Tombol Aksi --}}
                                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                                        @if ($isAlreadySelected)
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                                                <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5 text-emerald-500" />
                                                                Dipilih
                                                            </span>
                                                        @else
                                                            <x-filament::button
                                                                type="button"
                                                                size="xs"
                                                                icon="heroicon-m-plus"
                                                                wire:click="addItem('{{ $result['key'] }}')"
                                                                wire:loading.attr="disabled"
                                                            >
                                                                Tambah
                                                            </x-filament::button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- RIGHT COLUMN: Keranjang Mutasi & Konfirmasi (5 Cols - Sticky) --}}
                <div class="lg:col-span-5 space-y-5 lg:sticky lg:top-4">
                    
                    {{-- Card: Barang yang Dipilih (Cart) --}}
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 p-5 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/5 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400 font-bold text-xs">
                                    3
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Daftar Barang Mutasi</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Barang siap dipindahkan ke lokasi tujuan.</p>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                                <x-filament::icon icon="heroicon-m-cube" class="w-3.5 h-3.5" />
                                {{ count($items) }} Barang
                            </span>
                        </div>

                        {{-- Empty State Cart --}}
                        @if (count($items) === 0)
                            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center bg-gray-50/50 dark:bg-gray-800/30">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                                    <x-filament::icon icon="heroicon-o-shopping-bag" class="w-5 h-5" />
                                </div>
                                <h3 class="mt-2.5 text-xs font-semibold text-gray-700 dark:text-gray-200">Belum Ada Barang Dipilih</h3>
                                <p class="mt-1 text-[11px] text-gray-400">
                                    Gunakan pencarian di sebelah kiri dan klik tombol <strong>"+ Tambah"</strong> untuk memasukkan barang ke daftar mutasi ini.
                                </p>
                            </div>
                        @else
                            {{-- Items Cart List --}}
                            <div class="max-h-[300px] overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10 divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($items as $index => $item)
                                    @php
                                        $isBhp = str_starts_with($item['key'], 'supply:');
                                    @endphp
                                    <div class="p-3 bg-white dark:bg-gray-900 hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition-colors">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="space-y-0.5 flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-semibold text-xs text-gray-900 dark:text-white truncate">
                                                        {{ $item['name'] }}
                                                    </span>
                                                    @if ($isBhp)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                            BHP
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                            ASET
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="font-mono text-[10px] text-gray-400">
                                                    {{ $item['code'] }}
                                                </div>
                                            </div>

                                            {{-- Delete Button --}}
                                            <button
                                                type="button"
                                                wire:click="removeItem({{ $index }})"
                                                wire:loading.attr="disabled"
                                                title="Hapus dari daftar"
                                                class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 p-1 rounded-lg hover:bg-danger-50 dark:hover:bg-danger-950/50 transition-colors"
                                            >
                                                <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- Quantity Controller Row --}}
                                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-white/5 flex items-center justify-between text-xs">
                                            <span class="text-gray-500 text-[11px]">Kuantitas Mutasi:</span>

                                            @if ($isBhp)
                                                <div class="flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        x-on:click="decrementQty({{ $index }})"
                                                        class="flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100"
                                                    >
                                                        -
                                                    </button>
                                                    <input
                                                        id="item-qty-{{ $index }}"
                                                        type="number"
                                                        min="1"
                                                        max="{{ $item['available'] }}"
                                                        wire:model.live="items.{{ $index }}.quantity"
                                                        class="w-14 h-7 text-center text-xs font-bold bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md focus:ring-1 focus:ring-primary-500"
                                                    />
                                                    <button
                                                        type="button"
                                                        x-on:click="incrementQty({{ $index }}, {{ $item['available'] }})"
                                                        class="flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100"
                                                    >
                                                        +
                                                    </button>
                                                    <span class="text-[10px] text-gray-400">/ max {{ $item['available'] }}</span>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                    1 Unit (Fisik)
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Summary Footer --}}
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 flex items-center justify-between text-xs">
                                <span class="text-gray-500">Total Kuantitas:</span>
                                <span class="font-bold text-gray-900 dark:text-white">
                                    {{ array_sum(array_column($items, 'quantity')) }} unit
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Card: Alasan Mutasi & Tombol Eksekusi --}}
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 p-5 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 border-b border-gray-100 dark:border-white/5 pb-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 font-bold text-xs">
                                4
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Alasan Mutasi &amp; Catatan</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Keterangan resmi pemindahan barang untuk audit log.</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Alasan Pemindahan <span class="text-danger-500">*</span>
                            </label>
                            <textarea
                                wire:model.live.debounce.350ms="data.reason"
                                maxlength="5000"
                                rows="3"
                                placeholder="Tuliskan keterangan memo, keperluan operasional, atau instruksi mutasi..."
                                class="w-full text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-xl p-3 shadow-2xs hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                                required
                            ></textarea>
                            <div class="flex items-center justify-between text-[11px] text-gray-400">
                                <span>Wajib diisi sebagai catatan audit.</span>
                                <span>{{ strlen($data['reason'] ?? '') }} / 5000</span>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="pt-2 flex items-center justify-between gap-3">
                            <x-filament::button
                                :href="\App\Filament\Inventory\Resources\MutationResource::getUrl('index')"
                                tag="a"
                                color="gray"
                                icon="heroicon-m-x-mark"
                            >
                                Batal
                            </x-filament::button>

                            <x-filament::button
                                type="submit"
                                size="md"
                                icon="heroicon-m-arrow-right-circle"
                                wire:loading.attr="disabled"
                                :disabled="count($items) === 0 || ! $data['source_location_id'] || ! $data['destination_location_id'] || $data['source_location_id'] === $data['destination_location_id']"
                            >
                                Proses Mutasi
                            </x-filament::button>
                        </div>
                    </div>

                </div>
            </div>
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
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-2xl max-w-xl w-full max-h-[85vh] overflow-y-auto space-y-5 animate-in fade-in duration-200">
                    {{-- Modal Header --}}
                    <div class="flex items-start gap-3.5 border-b border-gray-200 dark:border-white/10 pb-4">
                        <div class="p-3 rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50 shrink-0">
                            <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6" />
                        </div>
                        <div>
                            <h2 id="mutation-confirm-title" class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">
                                Konfirmasi Pemindahan Barang
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Pastikan rute lokasi dan rincian barang sudah sesuai sebelum mutasi diproses secara permanen.
                            </p>
                        </div>
                    </div>

                    {{-- Visual Route Box --}}
                    <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-white/10 space-y-2">
                        <div class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rute Mutasi</div>
                        <div class="flex items-center gap-2 flex-wrap text-xs font-semibold text-gray-900 dark:text-white">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-100/70 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                {{ $campuses->firstWhere('id', $data['source_campus_id'])?->name }} · {{ $sourceLocations->firstWhere('id', $data['source_location_id'])?->name }}
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-gray-400 shrink-0" />
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-100/70 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                                {{ $campuses->firstWhere('id', $data['destination_campus_id'])?->name }} · {{ $destinationLocations->firstWhere('id', $data['destination_location_id'])?->name }}
                            </span>
                        </div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-200 dark:border-white/5 flex items-center justify-between">
                            <span>PIC Asal: <strong>{{ $employees->firstWhere('id', $data['source_pic_id'])?->name ?? '—' }}</strong></span>
                            <span>PIC Tujuan: <strong>{{ $employees->firstWhere('id', $data['destination_pic_id'])?->name ?? '—' }}</strong></span>
                        </div>
                    </div>

                    {{-- Items Staging List --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-gray-700 dark:text-gray-300">Daftar Barang ({{ count($items) }}):</span>
                            <span class="text-gray-400">Total: {{ array_sum(array_column($items, 'quantity')) }} unit</span>
                        </div>
                        <div class="max-h-40 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-gray-950/40 p-3">
                            <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-1.5 divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($items as $item)
                                    <li class="pt-1.5 first:pt-0 flex items-center justify-between">
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</span>
                                            <span class="font-mono text-[10px] text-gray-400 block">{{ $item['code'] }}</span>
                                        </div>
                                        <span class="font-bold text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded text-gray-700 dark:text-gray-300">
                                            {{ $item['quantity'] }} unit
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- Alasan Box --}}
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-400 block text-[11px] font-semibold uppercase">Catatan / Alasan:</span>
                        <p class="text-gray-900 dark:text-white font-medium mt-1 whitespace-pre-line">{{ $data['reason'] }}</p>
                    </div>

                    {{-- Alert Notice --}}
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                        <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <p>Lokasi dan stok barang akan langsung diperbarui di database secara real-time setelah tombol konfirmasi ditekan.</p>
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
    </div>
</x-filament-panels::page>
