<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-1">Klasifikasi asal
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="source">
                    <option value="">Pilih klasifikasi</option>
                    @foreach ($labels as $slug => $label)
                        <option value="{{ $slug }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select></x-filament::input.wrapper>
            </label>
            <label class="grid gap-1">Klasifikasi tujuan
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="destination">
                    <option value="">Pilih klasifikasi</option>
                    @foreach ($labels as $slug => $label)
                        <option value="{{ $slug }}" @disabled($slug === $source)>{{ $label }}</option>
                    @endforeach
                </x-filament::input.select></x-filament::input.wrapper>
            </label>
            @if ($rows && ($supply || $destination === 'barang-habis-pakai'))
                <label class="grid gap-1">Ruangan tujuan
                    <x-filament::input.wrapper><x-filament::input.select wire:model.live="locationId">
                        <option value="">Pertahankan ruangan asal</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->campus?->name }} / {{ $location->name }}</option>
                        @endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                </label>
            @endif
            <label class="grid gap-1">Alasan (opsional)
                <x-filament::input.wrapper><x-filament::input wire:model="reason" maxlength="5000" /></x-filament::input.wrapper>
            </label>
        </div>

        @if ($rows)
            <div class="mt-4 space-y-3">
                <x-filament::input.wrapper><x-filament::input wire:model.live.debounce.350ms="search" placeholder="Cari nama atau barcode" aria-label="Cari nama atau barcode" /></x-filament::input.wrapper>
                @forelse ($rows as $row)
                    <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 py-2 dark:border-gray-700" wire:key="item-{{ $source }}-{{ $row->id }}">
                        <label class="flex flex-1 items-center gap-2">
                            <input type="checkbox" wire:model="selected" value="{{ $row->id }}">
                            <span>{{ $row->name }} · {{ $row->brand ?: '—' }} · {{ $row->category?->name }} · {{ $row->location?->campus?->name }} / {{ $row->location?->name ?: '—' }} · {{ $supply ? $row->master_barcode : $row->inventory_number }}</span>
                        </label>
                        @if ($supply)
                            <label class="flex items-center gap-2">Jumlah ({{ $row->quantity }} {{ $row->unit }})
                                <x-filament::input.wrapper><x-filament::input type="number" min="1" :max="$row->quantity" wire:model="quantities.{{ $row->id }}" placeholder="1" /></x-filament::input.wrapper>
                            </label>
                        @endif
                    </div>
                @empty
                    <p>Barang tidak ditemukan.</p>
                @endforelse
                {{ $rows->links() }}
                <x-filament::button wire:click="showPreview" wire:loading.attr="disabled">Tampilkan pratinjau</x-filament::button>
            </div>
        @endif

        @foreach ($errors->all() as $message)
            <p class="mt-2 text-danger-600" role="alert">{{ $message }}</p>
        @endforeach
    </x-filament::section>

    @if ($preview)
        <x-filament::section heading="Pratinjau konversi">
            @foreach ($preview['errors'] as $message)
                <p class="text-danger-600" role="alert">{{ $message }}</p>
            @endforeach
            <p>{{ $preview['source'] }} → {{ $preview['destination'] }} · {{ $preview['total_quantity'] }} barang</p>
            @foreach ($preview['groups'] as $group)
                <div class="mt-3 border-b border-gray-200 pb-2 dark:border-gray-700">
                    <p class="font-semibold">{{ $group['name'] }} · {{ $group['brand'] ?: '—' }} · {{ $group['category'] }} · {{ $group['quantity'] }} {{ $group['unit'] }}</p>
                    <p>{{ collect($group['source_locations'])->map(fn ($quantity, $place) => "$place ($quantity)")->implode(', ') }} → {{ $group['destination_location'] ?? 'Ruangan asal masing-masing' }}</p>
                    <p>Nilai diketahui {{ $group['total_value'] === null ? '—' : 'Rp'.number_format($group['total_value'], 2, ',', '.') }} · {{ $group['unknown_price_quantity'] }} tanpa harga</p>
                    @foreach ($group['batches'] as $batch)
                        <p class="text-sm">Batch {{ $batch['purchase_item_id'] ?? '—' }} · {{ $batch['purchase_date'] ?? '—' }} · {{ $batch['quantity'] }} × {{ $batch['unit_price'] === null ? 'Harga tidak diketahui' : 'Rp'.number_format($batch['unit_price'], 2, ',', '.') }}</p>
                    @endforeach
                </div>
            @endforeach
            @if ($preview['errors'] === [])
                <x-filament::button class="mt-4" wire:click="confirm" wire:confirm="Proses reklasifikasi barang ini?" wire:loading.attr="disabled">Konfirmasi reklasifikasi</x-filament::button>
            @endif
        </x-filament::section>
    @endif

    @if ($result)
        <x-filament::section :heading="'Transaksi #'.$result->id">
            <p>{{ $result->summary['source'] }} → {{ $result->summary['destination'] }} · {{ $result->total_quantity }} barang · {{ $result->reason }}</p>
            <ul class="mt-2 space-y-1">
                @foreach ($result->lines as $line)
                    <li>{{ $line->source_type }} #{{ $line->source_id }} → {{ $line->destination_type }} #{{ $line->destination_id }} · {{ $line->quantity }} · {{ $line->unit_price === null ? 'Harga tidak diketahui' : 'Rp'.number_format($line->unit_price, 2, ',', '.') }} · Batch {{ $line->source_purchase_item_id ?? '—' }} → {{ $line->destination_purchase_item_id ?? '—' }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament::section heading="Riwayat reklasifikasi">
        @forelse ($history as $transaction)
            <div class="flex flex-wrap items-center gap-3 py-1">
                <x-filament::button size="sm" color="gray" wire:click="viewResult({{ $transaction->id }})">#{{ $transaction->id }}</x-filament::button>
                <span>{{ $transaction->created_at }} · {{ $transaction->user?->name ?? '—' }} · {{ $transaction->sourceClassification?->name }} → {{ $transaction->destinationClassification?->name }} · {{ $transaction->total_quantity }} barang</span>
            </div>
        @empty
            <p>Belum ada transaksi reklasifikasi.</p>
        @endforelse
        {{ $history->links() }}
    </x-filament::section>
</x-filament-panels::page>
