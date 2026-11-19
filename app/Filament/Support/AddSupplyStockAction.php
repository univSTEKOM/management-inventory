<?php

namespace App\Filament\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\Purchase;
use App\Services\SupplyBarcodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddSupplyStockAction
{
    public static function make(): Action
    {
        return Action::make('stock_in')
            ->label('Tambah Stok')
            ->icon('heroicon-o-plus-circle')
            ->authorize(fn () => Auth::user()?->hasPermissionTo('supplies.stock_in') ?? false)
            ->modalHeading(fn (InventoryBalance $record) => 'Tambah Stok: '.$record->name)
            ->form([
                TextInput::make('quantity')->label('Jumlah Tambahan')->integer()->minValue(1)->required(),
                Select::make('unit')
                    ->label('Satuan')
                    ->options(fn (InventoryBalance $record): array => array_filter(array_merge([
                        'Unit' => 'Unit', 'Pcs' => 'Pcs', 'Set' => 'Set',
                        'Roll' => 'Roll', 'Rim' => 'Rim', 'Dus' => 'Dus', 'Box' => 'Box',
                        'Kg' => 'Kg', 'Paket' => 'Paket', 'Lembar' => 'Lembar',
                        'Buah' => 'Buah', 'Meter' => 'Meter', 'Liter' => 'Liter',
                    ], $record->unit ? [$record->unit => $record->unit] : [])))
                    ->default(fn (InventoryBalance $record) => $record->unit ?? $record->purchaseItems()->latest('id')->value('unit'))
                    ->searchable()
                    ->native(false),
                DatePicker::make('purchase_date')->label('Tanggal Perolehan'),
                TextInput::make('unit_price')->label('Harga Satuan')->numeric()->minValue(0),
                Select::make('ownership')->label('Sumber Dana')
                    ->options(['company' => 'Yayasan', 'grant' => 'Hibah', 'loan' => 'Pinjaman'])
                    ->default('company')->required(),
            ])
            ->action(function (InventoryBalance $record, array $data): void {
                DB::transaction(function () use ($record, $data) {
                    $balance = InventoryBalance::lockForUpdate()->findOrFail($record->id);
                    $unit = InventoryBalance::normalizeUnit($data['unit'] ?? null);
                    if (filled($balance->unit) && $unit !== null && strcasecmp($unit, $balance->unit) !== 0) {
                        throw ValidationException::withMessages([
                            'unit' => "Stok ini bersatuan {$balance->unit}. Buat stok BHP terpisah untuk satuan lain.",
                        ]);
                    }
                    if ($balance->unit === null && $unit !== null) {
                        $exists = InventoryBalance::where('id', '!=', $balance->id)
                            ->where($balance->only(['category_id', 'name', 'brand', 'location_id']) + ['unit' => $unit])
                            ->lockForUpdate()->first();
                        if ($exists) {
                            throw ValidationException::withMessages(['unit' => 'Tambahkan stok pada baris BHP yang sudah memakai satuan ini.']);
                        }
                        $balance->update(['unit' => $unit]);
                    }
                    $quantity = (int) $data['quantity'];
                    $unitPrice = filled($data['unit_price'] ?? null) ? (float) $data['unit_price'] : null;
                    $total = $unitPrice === null ? null : $unitPrice * $quantity;
                    $purchase = Purchase::create([
                        'purchase_date' => $data['purchase_date'] ?? null,
                        'ownership' => $data['ownership'],
                        'total_amount' => $total,
                    ]);
                    $item = $balance->purchaseItems()->create([
                        'purchase_id' => $purchase->id,
                        'category_id' => $balance->category_id,
                        'classification_id' => Classification::where('slug', 'barang-habis-pakai')->sole()->id,
                        'name' => $balance->name,
                        'quantity' => $quantity,
                        'unit' => $balance->unit,
                        'unit_price' => $unitPrice,
                        'total_price' => $total,
                        'is_capitalized' => false,
                    ]);
                    foreach (SupplyBarcodeGenerator::generateSub($balance, $quantity) as $barcode) {
                        $balance->units()->create([
                            'purchase_item_id' => $item->id,
                            'sub_barcode' => $barcode,
                            'status' => 'available',
                        ]);
                    }
                    $oldQuantity = $balance->quantity;
                    $balance->increment('quantity', $quantity);
                    AuditLog::create([
                        'action' => AuditAction::UPDATED,
                        'auditable_type' => InventoryBalance::class,
                        'auditable_id' => $balance->id,
                        'user_id' => Auth::id(),
                        'old_values' => ['quantity' => $oldQuantity],
                        'new_values' => ['quantity' => $balance->quantity],
                        'metadata' => ['transaction_type' => 'stock_in', 'purchase_item_id' => $item->id],
                    ]);
                });

                Notification::make()->title('Stok berhasil ditambahkan')->success()->send();
            });
    }
}
