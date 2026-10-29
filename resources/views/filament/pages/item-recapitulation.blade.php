<x-filament-panels::page>
    <x-filament::section heading="Lihat jumlah barang berdasarkan lokasi">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            @if ($selectedRoom)
                Anda sedang melihat barang di {{ $selectedRoom->name }}. Gunakan pencarian atau pilih jenis barang untuk mempersempit daftar.
            @elseif ($selectedCampus)
                Pilih ruangan untuk melihat isinya, atau cari barang pada daftar di bawah rekap ruangan.
            @else
                Mulai dengan memilih homebase pada tabel di bawah. Setelah itu, pilih ruangan atau cari barang yang Anda butuhkan.
            @endif
        </p>
        <ol aria-label="Urutan penelusuran barang" class="mt-3 flex flex-wrap gap-3 text-sm text-gray-700 dark:text-gray-200">
            <li>1. Pilih homebase</li>
            <li>2. Pilih ruangan</li>
            <li>3. Temukan barang</li>
        </ol>
    </x-filament::section>
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
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-uturn-left"
                >
                    Kembali ke Semua Homebase
                </x-filament::button>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Card 1: Barang Individual --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 mb-2">
                        Aset & inventaris
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
                        Barang habis pakai
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Jumlah stok BHP
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

        {{-- Card 3: Lokasi yang ditampilkan --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 mb-2">
                        Cakupan rekap
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Lokasi yang ditampilkan
                    </p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5 break-words">
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
            <span class="font-semibold">Catatan Data:</span> Homebase menunjukkan lokasi barang saat ini. BHP berarti barang habis pakai. Jumlah stok BHP menggabungkan berbagai satuan; lihat rincian barang untuk mengetahui satuannya. Barang yang sudah dihapus atau masih menunggu pemeriksaan tidak dihitung. Pencarian dan filter jenis hanya berlaku pada daftar barang, bukan angka ringkasan di atas.
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
                                Jumlah stok BHP
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
                                        size="sm"
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

                    <label for="recap-search" class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-white">Cari barang di lokasi ini</label>
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            id="recap-search"
                            aria-describedby="recap-search-help"
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Nama barang, barcode, atau merek"
                        />

                    <label for="recap-search" class="sr-only">Cari barang</label>
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input id="recap-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama, merek, atau barcode..." />
                    </x-filament::input.wrapper>
                    <p id="recap-search-help" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Hasil diperbarui saat Anda mengetik.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Type Filter Buttons --}}
                    <div role="group" aria-label="Filter jenis barang" class="inline-flex flex-wrap rounded-lg p-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-white/10 text-xs font-medium">
                        <button
                            type="button"
                            wire:click="setType('all')"
                            aria-pressed="{{ $type === 'all' ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            class="min-h-11 px-3 py-2 rounded-md transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 {{ $type === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Semua
                        </button>
                        <button
                            type="button"
                            wire:click="setType('asset')"
                            aria-pressed="{{ $type === 'asset' ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            class="min-h-11 px-3 py-2 rounded-md transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 {{ $type === 'asset' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Barang Individual
                        </button>
                        <button
                            type="button"
                            wire:click="setType('supply')"
                            aria-pressed="{{ $type === 'supply' ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            class="min-h-11 px-3 py-2 rounded-md transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 {{ $type === 'supply' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >
                            Stok BHP
                        </button>
                    </div>

                    @if (filled($search) || $type !== 'all')
                        <x-filament::button
                            wire:click="resetFilters"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-x-mark"
                        >
                            Hapus filter
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <p role="status" aria-live="polite" class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                <span wire:loading wire:target="search,setType,resetFilters,gotoPage,nextPage,previousPage">Memperbarui daftar barang…</span>
                <span wire:loading.remove wire:target="search,setType,resetFilters,gotoPage,nextPage,previousPage">{{ number_format($items->total()) }} barang sesuai pencarian dan jenis yang dipilih.</span>
            </p>
            <div role="region" aria-label="Rincian barang, geser untuk melihat semua kolom" tabindex="0" class="overflow-x-auto -mx-6 -mb-6 focus-visible:outline-2 focus-visible:outline-primary-600">
=======
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
                            <tr>
                                <td colspan="6" class="py-10 px-6 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                        <p class="font-medium text-gray-700 dark:text-gray-300">{{ filled($search) || $type !== 'all' ? 'Tidak ada barang yang cocok.' : 'Belum ada barang di lokasi ini.' }}</p>
                                        @if (filled($search) || $type !== 'all')
                                            <p class="text-xs text-gray-400 mt-1">Coba kata kunci lain atau hapus filter untuk melihat semua barang di lokasi ini.</p>
                                            <div class="mt-3">
                                                <x-filament::button wire:click="resetFilters" size="sm" color="gray">
                                                    Hapus Filter Pencarian
                                                </x-filament::button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
=======
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
