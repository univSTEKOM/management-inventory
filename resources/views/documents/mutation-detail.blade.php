<div>
    <h2>{{ $mutation->mutation_number }} — {{ \App\Filament\Inventory\Resources\MutationResource::statusLabel($mutation->status) }}</h2>
    <p>Asal: {{ $mutation->sourceCampus?->name }} / {{ $mutation->sourceLocation?->name }} — PIC: {{ $mutation->sourcePic?->name ?? '—' }}</p>
    <p>Tujuan: {{ $mutation->destinationCampus?->name }} / {{ $mutation->destinationLocation?->name }} — PIC: {{ $mutation->destinationPic?->name ?? '—' }}</p>
    <p>Dibuat oleh: {{ $mutation->requestedBy?->name }} · Diproses: {{ $mutation->mutation_date?->format('d/m/Y H:i') ?? 'Belum diproses' }}</p>
    <p>Alasan: {{ $mutation->reason }}</p>
    <div style="overflow-x:auto;margin-top:1rem">
        <table style="width:100%;text-align:left;border-collapse:collapse" border="1" cellpadding="8">
            <thead><tr><th>Kode / barcode</th><th>Barang</th><th>Merek</th><th>Jenis</th><th>Jumlah</th></tr></thead>
            <tbody>@foreach ($mutation->items as $item)
                @php($detail = $item->display_details)
                <tr><td>{{ $detail['code'] ?? '—' }} / {{ $detail['barcode'] ?? '—' }}</td><td>{{ $detail['name'] }}</td><td>{{ $detail['brand'] ?? '—' }}</td><td>{{ $detail['type'] }}</td><td>{{ $item->quantity }}</td></tr>
            @endforeach</tbody>
        </table>
    </div>
</div>
