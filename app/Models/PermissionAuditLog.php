<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'target_type',
        'target_id',
        'action',
        'details',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(?int $actorId, string $targetType, int $targetId, string $action, array $details = []): self
    {
        return static::create([
            'actor_id' => $actorId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
