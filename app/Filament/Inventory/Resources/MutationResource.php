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

class MutationResource extends Resource
{
    protected static ?string $model = Mutation::class;

    public static function getNavigationIcon(): string { return 'heroicon-o-arrows-right-left'; }
    public static function getNavigationGroup(): string { return 'Asset Management'; }
    public static function getModelLabel(): string { return 'Mutasi Barang'; }
    public static function getPluralModelLabel(): string { return 'Mutasi Barang'; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('mutation_number')->label('No. Mutasi')->searchable(),
            Tables\Columns\TextColumn::make('sourceCampus.name')->label('Gedung Asal'),
            Tables\Columns\TextColumn::make('sourceLocation.name')->label('Ruangan Asal'),
            Tables\Columns\TextColumn::make('destinationCampus.name')->label('Gedung Tujuan'),
            Tables\Columns\TextColumn::make('destinationLocation.name')->label('Ruangan Tujuan'),
            Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Jumlah Baris'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn ($state) => self::statusLabel($state))
                ->color(fn ($state) => match ($state) {
                    'completed' => 'success', 'pending', 'approved' => 'warning', 'rejected' => 'danger', default => 'gray',
                }),
            Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('mutation_date')->label('Diproses')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('requestedBy.name')->label('Dibuat oleh'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'completed' => 'Selesai', 'pending' => 'Belum diproses (lama)',
                'approved' => 'Disetujui (lama)', 'rejected' => 'Ditolak (lama)', 'cancelled' => 'Dibatalkan',
            ]),
            Tables\Filters\SelectFilter::make('source_campus_id')->label('Gedung Asal')->relationship('sourceCampus', 'name'),
            Tables\Filters\SelectFilter::make('destination_campus_id')->label('Gedung Tujuan')->relationship('destinationCampus', 'name'),
        ])->actions([
            ViewAction::make(),
            Action::make('process')->label('Proses Mutasi')->requiresConfirmation()
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
            'completed' => 'Selesai', 'pending' => 'Belum diproses (lama)',
            'approved' => 'Disetujui (lama)', 'rejected' => 'Ditolak (lama)',
            'cancelled' => 'Dibatalkan', default => $status,
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
