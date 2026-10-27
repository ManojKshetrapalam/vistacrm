<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'user_id',
        'scheduled_at',
        'status',
        'notes',
        'outcome',
        'completed_at',
        'reminder_sent',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent' => 'boolean',
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<', Carbon::now());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereDate('scheduled_at', Carbon::today());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '>', Carbon::today()->endOfDay());
    }
}
