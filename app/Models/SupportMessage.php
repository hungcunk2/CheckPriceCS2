<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    public const UPDATED_AT = null;

    public const SENDER_MEMBER = 'member';

    public const SENDER_ADMIN = 'admin';

    protected $fillable = [
        'support_conversation_id',
        'sender',
        'admin_username',
        'body',
        'attachment_path',
        'attachment_mime',
    ];

    public function hasAttachment(): bool
    {
        return trim((string) ($this->attachment_path ?? '')) !== '';
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }
}
