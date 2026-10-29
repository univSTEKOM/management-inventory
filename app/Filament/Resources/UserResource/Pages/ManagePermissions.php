<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManagePermissions extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Kelola Hak Akses & Override: ' . $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Atur izin khusus per-akun dengan 3 opsi: Ikuti Role (inherit), Izinkan (allow), atau Tolak (deny). Penolakan (deny) pada akun akan mengalahkan izin yang diwariskan dari Role.';
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $record */
        $record = $this->getRecord();
        $isSuperadmin = $record->hasRole('Superadmin');

        // Petakan role yang dimiliki user
        $roleNames = $record->roles->pluck('name')->implode(', ') ?: 'Tidak ada role';

        // Petakan permission_id yang diberikan oleh role
        $rolePermissionIds = $record->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
            ->unique()
            ->flip()
            ->all();

        return $schema
            ->components([
                // Bagian Informasi Akun
                Section::make('Informasi Akun & Status Hak Akses')
                    ->description('Ringkasan peran dan mekanisme resolusi hak akses akun ini.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Components\Placeholder::make('user_name')
                                    ->label('Nama Pengguna')
                                    ->content($record->name),

                                Components\Placeholder::make('user_email')
                                    ->label('Alamat Email')
                                    ->content($record->email),

                                Components\Placeholder::make('user_roles')
                                    ->label('Role Saat Ini')
                                    ->content(new HtmlString('<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">' . e($roleNames) . '</span>')),
                            ]),

                        Components\Placeholder::make('override_explanation')
                            ->label('Petunjuk Override')
                            ->content(new HtmlString(
                                $isSuperadmin
                                    ? '<div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-800 dark:text-amber-200 font-medium">⚠️ Akun ini memiliki role <strong>Superadmin</strong>. Superadmin memiliki hak akses penuh otomatis (bypass) ke seluruh modul dan fitur sistem.</div>'
                                    : '<div class="p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg text-xs text-gray-600 dark:text-gray-300 space-y-1">'
                                        . '<p><strong>• Ikuti Role (Inherit):</strong> Hak akses mengikuti konfigurasi role pengguna.</p>'
                                        . '<p><strong>• Izinkan (Allow):</strong> Memberikan hak akses khusus meskipun role tidak memilikinya.</p>'
                                        . '<p><strong>• Tolak (Deny):</strong> Secara tegas mencabut hak akses ini, <u>mengalahkan izin dari role</u>.</p>'
                                    . '</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // Render checklist permission per kategori
                ...static::buildPermissionCategoriesSchema($record, $rolePermissionIds),
            ])
            ->columns([
                'default' => 1,
                'md' => 1,
            ]);
    }

    /**
     * Membangun schema formulir untuk setiap kategori permission.
     */
    protected static function buildPermissionCategoriesSchema(User $record, array $rolePermissionIds): array
    {
        $permissions = Permission::all();

        // Pengelompokan logis kategori
        $categoryDefinitions = [
            'dashboard' => [
                'label' => 'Dashboard & Komponen Widget',
                'description' => 'Kendali tampilan card statistik, grafik, dan ringkasan angka pada beranda.',
                'icon' => 'heroicon-o-home',
            ],
            'assets' => [
                'label' => 'Inventaris & Aset Barang',
                'description' => 'Akses menu daftar barang, penambahan, pengubahan, dan penghapusan data aset.',
                'icon' => 'heroicon-o-cube',
            ],
            'financial' => [
                'label' => 'Keuangan & Nilai Perolehan (Sensitif)',
                'description' => 'Visibilitas harga beli, nilai perolehan, nilai buku, dan penyusutan aset.',
                'icon' => 'heroicon-o-banknotes',
            ],
            'supplies' => [
                'label' => 'Barang Habis Pakai (BHP)',
                'description' => 'Pencatatan stok masuk dan pembaruan barang habis pakai.',
                'icon' => 'heroicon-o-archive-box',
            ],
            'intake' => [
                'label' => 'Barang Masuk & Intake',
                'description' => 'Akses antrian dan aksi pelengkapan data barang baru datang dari lapangan.',
                'icon' => 'heroicon-o-inbox-arrow-down',
            ],
            'movements' => [
                'label' => 'Mutasi Barang & Pemindahan',
                'description' => 'Pengajuan, persetujuan, dan pencatatan perpindahan lokasi barang.',
                'icon' => 'heroicon-o-arrows-right-left',
            ],
            'status' => [
                'label' => 'Status & Perubahan Kondisi',
                'description' => 'Pengajuan dan persetujuan perubahan kondisi fisik atau status barang.',
                'icon' => 'heroicon-o-tag',
            ],
            'stock_opname' => [
                'label' => 'Stock Opname (Pemeriksaan Fisik)',
                'description' => 'Pembuatan sesi opname fisik, pencatatan kecocokan, dan penyelesaian opname.',
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            'asset_qr' => [
                'label' => 'Barcode, QR Code & Scanner',
                'description' => 'Pencetakan label barcode aset dan penggunaan scanner kamera.',
                'icon' => 'heroicon-o-qr-code',
            ],
            'reports' => [
                'label' => 'Laporan & Ekspor Data',
                'description' => 'Melihat ringkasan laporan dan mengunduh data format Excel / PDF.',
                'icon' => 'heroicon-o-document-chart-bar',
            ],
            'audit' => [
                'label' => 'Audit Log & Jejak Aktivitas',
                'description' => 'Melihat riwayat log perubahan data dan ekspor jejak audit sistem.',
                'icon' => 'heroicon-o-clock',
            ],
            'master' => [
                'label' => 'Data Master (Kategori, Gedung, Ruangan, PIC)',
                'description' => 'Pengelolaan data referensi kampus, lokasi, kategori, dan karyawan.',
                'icon' => 'heroicon-o-rectangle-stack',
            ],
            'users' => [
                'label' => 'Manajemen Pengguna, Role & Hak Akses',
                'description' => 'Pengelolaan akun, penetapan role, dan konfigurasi permission.',
                'icon' => 'heroicon-o-users',
            ],
            'panel' => [
                'label' => 'Akses Panel Filament',
                'description' => 'Penentuan panel mana yang dapat dibuka (Admin atau Inventory).',
                'icon' => 'heroicon-o-shield-check',
            ],
        ];

        // Kelompokkan permission ke kategori yang ditentukan
        $grouped = $permissions->groupBy(function (Permission $permission) {
            $name = $permission->name;
            if (str_starts_with($name, 'dashboard.')) return 'dashboard';
            if (str_starts_with($name, 'financial.')) return 'financial';
            if (str_starts_with($name, 'assets.')) return 'assets';
            if (str_starts_with($name, 'supplies.')) return 'supplies';
            if (str_starts_with($name, 'intake.')) return 'intake';
            if (str_starts_with($name, 'movements.')) return 'movements';
            if (str_starts_with($name, 'status.')) return 'status';
            if (str_starts_with($name, 'stock_opname.')) return 'stock_opname';
            if (str_starts_with($name, 'asset_qr.') || str_starts_with($name, 'asset_scanner.')) return 'asset_qr';
            if (str_starts_with($name, 'reports.')) return 'reports';
            if (str_starts_with($name, 'audit.')) return 'audit';
            if (str_starts_with($name, 'categories.') || str_starts_with($name, 'campuses.') || str_starts_with($name, 'locations.') || str_starts_with($name, 'employees.')) return 'master';
            if (str_starts_with($name, 'users.') || str_starts_with($name, 'roles.') || str_starts_with($name, 'permissions.')) return 'users';
            if (str_starts_with($name, 'panel.')) return 'panel';
            return 'other';
        });

        $sections = [];

        foreach ($categoryDefinitions as $catKey => $catMeta) {
            $catPermissions = $grouped->get($catKey, collect());
            if ($catPermissions->isEmpty()) {
                continue;
            }

            $fields = [];
            foreach ($catPermissions as $permission) {
                $permId = $permission->id;
                $hasRolePermission = isset($rolePermissionIds[$permId]);
                $friendlyLabel = UserResource::friendlyPermissionLabel($permission->name);

                $roleBadge = $hasRolePermission
                    ? '<span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">✓ Diizinkan via Role</span>'
                    : '<span class="text-xs text-gray-400 dark:text-gray-500">✗ Tidak ada di Role</span>';

                $fields[] = Components\ToggleButtons::make("perm_{$permId}")
                    ->label(new HtmlString("
                        <div class='flex flex-col sm:flex-row sm:items-center justify-between gap-1 w-full'>
                            <span class='font-medium text-gray-900 dark:text-gray-100'>{$friendlyLabel}</span>
                            <div class='flex items-center gap-2'>
                                <code class='text-[10px] text-gray-400 bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded'>{$permission->name}</code>
                                {$roleBadge}
                            </div>
                        </div>
                    "))
                    ->options([
                        'inherit' => 'Ikuti Role',
                        'allow' => 'Izinkan (Allow)',
                        'deny' => 'Tolak (Deny)',
                    ])
                    ->colors([
                        'inherit' => 'gray',
                        'allow' => 'success',
                        'deny' => 'danger',
                    ])
                    ->icons([
                        'inherit' => 'heroicon-m-arrow-path',
                        'allow' => 'heroicon-m-check-circle',
                        'deny' => 'heroicon-m-x-circle',
                    ])
                    ->inline()
                    ->default('inherit');
            }

            $sections[] = Section::make($catMeta['label'])
                ->description($catMeta['description'])
                ->icon($catMeta['icon'])
                ->collapsible()
                ->compact()
                ->schema($fields);
        }

        return $sections;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        // Ambil semua permission langsung yang tercatat di pivot
        $overrides = $record->permissions()
            ->get()
            ->pluck('pivot.type', 'id')
            ->all();

        $formData = [];
        foreach (Permission::all() as $permission) {
            $formData["perm_{$permission->id}"] = $overrides[$permission->id] ?? 'inherit';
        }

        return array_merge($data, $formData);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        // Ambil override lama untuk audit log
        $oldOverrides = $record->permissions()
            ->get()
            ->mapWithKeys(fn (Permission $p) => [$p->name => $p->pivot->type])
            ->all();

        $permissions = Permission::all();
        $syncData = [];
        $newOverrides = [];

        foreach ($permissions as $permission) {
            $val = $data["perm_{$permission->id}"] ?? 'inherit';

            if ($val === 'allow' || $val === 'deny') {
                $syncData[$permission->id] = ['type' => $val];
                $newOverrides[$permission->name] = $val;
            }
        }

        // Simpan ke pivot permission_user
        $record->permissions()->sync($syncData);

        // Catat ke Audit Logger
        AuditLogger::log(
            'permission_override_updated',
            $record,
            $oldOverrides,
            $newOverrides,
            'Pembaruan override permission akun oleh administrator'
        );

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Hak Akses & Override Akun Berhasil Diperbarui';
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
