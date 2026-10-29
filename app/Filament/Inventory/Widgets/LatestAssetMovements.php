<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\Mutation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class LatestAssetMovements extends TableWidget
{
    protected int | string | array $columnSpan = 2;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasPermissionTo('dashboard.widget.latest_movements');
    }

    protected function getTableHeading(): string
    {
        return 'Mutasi Barang Terbaru';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Mutation::query()
                    ->latest('mutation_date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('mutation_number')
                    ->label('Nomor Mutasi')
                    ->searchable(),
                TextColumn::make('sourceLocation.name')
                    ->label('Dari')
                    ->placeholder('-'),
                TextColumn::make('destinationLocation.name')
                    ->label('Ke')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('mutation_date')
                    ->label('Tanggal')
                    ->dateTime('d M Y'),
            ])
            ->paginated(false);
    }
}
