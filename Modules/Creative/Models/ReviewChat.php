<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reddedilen bir Mannequin|TryonResult üzerinde, üretici hesap ve/veya yöneticilerin
 * AI ile yürüttüğü düzeltme sohbetinin tek bir mesajı.
 *
 * updated_at yok — mesajlar değiştirilmez, sadece eklenir.
 */
class ReviewChat extends Model
{
    public $timestamps = false;

    protected $table = 'creative_review_chats';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'role',
        'content',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
