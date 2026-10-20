<x-filament-panels::page>
    <nav aria-label="Navigasi rekapitulasi" class="flex flex-wrap items-center gap-3">
        <x-filament::link :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl()">Semua Homebase</x-filament::link>
        @if ($selectedCampus)
            <span aria-hidden="true">/</span>
            <x-filament::link :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl(['campus' => $selectedCampus->id])">{{ $selectedCampus->name }}</x-filament::link>
        @endif
        @if ($selectedRoom)
            <span aria-hidden="true">/</span>
            <span>{{ $selectedRoom->name }}</span>
        @endif
    </nav>

    <x-filament::section heading="Ringkasan Barang">
        <p>Barang individual: <strong>{{ number_format($individualCount) }}</strong></p>
        <p>Kuantitas stok BHP: <strong>{{ number_format($stockQuantity) }}</strong></p>
        <p class="text-sm text-gray-500">Homebase mengikuti penempatan barang. Kuantitas stok BHP merupakan jumlah stok dari berbagai satuan. Barang yang dihapus atau masih dalam antrean pemeriksaan tidak termasuk.</p>
    </x-filament::section>

    @unless ($selectedRoom)
        <x-filament::section :heading="$selectedCampus ? 'Rekap per Ruangan' : 'Rekap per Homebase'">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th scope="col" class="p-3">{{ $selectedCampus ? 'Ruangan' : 'Homebase' }}</th>
                            <th scope="col" class="p-3">Barang individual</th>
                            <th scope="col" class="p-3">Kuantitas stok BHP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <th scope="row" class="p-3">
                                    <x-filament::link :href="\App\Filament\Inventory\Pages\ItemRecapitulation::getUrl($selectedCampus ? ['campus' => $selectedCampus->id, 'room' => $group->id] : ['campus' => $group->id])">{{ $group->name }}</x-filament::link>
                                </th>
                                <td class="p-3">{{ number_format($totals->get($group->id)?->individual_count ?? 0) }}</td>
                                <td class="p-3">{{ number_format($totals->get($group->id)?->stock_quantity ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-3">Belum ada {{ $selectedCampus ? 'ruangan' : 'homebase' }}.</td></tr>
                        @endforelse
                        @php
                            $ungrouped = $totals->except($groups->modelKeys());
                        @endphp
                        @if ($ungrouped->isNotEmpty())
                            <tr>
                                <th scope="row" class="p-3">Tanpa {{ $selectedCampus ? 'ruangan' : 'homebase' }} aktif yang sesuai</th>
                                <td class="p-3">{{ number_format($ungrouped->sum('individual_count')) }}</td>
                                <td class="p-3">{{ number_format($ungrouped->sum('stock_quantity')) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endunless

    @if ($items)
        <x-filament::section :heading="$selectedRoom ? 'Barang di Ruangan '.$selectedRoom->name : 'Seluruh Barang di Homebase '.$selectedCampus->name">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            @foreach (['Barang', 'Jenis', 'Barcode', 'Ruangan', 'Jumlah', 'Satuan'] as $label)
                                <th scope="col" class="p-3">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td class="p-3">{{ $item->item_name }}</td>
                                <td class="p-3">{{ $item->classification_name }}</td>
                                <td class="p-3">{{ $item->barcode ?: '—' }}</td>
                                <td class="p-3">{{ $item->location_name ?: 'Tanpa ruangan' }}</td>
                                <td class="p-3">{{ number_format($item->quantity) }}</td>
                                <td class="p-3">{{ $item->unit_name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-3">Belum ada barang pada penempatan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        </x-filament::section>
    @endif
</x-filament-panels::page>
