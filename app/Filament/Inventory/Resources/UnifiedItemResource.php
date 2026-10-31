<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\UnifiedItemResource\Pages;
use App\Filament\Support\AddPhotoActions;
use App\Filament\Support\ChangeBrandActions;
use App\Filament\Support\ChangeCategoryActions;
use App\Filament\Support\ChangeKondisiActions;
use App\Filament\Support\ChangeStatusActions;
use App\Filament\Support\DeleteItemsActions;
use App\Filament\Support\MoveLocationActions;
use App\Filament\Support\PrintBarcodeActions;
use App\Filament\Support\PrintChecklistActions;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Location;
use App\Models\UnifiedItem;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class UnifiedItemResource extends Resource
{
    protected static ?string $model = UnifiedItem::class;

    protected static ?string $slug = 'semua-barang';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pengelolaan Barang';
    }

    public static function getModelLabel(): string
    {
        return 'Semua Barang';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Semua Barang';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('assets.view');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('has_photo')
                    ->label('Foto')
                    ->boolean()
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (UnifiedItem $record): string => $record->has_photo ? 'Sudah ada foto' : 'Belum ada foto')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('Kode Barang')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Barang')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk/Tipe')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_name')
                    ->label('Satuan')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Kategori')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('classification_name')
                    ->label('Klasifikasi')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('QTY')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_name')
                    ->label('Lokasi')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga Perolehan')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp '.number_format((float) $state, 0, ',', '.') : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'stock' => 'Stok (Gudang)',
                        'active' => 'Aktif / Digunakan',
                        'borrowed' => 'Dipinjam',
                        'maintenance' => 'Dalam Perbaikan',
                        'lost' => 'Hilang',
                        'sold' => 'Terjual',
                        'disposed' => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed' => 'Dimusnahkan',
                        'baru_dilaporkan' => 'Baru Dilaporkan',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'stock', 'active' => 'success',
                        'borrowed', 'maintenance' => 'warning',
                        'lost', 'destroyed' => 'danger',
                        'sold', 'disposed', 'administratively_deleted' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'good' => 'Baik',
                        'minor_damage' => 'Rusak Ringan',
                        'major_damage' => 'Rusak Berat',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'good' => 'success',
                        'minor_damage' => 'warning',
                        'major_damage' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // has_photo dihitung di subquery UnifiedItem (asset_photos untuk
                // Aset/Inventaris/BHP individual, inventory_balances.photo untuk
                // Stok BHP), jadi filter jalan di query backend + pagination.
                Tables\Filters\SelectFilter::make('photo_status')
                    ->label('Status Foto')
                    ->placeholder('Semua')
                    ->options([
                        'with' => 'Sudah Ada Foto',
                        'without' => 'Belum Ada Foto',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with' => $query->where('has_photo', 1),
                        'without' => $query->where('has_photo', 0),
                        default => $query,
                    })
                    ->native(false),
                // Dipakai juga oleh aksi baris "Merk/tipe sama" untuk memilih
                // barang sejenis sebelum Tambah Foto Massal.
                Tables\Filters\SelectFilter::make('brand')
                    ->label('Merk/Tipe')
                    ->options(fn () => DB::table('assets')
                        ->whereNull('deleted_at')
                        ->where('brand', '<>', '')
                        ->distinct()
                        ->pluck('brand')
                        ->merge(DB::table('inventory_balances')->where('brand', '<>', '')->distinct()->pluck('brand'))
                        ->unique()
                        ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                        ->mapWithKeys(fn ($brand) => [$brand => $brand])
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('category_name')
                    ->label('Kategori')
                    ->options(fn () => Category::pluck('name', 'name')->toArray())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock' => 'Stok (Gudang)',
                        'available' => 'Tersedia',
                        'active' => 'Aktif / Digunakan',
                        'borrowed' => 'Dipinjam',
                        'maintenance' => 'Dalam Perbaikan',
                        'lost' => 'Hilang',
                        'sold' => 'Terjual',
                        'disposed' => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed' => 'Dimusnahkan',
                        'baru_dilaporkan' => 'Baru Dilaporkan',
                    ]),
                Tables\Filters\SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'good' => 'Baik',
                        'minor_damage' => 'Rusak Ringan',
                        'major_damage' => 'Rusak Berat',
                    ]),
                Tables\Filters\Filter::make('campus_location')
                    // Dropdown Gedung/Ruangan dirender di toolbar tabel (lihat
                    // AppServiceProvider); field hidden ini cuma nampung state-nya.
                    ->form([
                        Hidden::make('campus_id'),
                        Hidden::make('location_id'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['campus_id'] ?? null,
                                function (Builder $query, $campusId) {
                                    $locationNames = Location::where('campus_id', $campusId)->pluck('name')->toArray();

                                    return $query->whereIn('location_name', $locationNames);
                                }
                            )
                            ->when(
                                $data['location_id'] ?? null,
                                function (Builder $query, $locationId) {
                                    $location = Location::find($locationId);

                                    return $query->where('location_name', $location?->name);
                                }
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['campus_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Gedung: '.Campus::find($data['campus_id'])?->name)
                                ->removeField('campus_id');
                        }
                        if ($data['location_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Ruangan: '.Location::find($data['location_id'])?->name)
                                ->removeField('location_id');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('manage')
                        ->label('Kelola / Detail')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->url(function (UnifiedItem $record) {
                            if ($record->row_type === 'supply') {
                                return InventoryBalanceResource::getUrl('index', ['tableSearch' => $record->item_name]);
                            } else {
                                if (strtolower($record->classification_name) === 'aset') {
                                    return AssetCategoryResource::getUrl('view', ['record' => $record->route_key]);
                                } else {
                                    return InventoryCategoryResource::getUrl('view', ['record' => $record->route_key]);
                                }
                            }
                        }),
                    AddPhotoActions::viewPhotoAction(),
                    AddPhotoActions::rowAction(),
                    Action::make('filterSameBrand')
                        ->label('Merk/tipe sama')
                        ->tooltip('Tampilkan barang dengan merk/tipe sama')
                        ->icon('heroicon-o-funnel')
                        ->color('gray')
                        ->visible(fn (UnifiedItem $record): bool => filled($record->brand))
                        ->action(function (UnifiedItem $record, Component $livewire): void {
                            // Hanya memfilter daftar; pengguna tetap memeriksa & mencentang sendiri.
                            $livewire->tableFilters['brand'] = ['value' => $record->brand];
                            $livewire->updatedTableFilters();
                        }),
                ]),
            ])
            // Centang tetap ada saat search/filter berubah. Tautan "Pilih semua N
            // data" dimatikan, karena tanpa filter-ulang ia bisa memilih seluruh tabel.
            ->deselectAllRecordsWhenFiltered(false)
            ->selectCurrentPageOnly()
            ->bulkActions([
                PrintBarcodeActions::bulkAction(),
                PrintChecklistActions::bulkAction(),
                AddPhotoActions::bulkAction(),
                MoveLocationActions::bulkAction(),
                ChangeCategoryActions::bulkAction(),
                ChangeBrandActions::bulkAction(),
                ChangeStatusActions::bulkAction(),
                ChangeKondisiActions::bulkAction(),
                DeleteItemsActions::bulkAction(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnifiedItems::route('/'),
            'create' => Pages\CreateUnifiedItem::route('/create'),
        ];
    }
}
