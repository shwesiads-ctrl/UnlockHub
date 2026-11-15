<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ResellerProfile extends Model
{
    protected $fillable = [
        'user_id', 'status', 'shop_name', 'subdomain', 'custom_domain', 'logo_path', 'brand_color',
        'contact_phone', 'contact_telegram', 'contact_email', 'about', 'markup_type', 'markup_value',
        'allow_signup', 'application_note', 'admin_note', 'approved_at',
        'is_verified', 'verified_at', 'verification_source',
    ];

    protected function casts(): array
    {
        return ['markup_value' => 'decimal:2', 'allow_signup' => 'boolean', 'approved_at' => 'datetime', 'is_verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** https://shop.example.com (custom domain wins over subdomain) */
    public function storeUrl(): ?string
    {
        $base = parse_url((string) config('app.url'));
        $scheme = $base['scheme'] ?? 'https';
        if ($this->custom_domain) {
            return $scheme.'://'.$this->custom_domain;
        }
        if ($this->subdomain && ($root = setting('reseller.base_domain') ?: ($base['host'] ?? null))) {
            return $scheme.'://'.$this->subdomain.'.'.$root.(isset($base['port']) ? ':'.$base['port'] : '');
        }

        return null;
    }
}
