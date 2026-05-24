<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Partner;
use Modules\Tenant\Models\Tenant;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'partner_id',
        'tenant_id',
        'uuid',
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'department',
        'bio',
        'address',
        'two_factor_enabled',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

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
            'two_factor_enabled' => 'boolean',
        ];
    }

    /**
     * Get the partner that owns the user.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the tenant that owns the user.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Check if the user belongs to a partner.
     */
    public function isPartner(): bool
    {
        return $this->partner_id !== null;
    }

    /**
     * Check if the user belongs to a tenant.
     */
    public function isTenant(): bool
    {
        return $this->tenant_id !== null;
    }

    /**
     * Check if two-factor authentication is enabled.
     */
    public function isTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled;
    }

    /**
     * Get the user's phone number (masked).
     */
    public function getMaskedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }
        
        return substr($this->phone, 0, 4) . '****' . substr($this->phone, -3);
    }
}
