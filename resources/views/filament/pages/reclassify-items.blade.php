<x-filament-panels::page>
    @php
        $classificationClasses = [
            'aset' => [
                'name' => 'Aset',
                'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
                'dot' => 'bg-blue-500',
            ],
            'inventaris' => [
                'name' => 'Inventaris',
                'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                'dot' => 'bg-emerald-500',
            ],
            'barang-habis-pakai' => [
                'name' => 'BHP (Barang Habis Pakai)',
                'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                'dot' => 'bg-amber-500',
            ],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Section 1: Konfigurasi Parameter Reklasifikasi --}}
        <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl shadow-xs space-y-6">
            <div class="flex items-center gap-3 border-b border-gray-100 dark:border-white/5 pb-4">
                <div class="p-2.5 rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50 shrink-0">
                    <x-filament::icon icon="heroicon-o-arrows-right-left" class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">
                        Konfigurasi Arah Reklasifikasi
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tentukan klasifikasi asal, klasifikasi tujuan baru, serta penyesuaian ruangan penempatan.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-12 items-center">
                {{-- Klasifikasi Asal --}}
                <div class="lg:col-span-5 space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Klasifikasi Asal <span class="text-danger-500">*</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-slot name="prefix">
                            <x-filament::icon icon="heroicon-m-arrow-up-tray" class="w-4 h-4 text-gray-400" />
                        </x-slot>
                        <x-filament::input.select wire:model.live="source">
                            <option value="">Pilih Klasifikasi Asal</option>
                            @foreach ($labels as $slug => $label)
                                <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                {{-- Visual Arrow Divider --}}
                <div class="hidden lg:flex lg:col-span-2 justify-center items-center pt-6">
                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 flex items-center justify-center text-gray-400 dark:text-gray-500 shadow-xs">
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>

                {{-- Klasifikasi Tujuan --}}
                <div class="lg:col-span-5 space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Klasifikasi Tujuan <span class="text-danger-500">*</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-slot name="prefix">
                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-gray-400" />
                        </x-slot>
                        <x-filament::input.select wire:model.live="destination">
                            <option value="">Pilih Klasifikasi Tujuan</option>
                            @foreach ($labels as $slug => $label)
                                <option value="{{ $slug }}" @disabled($slug === $source)>{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                {{-- Ruangan Tujuan (jika ada konversi BHP) --}}
                @if ($rows && ($supply || $destination === 'barang-habis-pakai'))
                    <div class="lg:col-span-6 space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Ruangan Tujuan Baru
                        </label>
                        <x-filament::input.wrapper>
                            <x-slot name="prefix">
                                <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 text-gray-400" />
                            </x-slot>
                            <x-filament::input.select wire:model.live="locationId">
                                <option value="">Pertahankan ruangan asal masing-masing</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">
                                        {{ $location->campus?->name }} — {{ $location->name }}
                                    </option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Pilih ruangan jika barang akan dikonsolidasikan ke satu tempat penyimpanan baru.
                        </p>
                    </div>
                @endif

                {{-- Catatan / Alasan --}}
                <div class="{{ $rows && ($supply || $destination === 'barang-habis-pakai') ? 'lg:col-span-6' : 'lg:col-span-12' }} space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Alasan Reklasifikasi <span class="text-xs font-normal text-gray-400">(Opsional)</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-slot name="prefix">
                            <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-gray-400" />
                        </x-slot>
                        <x-filament::input
                            wire:model="reason"
                            maxlength="5000"
                            placeholder="Contoh: Koreksi pembukuan aset / reklasifikasi inventaris ke aset tetap"
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 text-danger-700 dark:text-danger-300 text-sm space-y-1">
                    <div class="flex items-center gap-2 font-semibold">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                        <span>Terdapat kesalahan validasi:</span>
                    </div>
                    <ul class="list-disc list-inside text-xs space-y-0.5 pl-6">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Section 2: Pemilihan Barang --}}
        @if ($rows)
            <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl shadow-xs space-y-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>Daftar Barang yang Tersedia</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800">
                                {{ $rows->total() }} barang
                            </span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Centang barang yang hendak dikonversi ke klasifikasi <strong>{{ $labels[$destination] ?? $destination }}</strong>.
                        </p>
                    </div>

                    <div class="w-full md:w-80">
                        <x-filament::input.wrapper>
                            <x-slot name="prefix">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-4 h-4 text-gray-400" />
                            </x-slot>
                            <x-filament::input
                                wire:model.live.debounce.350ms="search"
                                placeholder="Cari nama, brand, atau kode..."
                                aria-label="Cari nama atau barcode"
                            />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                {{-- Edukasi Penyatuan/Total Barang jika tujuan BHP --}}
                @if (!$supply && $destination === 'barang-habis-pakai')
                    <div class="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2.5">
                        <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div class="space-y-0.5">
                            <p class="font-bold text-blue-800 dark:text-blue-200">
                                Mengapa barang muncul per baris (INV...)?
                            </p>
                            <p>
                                Barang di klasifikasi <strong>Inventaris</strong> dicatat per unit fisik unik (masing-masing punya stiker/No. Inventaris INV sendiri). Centang seluruh unit kipas angin yang ingin Anda gabung, lalu klik tombol <strong>"Tampilkan Pratinjau Reklasifikasi"</strong> di bawah. Sistem akan <strong>otomatis melebur dan menotalkan seluruh unit menjadi 1 saldo stok BHP</strong>!
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Action Bar: Select All / Deselect All --}}
                @php
                    $currentPageIds = $rows->pluck('id')->all();
                @endphp
                <div class="flex items-center justify-between gap-3 text-xs border-b border-gray-100 dark:border-white/5 pb-2">
                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            wire:click="selectAllVisible({{ json_encode($currentPageIds) }})"
                            class="font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline inline-flex items-center gap-1"
                        >
                            <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5" />
                            <span>Pilih Semua di Halaman Ini ({{ count($currentPageIds) }})</span>
                        </button>
                        <span class="text-gray-300 dark:text-gray-700">·</span>
                        <button
                            type="button"
                            wire:click="deselectAll"
                            class="font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:underline inline-flex items-center gap-1"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="w-3.5 h-3.5" />
                            <span>Batal Pilih Semua</span>
                        </button>
                    </div>

                    <span class="font-medium text-gray-500 dark:text-gray-400">
                        Terpilih: <strong class="text-primary-600 dark:text-primary-400">{{ count($selected) }}</strong> dari {{ $rows->total() }} barang
                    </span>
                </div>

                {{-- Tabel Barang --}}
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                            <tr>
                                <th scope="col" class="w-12 px-4 py-3 text-center">Pilih</th>
                                <th scope="col" class="px-4 py-3">Barang & Spesifikasi</th>
                                <th scope="col" class="px-4 py-3">Kode / No. Inventaris</th>
                                <th scope="col" class="px-4 py-3">Lokasi Saat Ini</th>
                                @if ($supply)
                                    <th scope="col" class="px-4 py-3 w-44">Jumlah Reklasifikasi</th>
                                @endif
                            </tr>
                        </thead>

                        @if ($supply)
                            {{-- Jika sumbernya BHP, tampilkan baris per saldo stok --}}
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @forelse ($rows as $row)
                                    <tr
                                        class="hover:bg-gray-50/80 dark:hover:bg-white/[0.02] transition {{ in_array((string)$row->id, array_map('strval', $selected), true) ? 'bg-primary-50/40 dark:bg-primary-950/20' : '' }}"
                                        wire:key="item-{{ $source }}-{{ $row->id }}"
                                    >
                                        <td class="px-4 py-3.5 text-center">
                                            <input
                                                type="checkbox"
                                                wire:model="selected"
                                                value="{{ $row->id }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                            >
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="space-y-1">
                                                <div class="font-semibold text-gray-900 dark:text-white">
                                                    {{ $row->name }}
                                                </div>
                                                <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        {{ $row->brand ?: 'Tanpa Merk' }}
                                                    </span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                        {{ $row->category?->name ?? 'Tanpa Kategori' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <code class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono text-xs font-semibold">
                                                {{ $row->master_barcode }}
                                            </code>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                <x-filament::icon icon="heroicon-m-map-pin" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                <span>
                                                    {{ $row->location?->campus?->name ?? '—' }} / <strong class="text-gray-800 dark:text-gray-200">{{ $row->location?->name ?: '—' }}</strong>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="space-y-1">
                                                <x-filament::input.wrapper>
                                                    <x-filament::input
                                                        type="number"
                                                        min="1"
                                                        :max="$row->quantity"
                                                        wire:model="quantities.{{ $row->id }}"
                                                        placeholder="1"
                                                    />
                                                </x-filament::input.wrapper>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block">
                                                    Tersedia: {{ $row->quantity }} {{ $row->unit }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-8 h-8 text-gray-400" />
                                                <p class="text-sm font-medium">Tidak ada barang BHP yang memenuhi syarat.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        @else
                            {{-- Jika sumbernya Inventaris/Aset: KONSOLIDASI & GABUNG BARANG YANG IDENTIK --}}
                            @php
                                $groupedRows = $rows->getCollection()->groupBy(function ($asset) {
                                    return mb_strtolower(trim($asset->name)) . '|' . mb_strtolower(trim((string)$asset->brand)) . '|' . $asset->category_id . '|' . $asset->location_id;
                                });
                            @endphp

                            @forelse ($groupedRows as $groupKey => $groupItems)
                                @php
                                    $firstItem = $groupItems->first();
                                    $groupItemIds = $groupItems->pluck('id')->map(fn($id) => (string)$id)->all();
                                    $selectedCountInGroup = count(array_intersect($groupItemIds, array_map('strval', $selected)));
                                    $isAllSelected = $selectedCountInGroup === count($groupItemIds) && count($groupItemIds) > 0;
                                    $isPartiallySelected = $selectedCountInGroup > 0 && !$isAllSelected;
                                @endphp
                                <tbody
                                    class="divide-y divide-gray-200 dark:divide-white/5 border-b border-gray-200 dark:border-white/10"
                                    x-data="{ open: false }"
                                    wire:key="group-{{ $source }}-{{ $groupKey }}"
                                >
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.02] transition {{ $isAllSelected ? 'bg-primary-50/40 dark:bg-primary-950/20' : ($isPartiallySelected ? 'bg-amber-50/30 dark:bg-amber-950/10' : '') }}">
                                        <td class="px-4 py-3.5 text-center">
                                            <input
                                                type="checkbox"
                                                wire:click="toggleGroup({{ json_encode($groupItemIds) }})"
                                                @checked($isAllSelected)
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 cursor-pointer"
                                            >
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-gray-900 dark:text-white text-sm">
                                                        {{ $firstItem->name }}
                                                    </span>
                                                    @if ($groupItems->count() > 1)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700">
                                                            {{ $groupItems->count() }} Unit
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        {{ $firstItem->brand ?: 'Tanpa Merk' }}
                                                    </span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                        {{ $firstItem->category?->name ?? 'Tanpa Kategori' }}
                                                    </span>
                                                    @if ($isPartiallySelected)
                                                        <span class="text-amber-600 dark:text-amber-400 font-medium">
                                                            ({{ $selectedCountInGroup }}/{{ $groupItems->count() }} unit dipilih)
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            @if ($groupItems->count() === 1)
                                                <code class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono text-xs font-semibold">
                                                    {{ $firstItem->inventory_number ?: $firstItem->barcode }}
                                                </code>
                                            @else
                                                <div class="space-y-1">
                                                    <button
                                                        type="button"
                                                        @click="open = !open"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-700 dark:text-gray-300 transition"
                                                    >
                                                        <span x-text="open ? 'Sembunyikan Rincian No. Inv' : 'Lihat ' + {{ $groupItems->count() }} + ' No. Inventaris'"></span>
                                                        <x-filament::icon icon="heroicon-m-chevron-down" class="w-3.5 h-3.5 transition" ::class="open ? 'rotate-180' : ''" />
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                <x-filament::icon icon="heroicon-m-map-pin" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                                <span>
                                                    {{ $firstItem->location?->campus?->name ?? '—' }} / <strong class="text-gray-800 dark:text-gray-200">{{ $firstItem->location?->name ?: '—' }}</strong>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Sub-row rincian nomor inventaris saat di-expand --}}
                                    @if ($groupItems->count() > 1)
                                        <tr x-show="open" x-cloak class="bg-gray-50/70 dark:bg-white/[0.02]">
                                            <td class="px-4 py-2"></td>
                                            <td colspan="3" class="px-4 py-3">
                                                <div class="space-y-2">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                        Daftar Nomor Inventaris Fisik (Centang untuk memilih per unit):
                                                    </p>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach ($groupItems as $item)
                                                            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-xs font-mono cursor-pointer transition {{ in_array((string)$item->id, array_map('strval', $selected), true) ? 'bg-primary-50 border-primary-300 text-primary-800 dark:bg-primary-950/60 dark:border-primary-700 dark:text-primary-300' : 'bg-white border-gray-200 text-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400' }}">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model="selected"
                                                                    value="{{ $item->id }}"
                                                                    class="rounded text-primary-600 border-gray-300 focus:ring-primary-500 text-xs w-3.5 h-3.5"
                                                                >
                                                                <span>{{ $item->inventory_number ?: $item->barcode }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-8 h-8 text-gray-400" />
                                                <p class="text-sm font-medium">Tidak ada barang yang memenuhi syarat untuk klasifikasi ini.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            @endforelse
                        @endif
                    </table>
                </div>

                {{-- Pagination & Tombol Pratinjau --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $rows->links() }}
                    </div>

                    <div class="flex items-center gap-3 self-end sm:self-auto">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                            <strong>{{ count($selected) }}</strong> barang dipilih
                        </span>
                        <x-filament::button
                            wire:click="showPreview"
                            wire:loading.attr="disabled"
                            icon="heroicon-m-eye"
                            color="primary"
                            :disabled="count($selected) === 0"
                        >
                            Tampilkan Pratinjau Reklasifikasi
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Section 3: Pratinjau Reklasifikasi (KARTU UTAMA PRATINJAU) --}}
        @if ($preview)
            <div class="p-6 bg-white dark:bg-gray-900 border-2 border-primary-500/30 dark:border-primary-500/40 rounded-2xl shadow-sm space-y-6">
                {{-- Header Pratinjau --}}
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 dark:border-white/5 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="p-3 rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                            <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-7 h-7" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">
                                    Pratinjau Hasil Reklasifikasi
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    Siap Dikonfirmasi
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Verifikasi detail pemindahan data, histori pengadaan, dan nilai pembukuan sebelum disimpan.
                            </p>
                        </div>
                    </div>

                    {{-- Badges Arah Konversi --}}
                    <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 self-start md:self-auto">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            {{ $preview['source'] }}
                        </span>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                            {{ $preview['destination'] }}
                        </span>
                    </div>
                </div>

                {{-- Alert Error jika ada --}}
                @if ($preview['errors'] !== [])
                    <div class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/60 border border-danger-200 dark:border-danger-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-danger-700 dark:text-danger-300 text-sm">
                            <x-filament::icon icon="heroicon-m-x-circle" class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                            <span>Tidak dapat memproses konversi karena kendala berikut:</span>
                        </div>
                        <ul class="list-disc list-inside text-xs text-danger-600 dark:text-danger-400 space-y-1 pl-4">
                            @foreach ($preview['errors'] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- KPI Summary Stats --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Total Barang --}}
                    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-cube" class="w-4 h-4 text-gray-400" />
                            Total Kuantitas
                        </span>
                        <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                            {{ $preview['total_quantity'] }} <span class="text-sm font-normal text-gray-500">Barang</span>
                        </p>
                    </div>

                    {{-- Estimasi Nilai Buku Diketahui --}}
                    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-banknotes" class="w-4 h-4 text-emerald-500" />
                            Total Nilai Diketahui
                        </span>
                        <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">
                            {{ $preview['total_value'] === null ? '—' : 'Rp ' . number_format($preview['total_value'], 0, ',', '.') }}
                        </p>
                    </div>

                    {{-- Barang Tanpa Harga --}}
                    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-question-mark-circle" class="w-4 h-4 text-amber-500" />
                            Status Harga
                        </span>
                        <p class="text-sm font-semibold {{ $preview['unknown_price_quantity'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            @if ($preview['unknown_price_quantity'] > 0)
                                {{ $preview['unknown_price_quantity'] }} barang tanpa harga
                            @else
                                Seluruh harga tercatat
                            @endif
                        </p>
                    </div>

                    {{-- Lokasi Tujuan Reklasifikasi --}}
                    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 text-blue-500" />
                            Ruangan Tujuan
                        </span>
                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate" title="{{ $preview['destination_location'] ?? 'Pertahankan ruangan asal' }}">
                            {{ $preview['destination_location'] ?? 'Ruangan asal masing-masing' }}
                        </p>
                    </div>
                </div>

                {{-- Detail Barang Berdasarkan Grup --}}
                <div class="space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Rincian Barang yang Dikonversi
                    </h4>

                    @foreach ($preview['groups'] as $group)
                        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800/40 overflow-hidden shadow-xs">
                            {{-- Header Barang --}}
                            <div class="p-4 bg-gray-50/80 dark:bg-white/[0.03] border-b border-gray-200 dark:border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-base font-bold text-gray-900 dark:text-white">
                                            {{ $group['name'] }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                            {{ $group['brand'] ?: 'Tanpa Merk' }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                            {{ $group['category'] ?? 'Tanpa Kategori' }}
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                                            {{ $group['quantity'] }} {{ $group['unit'] }}
                                        </span>
                                    </div>

                                    {{-- Lokasi Pergerakan --}}
                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 flex-wrap">
                                        <x-filament::icon icon="heroicon-m-map-pin" class="w-4 h-4 text-gray-400 shrink-0" />
                                        <span>Asal:</span>
                                        <span class="font-medium text-gray-800 dark:text-gray-200">
                                            {{ collect($group['source_locations'])->map(fn ($qty, $place) => "$place ($qty)")->implode(', ') }}
                                        </span>
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                        <span>Tujuan:</span>
                                        <span class="font-semibold text-primary-600 dark:text-primary-400">
                                            {{ $group['destination_location'] ?? 'Ruangan asal masing-masing' }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Total Nilai Group --}}
                                <div class="text-left sm:text-right shrink-0">
                                    <span class="text-[11px] uppercase tracking-wider text-gray-400 block">Nilai Buku Tercatat</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $group['total_value'] === null ? '—' : 'Rp ' . number_format($group['total_value'], 0, ',', '.') }}
                                    </span>
                                    @if ($group['unknown_price_quantity'] > 0)
                                        <span class="text-[11px] text-amber-600 dark:text-amber-400 block font-medium">
                                            ({{ $group['unknown_price_quantity'] }} item tanpa harga)
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Rincian Batch Pengadaan --}}
                            <div class="p-4 space-y-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">
                                    Riwayat Batch Pengadaan & Harga Satuan
                                </span>

                                <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-white/5">
                                    <table class="w-full text-xs text-left">
                                        <thead class="text-[11px] text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/40">
                                            <tr>
                                                <th class="px-3 py-2">Batch / Referensi</th>
                                                <th class="px-3 py-2">Tanggal Pengadaan</th>
                                                <th class="px-3 py-2 text-center">Kuantitas</th>
                                                <th class="px-3 py-2 text-right">Harga Satuan</th>
                                                <th class="px-3 py-2 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-gray-700 dark:text-gray-300">
                                            @foreach ($group['batches'] as $batch)
                                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                                    <td class="px-3 py-2 font-mono font-medium">
                                                        Batch #{{ $batch['purchase_item_id'] ?? '—' }}
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                                        {{ $batch['purchase_date'] ?? '—' }}
                                                    </td>
                                                    <td class="px-3 py-2 text-center font-semibold">
                                                        {{ $batch['quantity'] }} {{ $group['unit'] }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right">
                                                        @if ($batch['unit_price'] === null)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                                                                Harga tidak diketahui
                                                            </span>
                                                        @else
                                                            Rp {{ number_format($batch['unit_price'], 0, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-semibold">
                                                        @if ($batch['subtotal'] === null || $batch['unit_price'] === null)
                                                            <span class="text-gray-400">—</span>
                                                        @else
                                                            Rp {{ number_format($batch['subtotal'], 0, ',', '.') }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Konfirmasi Button Bar --}}
                @if ($preview['errors'] === [])
                    <div class="pt-4 border-t border-gray-100 dark:border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-shield-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                            <span>Proses ini akan mencatat audit log permanen dan memperbarui saldo stok/aset secara atomik.</span>
                        </div>

                        <x-filament::button
                            wire:click="confirm"
                            wire:confirm="Apakah Anda yakin ingin memproses reklasifikasi barang ini? Tindakan ini akan dicatat ke dalam audit log sistem."
                            wire:loading.attr="disabled"
                            icon="heroicon-m-check-badge"
                            size="lg"
                            color="primary"
                        >
                            Konfirmasi & Proses Reklasifikasi
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Section 4: Hasil Transaksi Terakhir yang Berhasil --}}
        @if ($result)
            @php
                $groupedLines = $result->lines->groupBy('destination_id');
            @endphp
            <div class="p-6 bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl shadow-xs space-y-5">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                Reklasifikasi Berhasil Diproses (#{{ $result->id }})
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                Transaksi #{{ $result->id }} · {{ $result->summary['source'] }} &rarr; {{ $result->summary['destination'] }} · <strong>{{ $result->total_quantity }} barang</strong>
                                @if ($result->reason)
                                    · <span class="italic">"{{ $result->reason }}"</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700">
                        Selesai
                    </span>
                </div>

                {{-- Ringkasan Konsolidasi Stok Hasil (Ditotal per Stok Tujuan) --}}
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-m-calculator" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Ringkasan Stok Hasil Reklasifikasi (Ditotal)</span>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($groupedLines as $destinationId => $lines)
                            @php
                                $firstLine = $lines->first();
                                $totalQty = $lines->sum('quantity');
                                $snapshot = $firstLine->snapshot ?? [];
                            @endphp
                            <div class="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-800/80 bg-white dark:bg-gray-900 flex items-center justify-between gap-3 shadow-2xs">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                                        <span>{{ $snapshot['name'] ?? ($firstLine->destination_type . ' #' . $destinationId) }}</span>
                                        @if (!empty($snapshot['brand']))
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-normal">
                                                {{ $snapshot['brand'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Stok Tujuan: <code class="font-mono font-semibold text-primary-600 dark:text-primary-400">{{ $firstLine->destination_type }} #{{ $destinationId }}</code> · Menggabungkan {{ $lines->count() }} unit asal
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-medium">Total Akumulasi</span>
                                    <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                        {{ $totalQty }} {{ $snapshot['unit'] ?? 'Unit' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Detail Audit Log Baris --}}
                <details class="group">
                    <summary class="cursor-pointer text-xs font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1.5 py-1 select-none">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="w-3.5 h-3.5 transition group-open:rotate-90 text-gray-400" />
                        <span>Lihat Rincian Baris Jejak Audit Log (One-to-One Mapping)</span>
                    </summary>
                    <div class="mt-2 overflow-x-auto rounded-xl border border-emerald-200/80 dark:border-emerald-800/60 bg-white dark:bg-gray-900">
                        <table class="w-full text-xs text-left">
                            <thead class="text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                                <tr>
                                    <th class="px-4 py-2.5">Barang Asal</th>
                                    <th class="px-4 py-2.5 text-center">Arah</th>
                                    <th class="px-4 py-2.5">Stok Tujuan</th>
                                    <th class="px-4 py-2.5 text-center">Qty</th>
                                    <th class="px-4 py-2.5 text-right">Harga Satuan</th>
                                    <th class="px-4 py-2.5">Batch Asal &rarr; Tujuan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($result->lines as $line)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">
                                            {{ $line->source_type }} #{{ $line->source_id }}
                                            @if (!empty($line->snapshot['inventory_number']))
                                                <span class="text-gray-400 font-mono text-[11px]">({{ $line->snapshot['inventory_number'] }})</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-400">&rarr;</td>
                                        <td class="px-4 py-2 font-medium text-primary-600 dark:text-primary-400">
                                            {{ $line->destination_type }} #{{ $line->destination_id }}
                                        </td>
                                        <td class="px-4 py-2 text-center font-bold">{{ $line->quantity }}</td>
                                        <td class="px-4 py-2 text-right font-medium">
                                            {{ $line->unit_price === null ? 'Harga tidak diketahui' : 'Rp ' . number_format($line->unit_price, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 font-mono text-[11px]">
                                            Batch {{ $line->source_purchase_item_id ?? '—' }} &rarr; {{ $line->destination_purchase_item_id ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        @endif

        {{-- Section 5: Riwayat Reklasifikasi --}}
        <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl shadow-xs space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5 text-gray-400" />
                        <span>Riwayat Reklasifikasi Terbaru</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Daftar transaksi reklasifikasi yang pernah dilakukan oleh operator.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-xs text-left">
                    <thead class="text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th class="px-4 py-3 w-20">ID</th>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Operator</th>
                            <th class="px-4 py-3">Konversi Klasifikasi</th>
                            <th class="px-4 py-3 text-center">Jumlah Barang</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($history as $transaction)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white">
                                    #{{ $transaction->id }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $transaction->created_at?->format('d M Y, H:i') ?? $transaction->created_at }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $transaction->user?->name ?? 'Sistem' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-1.5 font-medium">
                                        <span class="px-2 py-0.5 rounded text-[11px] bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            {{ $transaction->sourceClassification?->name ?? '—' }}
                                        </span>
                                        <span class="text-gray-400">&rarr;</span>
                                        <span class="px-2 py-0.5 rounded text-[11px] bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-semibold">
                                            {{ $transaction->destinationClassification?->name ?? '—' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-gray-800 dark:text-gray-200">
                                    {{ $transaction->total_quantity }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-filament::button
                                        size="xs"
                                        color="gray"
                                        icon="heroicon-m-eye"
                                        wire:click="viewResult({{ $transaction->id }})"
                                    >
                                        Rincian
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                    Belum ada transaksi reklasifikasi barang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2">
                {{ $history->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
