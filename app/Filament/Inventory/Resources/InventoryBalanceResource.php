<?php

namespace App\Filament\Inventory\Resources;

use App\Enums\AuditAction;
use App\Filament\Inventory\Resources\InventoryBalanceResource\Pages;
use App\Filament\Support\ChangeBrandActions;
use App\Filament\Support\ChangeCategoryActions;
use App\Filament\Support\DeleteItemsActions;
use App\Filament\Support\MoveLocationActions;
use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryBalanceUnit;
use App\Models\PurchaseItem;
use App\Support\SharedPhotoFiles;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;

    protected static ?int $navigationSort = 4;

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengelolaan Barang';
    }

    public static function getModelLabel(): string
    {
        return 'Barang Habis Pakai';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Barang Habis Pakai';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('assets.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('has_photo')
                    ->label('Foto')
                    ->boolean()
                    ->getStateUsing(fn (InventoryBalance $record): bool => filled($record->photo))
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (InventoryBalance $record): string => filled($record->photo) ? 'Sudah ada foto' : 'Belum ada foto')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk/Tipe')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_unit')
                    ->label('Satuan')
                    ->toggleable()
                    ->searchable(false)
                    ->sortable(false)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Kampus / Gedung')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Lokasi / Ruangan')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stok Tersedia')
                    ->toggleable()
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('photo_status')
                    ->label('Status Foto')
                    ->placeholder('Semua')
                    ->options([
                        'with' => 'Sudah Ada Foto',
                        'without' => 'Belum Ada Foto',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with' => $query->whereNotNull('photo')->where('photo', '<>', ''),
                        'without' => $query->where(fn ($q) => $q->whereNull('photo')->orWhere('photo', '')),
                        default => $query,
                    })
                    ->native(false),
            ])
            ->actions([
                ActionGroup::make([
                    \App\Filament\Support\AddPhotoActions::viewPhotoAction(),
                    \App\Filament\Support\AddPhotoActions::rowAction(),
                    Action::make('edit_info')
                        ->authorize(fn () => Auth::user()?->hasPermissionTo('supplies.update') ?? false)
                        ->label('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->modalHeading(fn (InventoryBalance $record) => 'Edit: '.$record->name)
                        ->modalDescription('Jika kategori, nama, merk, dan lokasi sama dengan barang lain, stok beserta riwayat pembelian akan digabung.')
                        ->fillForm(fn (InventoryBalance $record): array => [
                            'name' => $record->name,
                            'category_id' => $record->category_id,
                            'brand' => $record->brand,
                            'location_id' => $record->location_id,
                            'campus_id' => $record->campus_id,
                            'pic_id' => $record->pic_id,
                            'photo' => $record->photo,
                        ])
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('category_id')
                                ->label('Kategori')
                                ->options(fn () => \App\Models\Category::whereHas('classifications', fn ($query) => $query->where('slug', 'barang-habis-pakai'))->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('brand', null)),
                            Forms\Components\Select::make('brand')
                                ->label('Merk / Tipe')
                                ->searchable()
                                ->options(function ($get) {
                                    $options = \App\Filament\Support\ItemBrandOptions::forCategory(
                                        $get('category_id'),
                                        \App\Models\Classification::where('slug', 'barang-habis-pakai')->value('id'),
                                    );
                                    if (filled($get('brand'))) {
                                        $options[$get('brand')] = $get('brand');
                                    }

                                    return $options;
                                })
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('brand')->label('Merk/Tipe Baru')->required()->maxLength(255),
                                ])
                                ->createOptionUsing(fn (array $data) => $data['brand']),
                            Forms\Components\Select::make('campus_id')
                                ->label('Gedung / Kampus')
                                ->relationship('campus', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('location_id', null)),
                            Forms\Components\Select::make('location_id')
                                ->label('Ruangan / Lokasi')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('pic_id')
                                ->label('PIC (Penanggungjawab)')
                                ->relationship('pic', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\FileUpload::make('photo')
                                ->label('Foto Barang')
                                ->image()
                                ->disk('s3')
                                ->directory('inventory-photos')
                                ->imageEditor()
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('1200')
                                ->imageResizeTargetHeight('1200')
                                ->maxSize(5120)
                                ->panelLayout('integrated')
                                ->helperText('Satu foto representatif untuk jenis barang ini (maks 5MB).')
                                ->columnSpanFull(),
                        ])
                        ->action(function (InventoryBalance $record, array $data): void {
                            $photoState = $data['photo'] ?? [];
                            // FileUpload multiple=false tapi fillForm pakai array, normalize:
                            $newPhoto = is_array($photoState) ? (reset($photoState) ?: null) : ($photoState ?: null);

                            // Hapus foto lama dari S3 jika diganti, kecuali file-nya
                            // masih dipakai barang lain (foto massal).
                            if ($record->photo && $newPhoto !== $record->photo) {
                                SharedPhotoFiles::deleteIfUnreferenced($record->photo, ['s3'], exceptBalanceId: $record->id);
                            }

                            \App\Filament\Support\InventoryBalanceMerger::apply($record, array_merge(
                                Arr::except($data, ['photo']),
                                ['photo' => $newPhoto],
                            ), 'updated');

                            Notification::make()
                                ->title('Berhasil diperbarui')
                                ->success()
                                ->send();
                        }),

                    \App\Filament\Support\AddSupplyStockAction::make(),

                    Action::make('stock_out')
                        ->label('Gunakan Stok')
                        ->icon('heroicon-o-minus-circle')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('sub_barcodes')
                                ->label('Scan/Pilih Barcode Unit')
                                ->multiple()
                                ->searchable()
                                ->options(fn (InventoryBalance $record) => $record->units()->where('status', 'available')->pluck('sub_barcode', 'sub_barcode')
                                )
                                ->required()
                                ->minItems(1)
                                ->maxItems(fn (InventoryBalance $record) => $record->quantity)
                                ->helperText('Pilih unit spesifik yang akan dikeluarkan.'),
                            Forms\Components\Textarea::make('reason')
                                ->label('Alasan / Tujuan Penggunaan')
                                ->required(),
                        ])
                        ->action(function (InventoryBalance $record, array $data) {
                            $subBarcodes = $data['sub_barcodes'];
                            $quantity = count($subBarcodes);
                            $reason = $data['reason'] ?? '';

                            try {
                                DB::transaction(function () use ($record, $subBarcodes, $quantity, $reason) {
                                    // 1. Pessimistic Locking on Balance
                                    $lockedBalance = InventoryBalance::where('id', $record->id)->lockForUpdate()->first();

                                    // 2. Lock and check units
                                    $lockedUnits = InventoryBalanceUnit::where('inventory_balance_id', $record->id)
                                        ->whereIn('sub_barcode', $subBarcodes)
                                        ->lockForUpdate()
                                        ->get();

                                    if ($lockedUnits->count() !== $quantity) {
                                        throw new \Exception('Beberapa barcode tidak valid atau bukan milik master barang ini.');
                                    }

                                    $usedUnits = $lockedUnits->where('status', '!=', 'available');
                                    if ($usedUnits->count() > 0) {
                                        throw new \Exception('Beberapa barcode sudah digunakan (Stock OUT) atau tidak tersedia.');
                                    }

                                    // 3. Update Units
                                    InventoryBalanceUnit::whereIn('id', $lockedUnits->pluck('id'))->update(['status' => 'used']);

                                    $oldQuantity = $lockedBalance->quantity;
                                    $newQuantity = $oldQuantity - $quantity;

                                    if ($newQuantity < 0) {
                                        throw new \Exception("Stok tidak mencukupi. Stok saat ini: {$oldQuantity}");
                                    }

                                    // 4. Decrement Balance
                                    $lockedBalance->quantity = $newQuantity;
                                    $lockedBalance->save();

                                    // 5. Audit Trail
                                    AuditLog::create([
                                        'action' => AuditAction::UPDATED,
                                        'auditable_type' => InventoryBalance::class,
                                        'auditable_id' => $record->id,
                                        'user_id' => Auth::id(),
                                        'old_values' => ['quantity' => $oldQuantity],
                                        'new_values' => ['quantity' => $newQuantity],
                                        'metadata' => [
                                            'reason' => $reason,
                                            'usage_quantity' => $quantity,
                                            'sub_barcodes' => $subBarcodes,
                                            'transaction_type' => 'stock_out',
                                        ],
                                    ]);
                                });

                                Notification::make()
                                    ->title('Berhasil')
                                    ->body("Stok berhasil dikeluarkan sebanyak {$quantity} unit.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn (InventoryBalance $record): bool => $record->quantity > 0),
                ]),
            ])
            // Centang tetap ada saat search/filter berubah. Tautan "Pilih semua N
            // data" dimatikan, karena tanpa filter-ulang ia bisa memilih seluruh tabel.
            ->deselectAllRecordsWhenFiltered(false)
            ->selectCurrentPageOnly()
            ->bulkActions([
                \App\Filament\Support\AddPhotoActions::bulkAction(),
                MoveLocationActions::bulkAction(),
                ChangeCategoryActions::bulkAction(),
                ChangeBrandActions::bulkAction(),
                DeleteItemsActions::bulkAction(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect([
                'last_unit' => PurchaseItem::select('unit')
                    ->whereColumn('inventory_balance_id', 'inventory_balances.id')
                    ->whereNotNull('unit')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryBalances::route('/'),
        ];
    }
}
