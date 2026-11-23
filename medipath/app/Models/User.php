<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'status',
        'verification_status', 'avatar_path', 'locale', 'timezone',
        'email_verified_at', 'suspended_at', 'suspension_reason',
        'archived_at', 'last_login_at', 'last_login_ip', 'password_changed_at', 'session_version',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'verification_status' => VerificationStatus::class,
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'session_version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function pharmacist(): HasOne
    {
        return $this->hasOne(Pharmacist::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /** App notifications (custom table, PLAN §4 shape). Overrides Notifiable's morph feed. */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function pendingApprovals(): HasMany
    {
        return $this->hasMany(PendingApproval::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    // ---------------------------------------------------------------- accessors
    public function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? '';
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '';
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'MP';
    }

    public function getDashboardRouteAttribute(): string
    {
        return match ($this->role) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Doctor => 'doctor.dashboard',
            UserRole::Pharmacist => 'pharmacist.dashboard',
            default => 'patient.dashboard',
        };
    }

    public function getThemeAttribute(): string
    {
        $pref = $this->preferences?->theme ?? 'system';

        return in_array($pref, ['light', 'dark'], true) ? $pref : 'system';
    }

    // ---------------------------------------------------------------- helpers
    public function isRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return $this->role?->value === $value;
    }

    public function isAdmin(): bool
    {
        return $this->isRole(UserRole::Admin);
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Approved
            && $this->archived_at === null
            && $this->suspended_at === null;
    }

    public function canAuthenticate(): bool
    {
        return $this->isActive();
    }

    /**
     * Pending applicants may authenticate ONLY to view their holding screen;
     * RoleGate keeps them out of every portal until approval.
     */
    public function canAttemptLogin(): bool
    {
        return $this->archived_at === null
            && $this->suspended_at === null
            && in_array($this->status, [AccountStatus::Approved, AccountStatus::Pending, AccountStatus::Rejected], true);
    }

    public function isVerifiedDoctor(): bool
    {
        return $this->isRole(UserRole::Doctor)
            && $this->verification_status === VerificationStatus::Verified
            && $this->doctor?->verification_status === VerificationStatus::Verified;
    }

    public function isApprovedPharmacist(): bool
    {
        return $this->isRole(UserRole::Pharmacist) && $this->isActive();
    }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn ($q) => $q->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }
}
