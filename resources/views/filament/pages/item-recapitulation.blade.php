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
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-medium transition {{ !$selectedRoom && !$selectedItem ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 font-semibold ring-1 ring-primary-500/20' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-filament::icon icon="heroicon-m-academic-cap" class="w-4 h-4 shrink-0" />
                    <span>{{ $selectedCampus->name }}</span>
                </x-filament::link>
            @endif

            @if ($selectedRoom)
                <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-600 shrink-0" />
                @if ($selectedItem)
                    <x-filament::link
                        :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id, 'room' => $selectedRoom->id])"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition"
                    >
                        <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 shrink-0" />
                        <span>{{ $selectedRoom->name }}</span>
                    </x-filament::link>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 ring-1 ring-primary-500/20">
                        <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span>{{ $selectedRoom->name }}</span>
                    </span>
                @endif
            @endif

            @if ($selectedItem)
                <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-gray-400 dark:text-gray-600 shrink-0" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 ring-1 ring-primary-500/20">
                    <x-filament::icon icon="heroicon-m-tag" class="w-4 h-4 shrink-0 text-primary-600 dark:text-primary-400" />
                    <span>{{ $selectedItem }}</span>
                </span>
            @endif
        </nav>

        {{-- Single Smart Back Button (navigating up 1 level) --}}
        @if ($selectedItem)
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <x-filament::button
                    :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(array_filter(['campus' => $selectedCampus?->id, 'room' => $selectedRoom?->id, 'search' => $search, 'type' => $type]))"
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali ke Daftar Barang
                </x-filament::button>
            </div>
        @elseif ($selectedRoom)
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <x-filament::button
                    :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id])"
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali ke {{ $selectedCampus->name }}
                </x-filament::button>
            </div>
        @elseif ($selectedCampus)
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <x-filament::button
                    :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl()"
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali ke Semua Homebase
                </x-filament::button>
            </div>
        @endif
    </div>

    {{-- Executive KPI Cards (Responsive 4-Grid) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Aset --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 mb-2">
                        Unit Fisik Aset
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Aset
                    </p>
                    <h3 class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1 tracking-tight">
                        {{ number_format($assetCount) }}
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-100 dark:border-blue-900/50">
                    <x-filament::icon icon="heroicon-o-cube" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Aset ber-barcode dan tercatat individual.
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
        </div>

        {{-- Card 2: Inventaris --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300 mb-2">
                        Inventaris Kantor
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Inventaris
                    </p>
                    <h3 class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1 tracking-tight">
                        {{ number_format($inventoryCount) }}
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400 border border-violet-100 dark:border-violet-900/50">
                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Barang inventaris operasional terdata.
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-violet-500 to-purple-600"></div>
        </div>

        {{-- Card 3: Stok BHP --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 mb-2">
                        Barang Habis Pakai
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Stok BHP
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
                Akumulasi kuantitas persediaan BHP.
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-600"></div>
        </div>

        {{-- Card 4: Cakupan Lokasi --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div class="min-w-0 pr-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 mb-2">
                        Cakupan Rekap
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Lokasi Ditampilkan
                    </p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5 truncate" title="{{ $selectedRoom ? $selectedRoom->name : ($selectedCampus ? $selectedCampus->name : 'Seluruh Homebase') }}">
                        @if ($selectedRoom)
                            {{ $selectedRoom->name }}
                        @elseif ($selectedCampus)
                            {{ $selectedCampus->name }}
                        @else
                            Seluruh Homebase
                        @endif
                    </h3>
                </div>
                <div class="rounded-xl p-3 bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50 shrink-0">
                    <x-filament::icon icon="heroicon-o-map-pin" class="w-6 h-6" />
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 truncate">
                @if ($selectedRoom)
                    Homebase: <strong class="text-gray-700 dark:text-gray-300">{{ $selectedCampus->name }}</strong>
                @elseif ($selectedCampus)
                    <strong class="text-gray-700 dark:text-gray-300">{{ $groups->count() }}</strong> Ruangan terdata
                @else
                    <strong class="text-gray-700 dark:text-gray-300">{{ $groups->count() }}</strong> Homebase terdaftar
                @endif
            </p>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-gradient-to-r from-amber-500 to-orange-600"></div>
        </div>
    </div>

    {{-- Information Callout --}}
    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/70 dark:border-blue-900/40 text-xs sm:text-sm text-blue-900 dark:text-blue-200">
        <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
        <div class="leading-relaxed">
            <span class="font-semibold">Catatan Rekapitulasi:</span> Data mengikuti penempatan riil barang saat ini. Aset dan inventaris dihitung per unit fisik individual, sedangkan stok BHP merupakan akumulasi kuantitas persediaan. Barang yang dihapus (<em>soft deleted</em>) atau berstatus menunggu pemeriksaan otomatis dikecualikan.
        </div>
    </div>

    {{-- Rekapitulasi per Homebase / Rekapitulasi per Ruangan (when not on specific room or item drilldown) --}}
    @if (!$selectedRoom && !$selectedItem)
        <x-filament::section
            :heading="$selectedCampus ? 'Rekapitulasi per Ruangan di ' . $selectedCampus->name : 'Rekapitulasi per Homebase'"
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
                                Aset
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Inventaris
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
                                $groupAsset = $totals->get($group->id)?->asset_count ?? 0;
                                $groupInventory = $totals->get($group->id)?->inventory_count ?? 0;
                                $groupStock = $totals->get($group->id)?->stock_quantity ?? 0;
                                $groupTotal = $groupAsset + $groupInventory + $groupStock;
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
                                    @if ($groupAsset > 0)
                                        <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ number_format($groupAsset) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    @if ($groupInventory > 0)
                                        <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                                            {{ number_format($groupInventory) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    @if ($groupStock > 0)
                                        <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            {{ number_format($groupStock) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-gray-900 dark:text-white tabular-nums">
                                    {{ number_format($groupTotal) }}
                                </td>
                                <td class="py-3.5 px-6 text-center">
                                    <x-filament::button
                                        :href="$drillDownUrl"
                                        tag="a"
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-m-arrow-right"
                                        icon-position="after"
                                    >
                                        {{ $selectedCampus ? 'Lihat Barang' : 'Telusuri' }}
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 px-6 text-center text-gray-500 dark:text-gray-400">
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
                            $ungroupedAsset = $ungrouped->sum('asset_count');
                            $ungroupedInventory = $ungrouped->sum('inventory_count');
                            $ungroupedStock = $ungrouped->sum('stock_quantity');
                            $ungroupedTotal = $ungroupedAsset + $ungroupedInventory + $ungroupedStock;
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
                                    {{ number_format($ungroupedAsset) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedInventory) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedStock) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedTotal) }}
                                </td>
                                <td class="py-3.5 px-6 text-center text-gray-400">—</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50/90 dark:bg-gray-800/80 font-bold border-t-2 border-gray-200 dark:border-white/10 text-gray-900 dark:text-white">
                        <tr>
                            <th scope="row" class="py-3.5 px-6 text-left">Total Akumulasi</th>
                            <td class="py-3.5 px-6 text-right tabular-nums text-blue-700 dark:text-blue-300">
                                {{ number_format($assetCount) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-violet-700 dark:text-violet-300">
                                {{ number_format($inventoryCount) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ number_format($stockQuantity) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($assetCount + $inventoryCount + $stockQuantity) }}
                            </td>
                            <td class="py-3.5 px-6"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Detail Items Table (when campus or room is selected) --}}
    @if ($items)
        <x-filament::section
            :heading="$selectedItem !== null ? 'Rincian Merek: ' . $selectedItem : ($selectedRoom ? 'Daftar Barang di ' . $selectedRoom->name : 'Daftar Barang di ' . $selectedCampus->name)"
            :description="$selectedItem !== null ? 'Persebaran jumlah berdasarkan merek untuk barang ' . $selectedItem . ' di ' . ($selectedRoom ? $selectedRoom->name : $selectedCampus->name) . '.' : 'Nama barang yang sama digabung otomatis. Klik nama barang untuk melihat rincian per merek.'"
        >
            <x-slot name="headerEnd">
                <x-filament::badge color="primary" size="sm">
                    {{ number_format($items->total()) }} {{ $selectedItem !== null ? 'Merek' : 'Nama Barang' }}
                </x-filament::badge>
            </x-slot>

            {{-- Search & Filter Toolbar --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4">
                <div class="w-full sm:max-w-xs">
                    <label for="recap-search" class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Cari barang di lokasi ini
                    </label>
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            id="recap-search"
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Cari nama, merek, barcode..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    {{-- Filter Tabs --}}
                    <div role="group" aria-label="Filter jenis barang" class="inline-flex flex-wrap rounded-lg p-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-white/10 text-xs font-medium">
                        @foreach (['all' => 'Semua', 'asset' => 'Aset', 'inventory' => 'Inventaris', 'supply' => 'Stok BHP'] as $value => $label)
                            <button
                                type="button"
                                wire:click="setType('{{ $value }}')"
                                aria-pressed="{{ $type === $value ? 'true' : 'false' }}"
                                class="px-3 py-1.5 rounded-md transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 {{ $type === $value ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    @if (filled($search) || $type !== 'all')
                        <x-filament::button
                            wire:click="resetFilters"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-x-mark"
                        >
                            Reset filter
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Menampilkan {{ number_format($items->total()) }} {{ $selectedItem !== null ? 'merek' : 'nama barang' }}
                · {{ $selectedRoom?->name ?? $selectedCampus->name }}
                @if (filled($search)) · Difilter berdasarkan "{{ $search }}" @endif
            </p>

            <div class="overflow-x-auto -mx-6 -mb-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50/90 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th scope="col" class="py-3 px-6 font-semibold">
                                {{ $selectedItem !== null ? 'Merek' : 'Nama Barang' }}
                            </th>
                            <th scope="col" class="py-3 px-6 font-semibold text-right">
                                Aset
                            </th>
                            <th scope="col" class="py-3 px-6 font-semibold text-right">
                                Inventaris
                            </th>
                            <th scope="col" class="py-3 px-6 font-semibold text-right">
                                Stok BHP
                            </th>
                            <th scope="col" class="py-3 px-6 font-semibold text-right">
                                Total
                            </th>
                            <th scope="col" class="py-3 px-6 font-semibold">
                                Satuan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($items as $entry)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition-colors group">
                                <th scope="row" class="py-3.5 px-6 font-semibold text-gray-900 dark:text-white">
                                    @if ($selectedItem === null)
                                        <x-filament::link
                                            :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(array_filter(['campus' => $selectedCampus->id, 'room' => $selectedRoom?->id, 'item' => $entry->item_name, 'search' => $search, 'type' => $type]))"
                                            class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-semibold group-hover:underline"
                                        >
                                            <span>{{ $entry->item_name }}</span>
                                            <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400 shrink-0" />
                                        </x-filament::link>
                                    @else
                                        <span class="text-gray-900 dark:text-white font-medium">
                                            {{ filled($entry->brand) ? $entry->brand : 'Tanpa merek' }}
                                        </span>
                                    @endif
                                </th>
                                <td class="py-3.5 px-6 text-right tabular-nums text-sm font-medium">
                                    @if ($entry->asset_count > 0)
                                        <span class="text-blue-700 dark:text-blue-300 font-semibold">{{ number_format($entry->asset_count) }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right tabular-nums text-sm font-medium">
                                    @if ($entry->inventory_count > 0)
                                        <span class="text-violet-700 dark:text-violet-300 font-semibold">{{ number_format($entry->inventory_count) }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right tabular-nums text-sm font-medium">
                                    @if ($entry->stock_quantity > 0)
                                        <span class="text-emerald-700 dark:text-emerald-300 font-semibold">{{ number_format($entry->stock_quantity) }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right tabular-nums font-bold text-gray-900 dark:text-white">
                                    {{ number_format($entry->quantity) }}
                                </td>
                                <td class="py-3.5 px-6 text-gray-600 dark:text-gray-300 text-xs">
                                    <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 dark:bg-white/10 font-medium">
                                        {{ $entry->unit_name }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 px-6 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                        <p class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ filled($search) || $type !== 'all' ? 'Tidak ada barang yang cocok dengan kriteria filter.' : 'Belum ada barang di lokasi ini.' }}
                                        </p>
                                        @if (filled($search) || $type !== 'all')
                                            <p class="text-xs text-gray-400 mt-1">Coba kata kunci lain atau reset filter untuk melihat semua barang.</p>
                                            <div class="mt-3">
                                                <x-filament::button wire:click="resetFilters" size="sm" color="gray">
                                                    Reset Filter
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
