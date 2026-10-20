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

class AddSupplyStockAction
{
    public static function make(): Action
    {
        return Action::make('stock_in')
            ->label('Tambah Stok')
            ->icon('heroicon-o-plus-circle')
            ->authorize(fn () => Auth::user()?->hasPermissionTo('assets.create') ?? false)
            ->modalHeading(fn (InventoryBalance $record) => 'Tambah Stok: '.$record->name)
            ->form([
                TextInput::make('quantity')->label('Jumlah Tambahan')->integer()->minValue(1)->required(),
                TextInput::make('unit')->label('Satuan')->maxLength(255)
                    ->default(fn (InventoryBalance $record) => $record->purchaseItems()->latest('id')->value('unit')),
                DatePicker::make('purchase_date')->label('Tanggal Perolehan'),
                TextInput::make('unit_price')->label('Harga Satuan')->numeric()->minValue(0),
                Select::make('ownership')->label('Sumber Dana')
                    ->options(['company' => 'Yayasan', 'grant' => 'Hibah', 'loan' => 'Pinjaman'])
                    ->default('company')->required(),
            ])
            ->action(function (InventoryBalance $record, array $data): void {
                DB::transaction(function () use ($record, $data) {
                    $balance = InventoryBalance::lockForUpdate()->findOrFail($record->id);
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
                        'unit' => $data['unit'] ?? null,
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
