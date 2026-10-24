<x-filament-panels::page>
    <style>
        .mutation-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem }
        .mutation-input { width:100%; border:1px solid #9ca3af; border-radius:.5rem; padding:.65rem; background:transparent; color:inherit }
        .mutation-table { width:100%; border-collapse:collapse; text-align:left }
        .mutation-table th,.mutation-table td { padding:.65rem; border-bottom:1px solid #9ca3af55 }
        .mutation-stack > * + * { margin-top:1rem }
    </style>
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-danger-500 p-4">
            <strong>Mutasi belum diproses. Periksa data berikut:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form x-data wire:submit="review" class="mutation-stack">
        <x-filament::section heading="Asal dan tujuan">
            <div class="mutation-grid">
                @foreach (['source' => 'Asal', 'destination' => 'Tujuan'] as $side => $label)
                    <div class="mutation-stack">
                        <label class="block">Gedung {{ $label }}
                            <select aria-label="Gedung {{ $label }}" class="mutation-input" wire:model.live="data.{{ $side }}_campus_id"
                                @if ($side === 'source') x-on:change.capture="if ($wire.items.length && ! confirm('Mengganti asal akan mengosongkan daftar barang. Lanjutkan?')) { $event.stopImmediatePropagation(); $event.target.value = $wire.data.source_campus_id; }" @endif>
                                <option value="">Pilih gedung</option>
                                @foreach ($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block">Ruangan {{ $label }}
                            <select aria-label="Ruangan {{ $label }}" class="mutation-input" wire:model.live="data.{{ $side }}_location_id" @disabled(! $data[$side.'_campus_id'])
                                @if ($side === 'source') x-on:change.capture="if ($wire.items.length && ! confirm('Mengganti asal akan mengosongkan daftar barang. Lanjutkan?')) { $event.stopImmediatePropagation(); $event.target.value = $wire.data.source_location_id; }" @endif>
                                <option value="">Pilih ruangan</option>
                                @foreach (($side === 'source' ? $sourceLocations : $destinationLocations) as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block">PIC {{ $label }} (opsional)
                            <select aria-label="PIC {{ $label }}" class="mutation-input" wire:model.live="data.{{ $side }}_pic_id">
                                <option value="">Tanpa PIC</option>
                                @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
        <x-filament::section heading="Cari barang">
            <label for="mutation-search">Kode barang, barcode, nomor inventaris, atau nama barang</label>
            <input id="mutation-search" class="mutation-input" wire:model.live.debounce.350ms="search" placeholder="Ketik untuk mencari barang di lokasi asal…" @disabled(! $data['source_location_id'])>
            <p>Hasil dibatasi 30 barang individual dan 30 BHP. Perjelas pencarian jika barang belum terlihat.</p>
            <div style="overflow-x:auto">
                <table class="mutation-table">
                    <thead><tr><th>Kode / barcode</th><th>Barang / merek</th><th>Jenis</th><th>Tersedia</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($results as $result)
                        <tr wire:key="result-{{ $result['key'] }}">
                            <td>{{ $result['code'] ?: '—' }}<br><small>{{ $result['barcode'] }}</small></td>
                            <td>{{ $result['name'] }}<br><small>{{ $result['brand'] }}</small></td>
                            <td>{{ $result['type'] }}</td><td>{{ $result['available'] }}</td>
                            <td><x-filament::button type="button" wire:click="addItem('{{ $result['key'] }}')" :disabled="in_array($result['key'], array_column($items, 'key'))">{{ in_array($result['key'], array_column($items, 'key')) ? 'Ditambahkan' : 'Tambah' }}</x-filament::button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ ! $data['source_location_id'] ? 'Pilih lokasi asal terlebih dahulu.' : (trim($search) === '' ? 'Ketik kode atau nama barang untuk mencari.' : 'Tidak ada barang yang cocok di lokasi asal.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <x-filament::section heading="Barang yang akan dipindahkan">
            <div style="overflow-x:auto">
                <table class="mutation-table">
                    <thead><tr><th>Barang</th><th>Identitas</th><th>Jenis</th><th>Jumlah</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($items as $index => $item)
                        <tr wire:key="selected-{{ $item['key'] }}">
                            <td>{{ $item['name'] }} {{ $item['brand'] }}</td><td>{{ $item['code'] }}<br>{{ $item['barcode'] }}</td><td>{{ $item['type'] }}</td>
                            <td>@if (str_starts_with($item['key'], 'supply:'))<input aria-label="Jumlah {{ $item['name'] }}" class="mutation-input" style="min-width:90px" type="number" min="1" max="{{ $item['available'] }}" step="1" wire:model.live="items.{{ $index }}.quantity">@else 1 @endif</td>
                            <td><x-filament::button type="button" color="danger" wire:click="removeItem({{ $index }})">Hapus</x-filament::button></td>
                        </tr>
                    @empty<tr><td colspan="5">Belum ada barang dipilih.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <label class="block" for="mutation-reason">Alasan mutasi</label>
        <textarea id="mutation-reason" class="mutation-input" wire:model.live.debounce.350ms="data.reason" maxlength="5000" rows="3" required></textarea>
        <x-filament::button type="submit" wire:loading.attr="disabled" :disabled="count($items) === 0">Proses Mutasi</x-filament::button>
    </form>
    @if ($confirming)
        <div role="dialog" aria-modal="true" aria-labelledby="mutation-confirm-title" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.set('confirming', false)" style="position:fixed;inset:0;z-index:60;background:#0009;display:flex;align-items:center;justify-content:center;padding:1rem">
            <div class="bg-white dark:bg-gray-900 rounded-xl p-6 mutation-stack" style="max-width:700px;width:100%;max-height:85vh;overflow:auto">
                <h2 id="mutation-confirm-title" class="text-xl font-bold">Konfirmasi perpindahan barang</h2>
                <p>{{ $campuses->find($data['source_campus_id'])?->name }} / {{ $sourceLocations->find($data['source_location_id'])?->name }} → {{ $campuses->find($data['destination_campus_id'])?->name }} / {{ $destinationLocations->find($data['destination_location_id'])?->name }}</p>
                <ul>@foreach ($items as $item)<li>{{ $item['name'] }} {{ $item['brand'] }} — {{ $item['code'] }} / {{ $item['barcode'] }} × {{ $item['quantity'] }}</li>@endforeach</ul>
                <p>PIC asal: {{ $employees->find($data['source_pic_id'])?->name ?? '—' }} · PIC tujuan: {{ $employees->find($data['destination_pic_id'])?->name ?? '—' }}</p>
                <p>Alasan: {{ $data['reason'] }}</p>
                <p>Pastikan barang dipindahkan sesuai daftar. Lokasi dan stok langsung diperbarui setelah berhasil.</p>
                <x-filament::button type="button" color="gray" wire:click="$set('confirming', false)" wire:loading.attr="disabled">Kembali</x-filament::button>
                <x-filament::button type="button" wire:click="process" wire:loading.attr="disabled">Konfirmasi &amp; Proses</x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
