<?php

namespace Modules\Tenant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantApplication extends Model
{
    protected $table = 'tenant_applications';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    // Bayi portalı akışı: ön başvuru (pending) → görüşme sonrası resmi belge aşaması açılır →
    // belgeler yüklenince resmi evrak incelemesi → onayda hesap açılır (approved + davet).
    public const STATUS_DOCUMENTS_REQUESTED = 'documents_requested';
    public const STATUS_DOCUMENTS_REVIEW = 'documents_review';
    // Resmi evrak incelemesinde admin "Eksik evrak iste" dedi; belgeler yüklenince documents_review'a döner.
    public const STATUS_MISSING_DOCUMENTS = 'missing_documents';

    /** Üyelik tamamlama bağlantısının geçerlilik süresi (gün). */
    public const INVITATION_TTL_DAYS = 14;

    /** Resmi belge hatırlatmaları arasında en az bu kadar dakika olmalı (art arda tıklama koruması). */
    public const REMINDER_COOLDOWN_MINUTES = 60;

    public const SOURCE_WEB = 'web';
    public const SOURCE_BAGISTO = 'bagisto';

    protected $fillable = [
        'name',
        'legal_name',
        'tax_number',
        'tax_office',
        'contact_person',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'district',
        'country',
        'postal_code',
        'message',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'converted_tenant_id',
        'ip_address',
        'public_token',
        'source',
        'details',
        'missing_documents',
        'missing_note',
        'missing_requested_at',
        'documents_requested_at',
        'documents_reminded_at',
        'documents_submitted_at',
        'invitation_token',
        'invitation_sent_at',
        'invitation_expires_at',
        'completed_at',
    ];

    protected $hidden = [
        'public_token',
        'invitation_token',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'details' => 'array',
        'missing_documents' => 'array',
        'missing_requested_at' => 'datetime',
        'documents_requested_at' => 'datetime',
        'documents_reminded_at' => 'datetime',
        'documents_submitted_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isFromPortal(): bool
    {
        return $this->source === self::SOURCE_BAGISTO;
    }

    /** Davet bağlantısı hâlâ kullanılabilir mi (onaylı, tamamlanmamış, süresi dolmamış). */
    public function invitationIsOpen(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->invitation_token !== null
            && $this->completed_at === null
            && (bool) $this->invitation_expires_at?->isFuture();
    }

    /** Kullanıcıya gösterilen referans kodu (Stitch: #OZ-BAYI-2026-8419). */
    public function getReferenceAttribute(): string
    {
        return 'OZ-BAYI-'.$this->created_at?->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenantApplicationDocument::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }
}
