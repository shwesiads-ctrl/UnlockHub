<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password',
        'user_group_id', 'referred_by', 'preferred_locale', 'preferred_currency', 'notify_email',
        'shop_name', 'city',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'balance' => 'decimal:2',
            'points' => 'integer',
            'notify_email' => 'boolean',
            'password_set' => 'boolean',
            'profile_bonus_awarded' => 'boolean',
            'last_checkin_on' => 'date',
            'checkin_streak' => 'integer',
            'api_enabled' => 'boolean',
            'api_last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->referral_code ??= strtoupper(Str::random(8));
            $user->user_group_id ??= UserGroup::defaultId();
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Staff role for admins (old admins without one count as Owner). */
    public function staffRole(): ?string
    {
        return $this->isAdmin() ? ($this->staff_role ?: 'owner') : null;
    }

    public function canDo(string $permission): bool
    {
        return \App\Support\Permissions::allows($this, $permission);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->name ?: $this->username, 0, 2));
    }

    /** Fields that count for the profile bonus. */
    public function profileChecklist(): array
    {
        return [
            'email' => filled($this->email),
            'phone' => filled($this->phone),
            'avatar' => filled($this->avatar_path),
            'shop_name' => filled($this->shop_name),
            'city' => filled($this->city),
        ];
    }

    public function profilePercent(): int
    {
        $list = $this->profileChecklist();

        return (int) round(count(array_filter($list)) / count($list) * 100);
    }

    /** @return string[] */
    public function apiIpList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $this->api_ips))));
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }
}
