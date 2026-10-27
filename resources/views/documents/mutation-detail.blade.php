<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <div style="border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 16px;">
        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #111827;">
            {{ $mutation->mutation_number }} — {{ \App\Filament\Inventory\Resources\MutationResource::statusLabel($mutation->status) }}
        </h2>
        <div style="font-size: 0.875rem; color: #6b7280; margin-top: 4px;">
            Dibuat oleh: <strong>{{ $mutation->requestedBy?->name ?? '—' }}</strong> · Diproses: <strong>{{ $mutation->mutation_date?->format('d/m/Y H:i') ?? 'Belum diproses' }}</strong>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 0.875rem;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #4b5563;">Lokasi Asal</div>
            <p style="margin: 4px 0 0 0;">Asal: <strong>{{ $mutation->sourceCampus?->name }} / {{ $mutation->sourceLocation?->name }}</strong> — PIC: {{ $mutation->sourcePic?->name ?? '—' }}</p>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #4b5563;">Lokasi Tujuan</div>
            <p style="margin: 4px 0 0 0;">Tujuan: <strong>{{ $mutation->destinationCampus?->name }} / {{ $mutation->destinationLocation?->name }}</strong> — PIC: {{ $mutation->destinationPic?->name ?? '—' }}</p>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; font-size: 0.875rem;">
        <p style="margin: 0;"><strong>Alasan:</strong> {{ $mutation->reason }}</p>
    </div>

    <div style="overflow-x: auto; margin-top: 1rem;">
        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.875rem;" border="1" cellpadding="8">
            <thead style="background: #f3f4f6; color: #374151;">
                <tr>
                    <th style="border: 1px solid #d1d5db; padding: 8px 10px;">Kode / barcode</th>
                    <th style="border: 1px solid #d1d5db; padding: 8px 10px;">Barang</th>
                    <th style="border: 1px solid #d1d5db; padding: 8px 10px;">Merek</th>
                    <th style="border: 1px solid #d1d5db; padding: 8px 10px;">Jenis</th>
                    <th style="border: 1px solid #d1d5db; padding: 8px 10px; text-align: right;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($mutation->items as $item)
                    @php($detail = $item->display_details)
                    <tr>
                        <td style="border: 1px solid #e5e7eb; padding: 8px 10px; font-family: monospace;">{{ $detail['code'] ?? '—' }} / {{ $detail['barcode'] ?? '—' }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px 10px; font-weight: 600;">{{ $detail['name'] }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px 10px;">{{ $detail['brand'] ?? '—' }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px 10px;">{{ $detail['type'] }}</td>
                        <td style="border: 1px solid #e5e7eb; padding: 8px 10px; text-align: right; font-weight: 600;">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
