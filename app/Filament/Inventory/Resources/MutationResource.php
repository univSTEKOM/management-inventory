<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\MutationResource\Pages;
use App\Models\Mutation;
use App\Services\DirectMutationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MutationResource extends Resource
{
    protected static ?string $model = Mutation::class;

    public static function getNavigationIcon(): string { return 'heroicon-o-arrows-right-left'; }
    public static function getNavigationGroup(): string { return 'Asset Management'; }
    public static function getModelLabel(): string { return 'Mutasi Barang'; }
    public static function getPluralModelLabel(): string { return 'Mutasi Barang'; }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('movements.view');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum Ada Riwayat Mutasi')
            ->emptyStateDescription('Buat transaksi mutasi baru untuk memindahkan aset atau persediaan barang ke ruangan lain.')
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->columns([
                Tables\Columns\TextColumn::make('mutation_number')
                    ->label('No. Mutasi')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor mutasi disalin')
                    ->weight('bold')
                    ->icon('heroicon-m-hashtag'),
                Tables\Columns\TextColumn::make('sourceCampus.name')
                    ->label('Gedung Asal')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sourceLocation.name')
                    ->label('Ruangan Asal')
                    ->description(fn (Mutation $record) => $record->sourceCampus?->name),
                Tables\Columns\TextColumn::make('destinationCampus.name')
                    ->label('Gedung Tujuan')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('destinationLocation.name')
                    ->label('Ruangan Tujuan')
                    ->description(fn (Mutation $record) => $record->destinationCampus?->name),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Barang')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-cube')
                    ->suffix(' baris'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::statusLabel($state))
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'pending', 'approved' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'completed' => 'heroicon-m-check-circle',
                        'pending', 'approved' => 'heroicon-m-clock',
                        'rejected' => 'heroicon-m-x-circle',
                        'cancelled' => 'heroicon-m-no-symbol',
                        default => 'heroicon-m-information-circle',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mutation_date')
                    ->label('Diproses')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Dibuat oleh')
                    ->icon('heroicon-m-user')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Selesai',
                        'pending' => 'Belum diproses (lama)',
                        'approved' => 'Disetujui (lama)',
                        'rejected' => 'Ditolak (lama)',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('source_campus_id')
                    ->label('Gedung Asal')
                    ->relationship('sourceCampus', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Tables\Filters\SelectFilter::make('destination_campus_id')
                    ->label('Gedung Tujuan')
                    ->relationship('destinationCampus', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->filtersFormColumns(3)
            ->actions([
                ViewAction::make()->icon('heroicon-m-eye'),
                Action::make('receipt')
                    ->label('Cetak')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->visible(fn (Mutation $record) => $record->status === 'completed')
                    ->url(fn (Mutation $record) => route('mutation.receipt', $record))
                    ->openUrlInNewTab(),
                Action::make('process')
                    ->label('Proses Mutasi')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->requiresConfirmation()
                    ->modalDescription('Proses perpindahan fisik transaksi lama ini sekarang. Seluruh barang akan divalidasi ulang.')
                    ->visible(fn (Mutation $record) => auth()->user()->can('process', $record))
                    ->action(fn (Mutation $record) => app(DirectMutationService::class)->process(auth()->user(), $record)),
            Action::make('cancel')->label('Batalkan')->color('danger')->requiresConfirmation()
                    ->visible(fn (Mutation $record) => auth()->user()->can('cancel', $record))
                    ->action(fn (Mutation $record) => app(DirectMutationService::class)->cancel(auth()->user(), $record)),
            ]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai',
            'pending' => 'Belum diproses (lama)',
            'approved' => 'Disetujui (lama)',
            'rejected' => 'Ditolak (lama)',
            'cancelled' => 'Dibatalkan',
            default => $status,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMutations::route('/'),
            'create' => Pages\CreateMutation::route('/create'),
            'view' => Pages\ViewMutation::route('/{record}'),
        ];
    }
}
