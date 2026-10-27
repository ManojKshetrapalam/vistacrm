<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'follow_up_id',
        'user_id',
        'reminder_key',
        'channel',
        'recipient',
        'title',
        'message',
        'status',
        'error_message',
        'metadata',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_read' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class, 'follow_up_id');
    }
}
