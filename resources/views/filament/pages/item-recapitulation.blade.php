<x-filament-panels::page>
    @php
        $pageUrl = fn (array $params = []) => \App\Filament\Inventory\Pages\ItemRecapitulation::getUrl($params);
        $scopeName = $selectedRoom?->name ?? $selectedCampus?->name ?? 'Semua Homebase';
        $ungrouped = $selectedRoom ? collect() : $totals->except($groups->modelKeys());
    @endphp
    <style>
        .item-recap { --recap-border:#e2e8f0; --recap-muted:#475569; --recap-surface:#fff; --recap-soft:#f8fafc; color:#0f172a; display:grid; gap:24px }
        .dark .item-recap { --recap-border:#334155; --recap-muted:#cbd5e1; --recap-surface:#111827; --recap-soft:#1e293b; color:#f1f5f9 }
        .item-recap a:focus-visible { outline:3px solid #3b82f6; outline-offset:4px }
        .item-recap .recap-nav { display:flex; gap:10px; flex-wrap:wrap; align-items:center; font-size:14px; color:var(--recap-muted) }
        .item-recap .recap-nav a { text-decoration:underline; text-underline-offset:4px }
        .item-recap .recap-header { display:flex; justify-content:space-between; gap:20px; align-items:center; flex-wrap:wrap }
        .item-recap h2 { font-size:24px; font-weight:700; overflow-wrap:anywhere }
        .item-recap h3 { font-size:18px; font-weight:600 }
        .item-recap p { color:var(--recap-muted); font-size:14px; line-height:1.6 }
        .item-recap .recap-eyebrow { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; margin-bottom:4px }
        .item-recap .recap-button { display:inline-flex; align-items:center; padding:10px 16px; border:1px solid var(--recap-border); border-radius:10px; background:var(--recap-surface); font-size:14px; font-weight:600 }
        .item-recap .recap-stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px }
        .item-recap .recap-stat { padding:20px; border:1px solid var(--recap-border); border-radius:14px; background:var(--recap-surface) }
        .item-recap .recap-number { font-size:32px; font-weight:700; font-variant-numeric:tabular-nums; margin:8px 0 }
        .item-recap .recap-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr)); gap:16px; margin-top:16px }
        .item-recap .recap-card { display:flex; flex-direction:column; gap:18px; padding:20px; border:1px solid var(--recap-border); border-radius:14px; background:var(--recap-surface); transition:border-color .15s,box-shadow .15s }
        .item-recap .recap-card:hover { border-color:#3b82f6; box-shadow:0 4px 16px #2563eb12 }
        .item-recap .recap-card h4 { font-weight:700; font-size:16px; overflow-wrap:anywhere }
        .item-recap .recap-card dl { display:grid; grid-template-columns:1fr 1fr; gap:12px }
        .item-recap dt { color:var(--recap-muted); font-size:12px }
        .item-recap dd { font-size:22px; font-weight:600; font-variant-numeric:tabular-nums }
        .item-recap .recap-cta { border-top:1px solid var(--recap-border); padding-top:14px; font-size:14px; font-weight:600; margin-top:auto; display:flex; justify-content:space-between }
        .item-recap .recap-notice { padding:16px; margin-top:16px; border:1px dashed var(--recap-border); border-radius:12px; background:var(--recap-soft) }
        .item-recap .recap-table-wrap { overflow-x:auto; border:1px solid var(--recap-border); border-radius:12px; margin-top:16px; background:var(--recap-surface) }
        .item-recap table { width:100%; border-collapse:collapse; font-size:14px; text-align:left }
        .item-recap th { background:var(--recap-soft); color:var(--recap-muted); font-size:12px; font-weight:600; white-space:nowrap }
        .item-recap th,.item-recap td { padding:14px 18px; border-bottom:1px solid var(--recap-border) }
        .item-recap tbody tr:last-child td { border-bottom:0 }
        .item-recap tbody tr:hover { background:var(--recap-soft) }
        .item-recap .recap-qty { text-align:right; font-variant-numeric:tabular-nums }
        .item-recap .recap-item-name { font-weight:600; min-width:180px }
        .item-recap .recap-empty { padding:32px; text-align:center; color:var(--recap-muted) }
        .item-recap .recap-pagination { margin-top:16px }
        @media(max-width:600px) { .item-recap .recap-stats { grid-template-columns:1fr } .item-recap h2 { font-size:21px } }
    </style>
    <div class="item-recap">
        <nav aria-label="Navigasi rekapitulasi" class="recap-nav">
            @if ($selectedCampus)
                <a href="{{ $pageUrl() }}">Semua Homebase</a>
                <span aria-hidden="true">/</span>
                @if ($selectedRoom)
                    <a href="{{ $pageUrl(['campus' => $selectedCampus->id]) }}">{{ $selectedCampus->name }}</a>
                    <span aria-hidden="true">/</span>
                @endif
            @endif
            <span aria-current="page">{{ $scopeName }}</span>
        </nav>

        <header class="recap-header">
            <div>
                <p class="recap-eyebrow">{{ $selectedRoom ? 'Detail ruangan' : ($selectedCampus ? 'Ringkasan homebase' : 'Ringkasan seluruh lokasi') }}</p>
                <h2>{{ $scopeName }}</h2>
                <p>{{ $selectedRoom ? 'Daftar barang yang ditempatkan di ruangan ini.' : ($selectedCampus ? 'Pilih ruangan untuk melihat isinya, atau lihat seluruh barang homebase di bawah.' : 'Pilih homebase untuk melihat ruangan dan barang di dalamnya.') }}</p>
            </div>
            @if ($selectedCampus)
                <a class="recap-button" href="{{ $selectedRoom ? $pageUrl(['campus' => $selectedCampus->id]) : $pageUrl() }}">← {{ $selectedRoom ? 'Kembali ke homebase' : 'Semua homebase' }}</a>
            @endif
        </header>

        <div class="recap-stats" aria-label="Ringkasan jumlah barang">
            <div class="recap-stat">
                <h3>Barang tercatat per unit</h3>
                <div class="recap-number">{{ number_format($individualCount) }}</div>
                <p>Aset, inventaris, dan BHP yang dicatat satu per satu.</p>
            </div>
            <div class="recap-stat">
                <h3>Stok BHP</h3>
                <div class="recap-number">{{ number_format($stockQuantity) }}</div>
                <p>Jumlah stok gabungan. Satuan masing-masing ada di daftar barang.</p>
            </div>
        </div>

        @unless ($selectedRoom)
            <section aria-labelledby="recap-locations-heading">
                <div class="recap-header">
                    <h3 id="recap-locations-heading">{{ $selectedCampus ? 'Ruangan' : 'Homebase' }} <span>({{ $groups->count() }})</span></h3>
                    @if ($selectedCampus)
                        <a class="recap-button" href="#recap-items">Lihat semua barang ↓</a>
                    @endif
                </div>
                <div class="recap-grid">
                    @forelse ($groups as $group)
                        <a class="recap-card" href="{{ $pageUrl($selectedCampus ? ['campus' => $selectedCampus->id, 'room' => $group->id] : ['campus' => $group->id]) }}">
                            <h4>{{ $group->name }}</h4>
                            <dl>
                                <div><dt>Barang per unit</dt><dd>{{ number_format($totals->get($group->id)?->individual_count ?? 0) }}</dd></div>
                                <div><dt>Stok BHP</dt><dd>{{ number_format($totals->get($group->id)?->stock_quantity ?? 0) }}</dd></div>
                            </dl>
                            <span class="recap-cta">{{ $selectedCampus ? 'Lihat barang' : 'Lihat ruangan' }}<span aria-hidden="true">→</span></span>
                        </a>
                    @empty
                        <p class="recap-empty">Belum ada {{ $selectedCampus ? 'ruangan di homebase ini' : 'homebase' }}.</p>
                    @endforelse
                </div>
                @if ($ungrouped->isNotEmpty())
                    <div class="recap-notice">
                        <h4>Penempatan belum terhubung</h4>
                        <p>{{ number_format($ungrouped->sum('individual_count')) }} barang per unit dan {{ number_format($ungrouped->sum('stock_quantity')) }} stok BHP tidak terhubung ke {{ $selectedCampus ? 'ruangan' : 'homebase' }} aktif yang sesuai. Jumlah ini tetap termasuk dalam ringkasan di atas.</p>
                    </div>
                @endif
            </section>
        @endunless

        @if ($items)
            <section id="recap-items" aria-labelledby="recap-items-heading">
                <h3 id="recap-items-heading">{{ $selectedRoom ? 'Barang di ruangan ini' : 'Semua barang di homebase ini' }}</h3>
                <p>{{ number_format($items->total()) }} catatan barang · {{ $selectedRoom ? $selectedRoom->name : 'Mencakup seluruh ruangan dan barang tanpa ruangan' }}</p>
                <div class="recap-table-wrap">
                    <table>
                        <caption class="sr-only">Daftar barang — {{ $scopeName }}</caption>
                        <thead><tr>
                            <th scope="col">Barang / Barcode</th>
                            <th scope="col">Jenis</th>
                            @unless ($selectedRoom)<th scope="col">Ruangan</th>@endunless
                            <th scope="col" class="recap-qty">Jumlah</th>
                            <th scope="col">Satuan</th>
                        </tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td><div class="recap-item-name">{{ $item->item_name }}</div><p>{{ $item->barcode ?: 'Tanpa barcode' }}</p></td>
                                    <td>{{ $item->classification_name ?: '—' }}</td>
                                    @unless ($selectedRoom)<td>{{ $item->location_name ?: 'Tanpa ruangan' }}</td>@endunless
                                    <td class="recap-qty">{{ number_format($item->quantity) }}</td>
                                    <td>{{ $item->unit_name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $selectedRoom ? 4 : 5 }}" class="recap-empty">Belum ada barang pada penempatan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="recap-pagination">{{ $items->links() }}</div>
            </section>
        @endif
        <p>Rekap mengikuti penempatan barang saat ini. Barang terhapus dan barang dalam antrean pemeriksaan tidak ditampilkan.</p>
    </div>
</x-filament-panels::page>
