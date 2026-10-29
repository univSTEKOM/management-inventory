<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRouteUlid, HasApiTokens;

    /**
     * Diekspos ke API (mis. GET /profile) supaya frontend tahu role user
     * yang sebenarnya, bukan label statis.
     */
    protected $appends = ['role_name'];

    public function getRoleNameAttribute(): ?string
    {
        return $this->roles->pluck('name')->first();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Data karyawan (PIC) yang tertaut ke akun ini, kalau ada.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Direct permission overrides (allow / deny / inherit) per user.
     * Kolom `type` pada pivot menentukan perilaku override:
     *  - 'allow'   : izin eksplisit diberikan, mengalahkan ketiadaan di role
     *  - 'deny'    : izin eksplisit ditolak, mengalahkan pemberian via role
     *  - 'inherit' : ikuti role (berlaku seperti tidak ada record override)
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withPivot('type');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Resolusi permission dengan urutan prioritas:
     * 1. Superadmin — bypass penuh, selalu true
     * 2. Cek override per-user:
     *    a. 'deny'    → false (tolak eksplisit mengalahkan role)
     *    b. 'allow'   → true (izin eksplisit mengalahkan ketiadaan di role)
     *    c. 'inherit' → lanjut ke langkah 3
     * 3. Cek via role → true jika salah satu role punya permission
     * 4. Default → false (tolak jika tidak ada izin yang berlaku)
     */
    public function hasPermissionTo(string $permission): bool
    {
        // Superadmin bypass semua pemeriksaan
        if ($this->hasRole('Superadmin')) {
            return true;
        }

        // Cek override per-user dari tabel permission_user
        $override = $this->permissions()
            ->where('permissions.name', $permission)
            ->first();

        if ($override !== null) {
            $type = $override->pivot->type ?? 'allow';

            // Tolak eksplisit — mengalahkan permission dari role
            if ($type === 'deny') {
                return false;
            }

            // Izin eksplisit — langsung true
            if ($type === 'allow') {
                return true;
            }

            // 'inherit' — jatuh ke pemeriksaan role di bawah
        }

        // Cek apakah salah satu role user memiliki permission ini
        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('permissions.name', $permission);
        })->exists();
    }

    /**
     * Menghitung permission efektif untuk user ini pada satu permission.
     * Mengembalikan:
     *  - 'superadmin' : user adalah Superadmin
     *  - 'deny'       : ditolak eksplisit via override
     *  - 'allow'      : diizinkan eksplisit via override
     *  - 'role'       : diizinkan via role
     *  - 'none'       : tidak ada izin yang berlaku
     */
    public function effectivePermissionStatus(string $permission): string
    {
        if ($this->hasRole('Superadmin')) {
            return 'superadmin';
        }

        $override = $this->permissions()
            ->where('permissions.name', $permission)
            ->first();

        if ($override !== null) {
            $type = $override->pivot->type ?? 'allow';

            if ($type === 'deny') {
                return 'deny';
            }

            if ($type === 'allow') {
                return 'allow';
            }
        }

        $viaRole = $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('permissions.name', $permission);
        })->exists();

        return $viaRole ? 'role' : 'none';
    }

    public const PANEL_PERMISSIONS = [
        'admin' => 'panel.admin',
        'inventory' => 'panel.inventory',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // KODE LAMA (dijadikan komentar):
        // $permission = self::PANEL_PERMISSIONS[$panel->getId()] ?? null;
        // if ($permission === null) {
        //     return false;
        // }
        // return $this->hasPermissionTo($permission);

        // KODE BARU:
        // Agar Superadmin tidak melihat pemisahan panel (switcher), kita blokir aksesnya ke panel 'inventory'.
        // Karena menu inventaris sudah disatukan ke panel 'admin', Superadmin cukup pakai panel admin saja.
        if ($this->hasRole('Superadmin') && $panel->getId() === 'inventory') {
            return false;
        }

        $permission = self::PANEL_PERMISSIONS[$panel->getId()] ?? null;

        if ($permission === null) {
            return false;
        }

        return $this->hasPermissionTo($permission);
    }
}
