<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'User & Access';
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasPermissionTo('users.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),

                Components\TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->visible(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),

                Components\Select::make('roles')
                    ->label('Role (Hak Akses)')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->visible(fn (string $context): bool => $context === 'create'),
            ])
            ->columns(2);
    }

    public static function permissionGroups(): Collection
    {
        return Permission::all()->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'));
    }

    public static function permissionGroupFieldName(string $group): string
    {
        return 'permission_group_'.$group;
    }

    public static function friendlyPermissionLabel(string $name): string
    {
        $labels = [
            // Dashboard
            'dashboard.view' => 'Melihat Halaman Dashboard',
            'assets.widget.total_items' => 'Card Total Barang — Menu Aset & Inventaris',
            'assets.widget.acquisition_value' => 'Card Total Harga Perolehan — Menu Aset & Inventaris',
            'dashboard.widget.total_assets' => 'Card Total Barang / Aset',
            'dashboard.widget.total_categories' => 'Card Total Kategori',
            'dashboard.widget.total_locations' => 'Card Total Lokasi',
            'dashboard.widget.total_employees' => 'Card Total Karyawan',
            'dashboard.widget.pending_mutations' => 'Card Mutasi Pending',
            'dashboard.widget.stock_opname_active' => 'Card Stock Opname Berjalan',
            'dashboard.widget.asset_status_chart' => 'Grafik Status Barang',
            'dashboard.widget.latest_movements' => 'Tabel Mutasi Terbaru',
            'dashboard.widget.asset_acquisition_value' => 'Nilai Total Perolehan Aset',
            // Assets
            'assets.view' => 'Melihat Daftar Barang & Inventaris',
            'assets.create' => 'Menambah Barang Baru',
            'assets.update' => 'Mengubah Data Barang',
            'assets.delete' => 'Menghapus Barang',
            // Supplies
            'supplies.stock_in' => 'Stok Masuk Barang Habis Pakai',
            'supplies.update' => 'Mengubah Data Barang Habis Pakai',
            // Intake
            'intake.complete' => 'Melengkapi Data Barang Masuk (Finance)',
            // Master Data
            'categories.view' => 'Melihat Master Kategori',
            'categories.create' => 'Menambah Master Kategori',
            'categories.update' => 'Mengubah Master Kategori',
            'categories.delete' => 'Menghapus Master Kategori',
            'campuses.view' => 'Melihat Master Kampus / Gedung',
            'campuses.create' => 'Menambah Master Kampus / Gedung',
            'campuses.update' => 'Mengubah Master Kampus / Gedung',
            'campuses.delete' => 'Menghapus Master Kampus / Gedung',
            'locations.view' => 'Melihat Master Ruangan / Lokasi',
            'locations.create' => 'Menambah Master Ruangan / Lokasi',
            'locations.update' => 'Mengubah Master Ruangan / Lokasi',
            'locations.delete' => 'Menghapus Master Ruangan / Lokasi',
            'employees.view' => 'Melihat Master Karyawan / PIC',
            'employees.create' => 'Menambah Master Karyawan / PIC',
            'employees.update' => 'Mengubah Master Karyawan / PIC',
            'employees.delete' => 'Menghapus Master Karyawan / PIC',
            // Movements
            'movements.view' => 'Melihat Riwayat Mutasi',
            'movements.create' => 'Mengajukan Mutasi Barang',
            'movements.approve' => 'Menyetujui Mutasi Barang',
            'movements.reject' => 'Menolak Mutasi Barang',
            'movements.complete' => 'Menyelesaikan Mutasi Barang',
            // Status
            'status.view' => 'Melihat Status & Kondisi',
            'status.update' => 'Mengubah Status / Kondisi',
            'status.approve' => 'Menyetujui Perubahan Status',
            // Stock Opname
            'stock_opname.view' => 'Melihat Sesi Stock Opname',
            'stock_opname.create' => 'Membuat Sesi Stock Opname',
            'stock_opname.update' => 'Memperbarui Item Stock Opname',
            'stock_opname.complete' => 'Menyelesaikan Stock Opname',
            // Barcode & QR
            'asset_qr.view' => 'Melihat Barcode / QR Code',
            'asset_qr.generate' => 'Mencetak Barcode & Checklist',
            'asset_scanner.use' => 'Menggunakan Fitur Scanner',
            // Financial
            'financial.view' => 'Melihat Harga Beli & Informasi Finansial',
            'financial.update' => 'Mengubah Data Keuangan / Nilai Perolehan',
            'financial.export' => 'Ekspor Data Finansial',
            // Reports
            'reports.view' => 'Melihat Halaman Laporan',
            'reports.export' => 'Ekspor Laporan (Excel/PDF)',
            // Audit
            'audit.view' => 'Melihat Log Audit Aktivitas',
            'audit.export' => 'Ekspor Riwayat Audit',
            // User Administration
            'users.view' => 'Melihat Daftar Pengguna',
            'users.create' => 'Menambah Pengguna Baru',
            'users.update' => 'Mengubah Informasi Pengguna',
            'users.delete' => 'Menghapus Pengguna',
            'roles.view' => 'Melihat Daftar Role',
            'roles.create' => 'Menambah Role Baru',
            'roles.update' => 'Mengubah Role & Permission Role',
            'roles.delete' => 'Menghapus Role',
            'permissions.view' => 'Melihat Daftar Permission',
            'permissions.manage' => 'Mengelola Permission',
            // Panels
            'panel.admin' => 'Akses Panel Administrasi (Admin)',
            'panel.inventory' => 'Akses Panel Inventaris (Inventory)',
        ];

        return $labels[$name] ?? Str::headline(str_replace(['.', '_'], ' ', $name));
    }

    /**
     * Map permission_id => nama-nama role user yang sudah memberikan permission tsb.
     */
    public static function permissionsGrantedViaRoles(User $record): Collection
    {
        return $record->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role): array => $role->permissions->map(fn (Permission $permission): array => [
                'permission_id' => $permission->id,
                'role_name' => $role->name,
            ])->all())
            ->groupBy('permission_id')
            ->map(fn (Collection $rows): string => $rows->pluck('role_name')->unique()->implode(', '));
    }

    public static function permissionChecklistSchema(?User $record = null): array
    {
        $viaRoles = $record ? static::permissionsGrantedViaRoles($record) : collect();

        return static::permissionGroups()
            ->map(function (Collection $permissions, string $group) use ($viaRoles) {
                $label = Str::headline($group);

                return Section::make($label)
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Components\CheckboxList::make(static::permissionGroupFieldName($group))
                            ->hiddenLabel()
                            ->options($permissions->pluck('name', 'id')->all())
                            ->descriptions(
                                $permissions->pluck('id')
                                    ->mapWithKeys(fn (int $id): array => [$id => $viaRoles->has($id) ? "Sudah aktif via role: {$viaRoles->get($id)}" : null])
                                    ->filter()
                                    ->all()
                            )
                            ->columns(2)
                            ->bulkToggleable(),
                    ]);
            })
            ->values()
            ->all();
    }

    public static function permissionGroupDataFromRecord(User $record): array
    {
        $selected = $record->permissions()->pluck('permissions.id')->all();

        return static::permissionGroups()
            ->mapWithKeys(fn (Collection $permissions, string $group): array => [
                static::permissionGroupFieldName($group) => $permissions->pluck('id')->intersect($selected)->values()->all(),
            ])
            ->all();
    }

    public static function extractPermissionIds(array $data): array
    {
        return collect($data)
            ->filter(fn ($value, $key): bool => str_starts_with($key, 'permission_group_'))
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->grow(true),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->separator(', '),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Override Akun')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->tooltip('Detail User'),

                    Action::make('manageRoles')
                        ->label('Kelola Role')
                        ->icon('heroicon-o-shield-check')
                        ->tooltip('Kelola Role')
                        ->modalHeading('Kelola Role User')
                        ->modalWidth('md')
                        ->fillForm(fn (User $record): array => [
                            'roles' => $record->roles->pluck('id')->all(),
                        ])
                        ->form([
                            Components\CheckboxList::make('roles')
                                ->hiddenLabel()
                                ->options(Role::pluck('name', 'id')->all())
                                ->bulkToggleable(),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->roles()->sync($data['roles'] ?? []);

                            Notification::make()
                                ->title('Role User Berhasil Diperbarui')
                                ->success()
                                ->send();
                        }),

                    Action::make('changePassword')
                        ->label('Ubah Password')
                        ->icon('heroicon-o-lock-closed')
                        ->tooltip('Ubah Password')
                        ->modalHeading('Ubah Password User')
                        ->modalWidth('md')
                        ->form([
                            Components\TextInput::make('password')
                                ->label('Password Baru')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->same('password_confirmation'),

                            Components\TextInput::make('password_confirmation')
                                ->label('Konfirmasi Password Baru')
                                ->password()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->update([
                                'password' => Hash::make($data['password']),
                            ]);

                            Notification::make()
                                ->title('Password Berhasil Diperbarui')
                                ->success()
                                ->send();
                        }),

                    EditAction::make()
                        ->tooltip('Edit User')
                        ->modalHeading('Edit Informasi User'),

                    Action::make('managePermissions')
                        ->label('Kelola Permission')
                        ->icon('heroicon-o-key')
                        ->tooltip('Kelola Permission')
                        ->url(fn (User $record): string => static::getUrl('permissions', ['record' => $record])),

                    DeleteAction::make()
                        ->tooltip('Hapus User'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
            'permissions' => Pages\ManagePermissions::route('/{record}/permissions'),
        ];
    }
}
