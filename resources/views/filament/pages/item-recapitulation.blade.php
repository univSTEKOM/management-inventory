<x-filament-panels::page>
    {{-- Breadcrumb & Scope Navigation Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-xs">
        <nav aria-label="Navigasi rekapitulasi" class="flex items-center flex-wrap gap-2 text-sm">
            <x-filament::link
                :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl()"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-medium transition {{ !$selectedCampus ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 font-semibold ring-1 ring-primary-500/20' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
            >
                <x-filament::icon icon="heroicon-m-building-office-2" class="w-4 h-4 shrink-0" />
                <span>Semua Homebase</span>
            </x-filament::link>

            @if ($selectedCampus)
                <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-600 shrink-0" />
                <x-filament::link
                    :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id])"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-medium transition {{ !$selectedRoom ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 font-semibold ring-1 ring-primary-500/20' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-filament::icon icon="heroicon-m-academic-cap" class="w-4 h-4 shrink-0" />
                    <span>{{ $selectedCampus->name }}</span>
                </x-filament::link>
            @endif

            @if ($selectedRoom)
                <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-600 shrink-0" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 ring-1 ring-primary-500/20">
                    <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 shrink-0 text-primary-600 dark:text-primary-400" />
                    <span>{{ $selectedRoom->name }}</span>
                </span>
            @endif
        </nav>

        @if ($selectedCampus || $selectedRoom)
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <x-filament::button
                    :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl()"
                    tag="a"
                    size="xs"
                    color="gray"
                    icon="heroicon-m-arrow-uturn-left"
                >
                    Kembali ke Semua Homebase
                </x-filament::button>
            </div>
        @endif
    </div>

    {{-- Ringkasan Barang: Executive KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Card 1: Barang Individual --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 mb-2">
                        Unit Fisik Unik
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Barang Individual
                    </p>
                    <h3 class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1 tracking-tight">
                        {{ number_format($individualCount) }}
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-100 dark:border-blue-900/50">
                    <x-filament::icon icon="heroicon-o-cube" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Aset tetap & inventaris ber-barcode terdata pada cakupan ini.
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
        </div>

        {{-- Card 2: Stok BHP --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 mb-2">
                        Stok Consumable
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Kuantitas Stok BHP
                    </p>
                    <h3 class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1 tracking-tight">
                        {{ number_format($stockQuantity) }}
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <x-filament::icon icon="heroicon-o-circle-stack" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Akumulasi seluruh jumlah kuantitas stok barang habis pakai.
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-600"></div>
        </div>

        {{-- Card 3: Lingkup Penempatan --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 mb-2">
                        Cakupan Filter
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Lingkup Penempatan
                    </p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5 truncate max-w-[220px]">
                        @if ($selectedRoom)
                            {{ $selectedRoom->name }}
                        @elseif ($selectedCampus)
                            {{ $selectedCampus->name }}
                        @else
                            Seluruh Homebase
                        @endif
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <x-filament::icon icon="heroicon-o-map-pin" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                @if ($selectedRoom)
                    Homebase: <strong class="text-gray-700 dark:text-gray-300">{{ $selectedCampus->name }}</strong>
                @elseif ($selectedCampus)
                    <strong class="text-gray-700 dark:text-gray-300">{{ $groups->count() }}</strong> Ruangan terdaftar di homebase ini
                @else
                    <strong class="text-gray-700 dark:text-gray-300">{{ $groups->count() }}</strong> Homebase terdaftar di sistem
                @endif
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-amber-500 to-orange-600"></div>
        </div>
    </div>

    {{-- Information Callout --}}
    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/70 dark:border-blue-900/40 text-xs sm:text-sm text-blue-900 dark:text-blue-200">
        <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
        <div class="leading-relaxed">
            <span class="font-semibold">Catatan Data:</span> Homebase mengikuti penempatan riil barang saat ini. Kuantitas stok BHP merupakan total akumulasi jumlah stok dari berbagai satuan. Barang yang dihapus (<em>soft deleted</em>) atau masih dalam antrean pemeriksaan otomatis tidak termasuk dalam perhitungan rekapitulasi.
        </div>
    </div>

    {{-- Rekapitulasi per Homebase / Rekapitulasi per Ruangan (when not on specific room) --}}
    @unless ($selectedRoom)
        <x-filament::section
            :heading="$selectedCampus ? 'Rekapitulasi per Ruangan' : 'Rekapitulasi per Homebase'"
            :description="$selectedCampus ? 'Klik nama ruangan atau tombol telusuri untuk melihat rincian barang spesifik di ruangan tersebut.' : 'Klik nama homebase atau tombol telusuri untuk melihat sebaran per ruangan dan seluruh barang di kampus terkait.'"
        >
            <x-slot name="headerEnd">
                <x-filament::badge color="gray" size="sm">
                    {{ $groups->count() }} {{ $selectedCampus ? 'Ruangan' : 'Homebase' }}
                </x-filament::badge>
            </x-slot>

            <div class="overflow-x-auto -mx-6 -my-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50/90 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th scope="col" class="py-3.5 px-6 font-semibold">
                                {{ $selectedCampus ? 'Ruangan' : 'Homebase' }}
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Barang Individual
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Kuantitas Stok BHP
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Total Akumulasi
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-center w-36">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($groups as $group)
                            @php
                                $groupIndividual = $totals->get($group->id)?->individual_count ?? 0;
                                $groupStock = $totals->get($group->id)?->stock_quantity ?? 0;
                                $groupTotal = $groupIndividual + $groupStock;
                                $drillDownUrl = \App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(
                                    $selectedCampus
                                        ? ['campus' => $selectedCampus->id, 'room' => $group->id]
                                        : ['campus' => $group->id]
                                );
                            @endphp
                            <tr class="hover:bg-primary-50/40 dark:hover:bg-primary-950/20 transition-colors group">
                                <th scope="row" class="py-3.5 px-6 font-medium text-gray-900 dark:text-white">
                                    <a href="{{ $drillDownUrl }}" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold group-hover:underline">
                                        <x-filament::icon
                                            :icon="$selectedCampus ? 'heroicon-m-map-pin' : 'heroicon-m-building-office-2'"
                                            class="w-4 h-4 text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400 shrink-0"
                                        />
                                        <span>{{ $group->name }}</span>
                                    </a>
                                </th>
                                <td class="py-3.5 px-6 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $groupIndividual > 0 ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($groupIndividual) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $groupStock > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($groupStock) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-gray-900 dark:text-white tabular-nums">
                                    {{ number_format($groupTotal) }}
                                </td>
                                <td class="py-3.5 px-6 text-center">
                                    <x-filament::button
                                        :href="$drillDownUrl"
                                        tag="a"
                                        size="xs"
                                        color="gray"
                                        icon="heroicon-m-arrow-right"
                                        icon-position="after"
                                    >
                                        Telusuri
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-6 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-filament::icon icon="heroicon-o-folder-open" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                        <p class="font-medium">Belum ada {{ $selectedCampus ? 'ruangan' : 'homebase' }} yang terdata.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        {{-- Ungrouped row (if any) --}}
                        @php
                            $ungrouped = $totals->except($groups->modelKeys());
                            $ungroupedIndividual = $ungrouped->sum('individual_count');
                            $ungroupedStock = $ungrouped->sum('stock_quantity');
                        @endphp
                        @if ($ungrouped->isNotEmpty())
                            <tr class="bg-amber-50/40 dark:bg-amber-950/20 border-t border-amber-200/50 dark:border-amber-900/40">
                                <th scope="row" class="py-3.5 px-6 font-medium text-amber-900 dark:text-amber-200">
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4 text-amber-500 shrink-0" />
                                        <span>Tanpa {{ $selectedCampus ? 'ruangan' : 'homebase' }} aktif yang sesuai</span>
                                    </div>
                                </th>
                                <td class="py-3.5 px-6 text-right font-medium text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedIndividual) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedStock) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedIndividual + $ungroupedStock) }}
                                </td>
                                <td class="py-3.5 px-6 text-center text-gray-400">—</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50/90 dark:bg-gray-800/80 font-bold border-t-2 border-gray-200 dark:border-white/10 text-gray-900 dark:text-white">
                        <tr>
                            <th scope="row" class="py-3.5 px-6 text-left">Total Akumulasi</th>
                            <td class="py-3.5 px-6 text-right tabular-nums text-blue-700 dark:text-blue-300">
                                {{ number_format($individualCount) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ number_format($stockQuantity) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($individualCount + $stockQuantity) }}
                            </td>
                            <td class="py-3.5 px-6"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    @endunless

    {{-- Detail Items Table (when campus or room is selected) --}}
    @if ($items)
        <x-filament::section
            :heading="$selectedRoom ? 'Rincian Barang di Ruangan ' . $selectedRoom->name : 'Daftar Seluruh Barang di Homebase ' . $selectedCampus->name"
            :description="$selectedRoom ? 'Menampilkan seluruh aset individual dan stok BHP yang teralokasi pada ruangan ini.' : 'Menampilkan seluruh barang dan persediaan BHP yang berada di lingkungan kampus ini.'"
        >
            <x-slot name="headerEnd">
                <x-filament::badge color="primary" size="sm">
                    {{ number_format($items->total()) }} Item Ditemukan
                </x-filament::badge>
            </x-slot>

            {{-- Search and Filter Toolbar --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4">
                <div class="w-full sm:max-w-xs">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Cari nama barang, barcode, brand..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Type Filter Buttons --}}
                    <div class="inline-flex rounded-lg p-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-white/10 text-xs font-medium">
                        <button
                            type="button"
                            wire:click="setType('all')"
                            class="px-2.5 py-1 rounded-md transition {{ $type === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Semua
                        </button>
                        <button
                            type="button"
                            wire:click="setType('asset')"
                            class="px-2.5 py-1 rounded-md transition {{ $type === 'asset' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Barang Individual
                        </button>
                        <button
                            type="button"
                            wire:click="setType('supply')"
                            class="px-2.5 py-1 rounded-md transition {{ $type === 'supply' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Stok BHP
                        </button>
                    </div>

                    @if (filled($search) || $type !== 'all')
                        <x-filament::button
                            wire:click="resetFilters"
                            size="xs"
                            color="gray"
                            icon="heroicon-m-x-mark"
                        >
                            Reset
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto -mx-6 -mb-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50/90 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th scope="col" class="py-3 px-6 font-semibold">Barang & Spesifikasi</th>
                            <th scope="col" class="py-3 px-6 font-semibold">Jenis / Tipe</th>
                            <th scope="col" class="py-3 px-6 font-semibold">Barcode / SKU</th>
                            <th scope="col" class="py-3 px-6 font-semibold">Ruangan</th>
                            <th scope="col" class="py-3 px-6 font-semibold text-right">Jumlah & Satuan</th>
                            <th scope="col" class="py-3 px-6 font-semibold text-center">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($items as $item)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition-colors">
                                <td class="py-3.5 px-6">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $item->item_name }}
                                    </div>
                                    @if ($item->brand || $item->category_name)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1.5">
                                            @if ($item->brand)
                                                <span>{{ $item->brand }}</span>
                                            @endif
                                            @if ($item->brand && $item->category_name)
                                                <span>•</span>
                                            @endif
                                            @if ($item->category_name)
                                                <span>{{ $item->category_name }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6">
                                    @if ($item->row_type === 'asset')
                                        <x-filament::badge color="info" size="sm" icon="heroicon-m-cube">
                                            {{ $item->classification_name ?: 'Aset Individual' }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="success" size="sm" icon="heroicon-m-circle-stack">
                                            {{ $item->classification_name ?: 'Stok BHP' }}
                                        </x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6">
                                    @if ($item->barcode)
                                        <span class="inline-flex font-mono text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 font-medium">
                                            {{ $item->barcode }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-gray-700 dark:text-gray-300">
                                    @if ($item->location_name)
                                        <div class="inline-flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-m-map-pin" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                            <span>{{ $item->location_name }}</span>
                                        </div>
                                    @else
                                        <span class="italic text-gray-400">Tanpa ruangan</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right tabular-nums">
                                    <span class="font-bold text-gray-900 dark:text-white">
                                        {{ number_format($item->quantity) }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                                        {{ $item->unit_name ?: 'Unit' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-center">
                                    @php
                                        $kondisi = $item->kondisi ?? null;
                                        $kondisiLabel = match ($kondisi) {
                                            'good' => 'Baik',
                                            'minor_damage' => 'Rusak Ringan',
                                            'major_damage' => 'Rusak Berat',
                                            default => $kondisi ? ucfirst(str_replace('_', ' ', $kondisi)) : '—',
                                        };
                                        $kondisiColor = match ($kondisi) {
                                            'good' => 'success',
                                            'minor_damage' => 'warning',
                                            'major_damage' => 'danger',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <x-filament::badge :color="$kondisiColor" size="sm">
                                        {{ $kondisiLabel }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 px-6 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                        <p class="font-medium text-gray-700 dark:text-gray-300">Belum ada barang pada penempatan ini.</p>
                                        @if (filled($search) || $type !== 'all')
                                            <p class="text-xs text-gray-400 mt-1">Tidak ada barang yang cocok dengan kriteria pencarian.</p>
                                            <div class="mt-3">
                                                <x-filament::button wire:click="resetFilters" size="xs" color="gray">
                                                    Hapus Filter Pencarian
                                                </x-filament::button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-white/10">
                {{ $items->links() }}
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
