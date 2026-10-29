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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach (['Aset' => $assetCount, 'Inventaris' => $inventoryCount, 'Stok BHP' => $stockQuantity] as $label => $count)
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <h3 class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($count) }}</h3>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $label === 'Stok BHP' ? 'Kuantitas stok; dapat mencakup beberapa satuan.' : 'Unit dengan klasifikasi '.$label.' di lokasi ini.' }}</p>
            </div>
        @endforeach
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
                                Aset
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Inventaris
                            </th>
                            <th scope="col" class="py-3.5 px-6 font-semibold text-right">
                                Kuantitas Stok BHP
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
                                $groupStock = $totals->get($group->id)?->stock_quantity ?? 0;
                                $groupInventory = $totals->get($group->id)?->inventory_count ?? 0;
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
                                    <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $groupAsset > 0 ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($groupAsset) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-gray-900 dark:text-white tabular-nums">
                                    {{ number_format($groupInventory) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    <span class="inline-flex items-center justify-end px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $groupStock > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($groupStock) }}
                                    </span>
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
                            $ungroupedAsset = $ungrouped->sum('asset_count');
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
                                    {{ number_format($ungroupedAsset) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-bold text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungrouped->sum('inventory_count')) }}
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium text-amber-900 dark:text-amber-200 tabular-nums">
                                    {{ number_format($ungroupedStock) }}
                                </td>
                                <td class="py-3.5 px-6 text-center text-gray-400">—</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50/90 dark:bg-gray-800/80 font-bold border-t-2 border-gray-200 dark:border-white/10 text-gray-900 dark:text-white">
                        <tr>
                            <th scope="row" class="py-3.5 px-6 text-left">Total</th>
                            <td class="py-3.5 px-6 text-right tabular-nums text-blue-700 dark:text-blue-300">
                                {{ number_format($assetCount) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($inventoryCount) }}
                            </td>
                            <td class="py-3.5 px-6 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ number_format($stockQuantity) }}
                            </td>
                            <td class="py-3.5 px-6"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    @endunless

    @if ($items)
        <x-filament::section
            :heading="$selectedItem !== null ? 'Rekap Merek — '.$selectedItem : 'Rekap per Nama Barang'"
            :description="$selectedItem !== null ? 'Jumlah per merek untuk barang dan lokasi yang dipilih.' : 'Nama barang yang sama digabung. Klik nama barang untuk melihat total per merek.'"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                @if ($selectedItem !== null)
                    <x-filament::button tag="a" color="gray" icon="heroicon-m-arrow-left"
                        :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id, 'room' => $selectedRoom?->id, 'search' => $search, 'type' => $type])">
                        Kembali ke nama barang
                    </x-filament::button>
                @endif
                <div class="w-full sm:max-w-xs">
                    <label for="recap-search" class="sr-only">Cari barang</label>
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input id="recap-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama, merek, atau barcode..." />
                    </x-filament::input.wrapper>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Filter jenis barang">
                    @foreach (['all' => 'Semua', 'asset' => 'Aset', 'inventory' => 'Inventaris', 'supply' => 'Stok BHP'] as $value => $label)
                        <x-filament::button wire:click="setType('{{ $value }}')" :color="$type === $value ? 'primary' : 'gray'" size="sm" :aria-pressed="$type === $value ? 'true' : 'false'">{{ $label }}</x-filament::button>
                    @endforeach
                    @if (filled($search) || $type !== 'all')
                        <x-filament::button wire:click="resetFilters" color="gray" size="sm">Reset filter</x-filament::button>
                    @endif
                </div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                {{ number_format($items->total()) }} {{ $selectedItem !== null ? 'merek' : 'nama barang' }}
                · {{ $selectedRoom?->name ?? $selectedCampus->name }}
                @if (filled($search)) · Jumlah mengikuti hasil pencarian. @endif
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="p-4">{{ $selectedItem !== null ? 'Merek' : 'Nama barang' }}</th>
                            <th scope="col" class="p-4 text-right">Aset</th>
                            <th scope="col" class="p-4 text-right">Inventaris</th>
                            <th scope="col" class="p-4 text-right">Stok BHP</th>
                            <th scope="col" class="p-4 text-right">Jumlah total</th>
                            <th scope="col" class="p-4">Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($items as $entry)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <th scope="row" class="p-4 font-semibold">
                                    @if ($selectedItem === null)
                                        <x-filament::link :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id, 'room' => $selectedRoom?->id, 'item' => $entry->item_name, 'search' => $search, 'type' => $type])">
                                            {{ $entry->item_name }} <span aria-hidden="true">→</span>
                                        </x-filament::link>
                                    @else
                                        {{ filled($entry->brand) ? $entry->brand : 'Tanpa merek' }}
                                    @endif
                                </th>
                                <td class="p-4 text-right tabular-nums">{{ number_format($entry->asset_count) }}</td>
                                <td class="p-4 text-right tabular-nums">{{ number_format($entry->inventory_count) }}</td>
                                <td class="p-4 text-right tabular-nums">{{ number_format($entry->stock_quantity) }}</td>
                                <td class="p-4 text-right tabular-nums font-bold">{{ number_format($entry->quantity) }}</td>
                                <td class="p-4">{{ $entry->unit_name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-gray-500">Tidak ada barang yang sesuai pada lokasi ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Jumlah total mencakup semua klasifikasi, termasuk BHP yang dicatat per unit. Jika satuannya beragam, jumlah merupakan gabungan kuantitas, bukan satu satuan yang sama.</p>
            <div class="mt-4">{{ $items->links() }}</div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
