<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'reminder_at',
        'reminder_key',
        'reminder_status',
        'reminder_sent_at',
        'reminder_channel',
        'client_uuid',
        'version',
    ];

    protected $appends = [
        'scheduled_at_ist',
        'reminder_at_ist',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reminder_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'reminder_sent' => 'boolean',
        ];
    }

    public function setScheduledAtAttribute($value): void
    {
        $this->attributes['scheduled_at'] = $value ? static::parseToUtc($value)->format('Y-m-d H:i:s') : null;
    }

    public function setReminderAtAttribute($value): void
    {
        $this->attributes['reminder_at'] = $value ? static::parseToUtc($value)->format('Y-m-d H:i:s') : null;
    }

    public function setCompletedAtAttribute($value): void
    {
        $this->attributes['completed_at'] = $value ? static::parseToUtc($value)->format('Y-m-d H:i:s') : null;
    }

    protected static function booted(): void
    {
        static::creating(function (FollowUp $followUp) {
            if ($followUp->scheduled_at) {
                $dueUtc = $followUp->scheduled_at->copy()->utc();
                if (!$followUp->reminder_at) {
                    $followUp->reminder_at = static::calculateReminderAt($dueUtc, Carbon::now('UTC'));
                }
            }
            if (!$followUp->reminder_status) {
                $followUp->reminder_status = 'pending';
            }
        });

        static::created(function (FollowUp $followUp) {
            if (!$followUp->reminder_key) {
                $followUp->reminder_key = $followUp->generateReminderKey();
                $followUp->saveQuietly();
            }
        });

        static::updating(function (FollowUp $followUp) {
            if ($followUp->isDirty('scheduled_at')) {
                $dueUtc = $followUp->scheduled_at->copy()->utc();
                $followUp->reminder_at = static::calculateReminderAt($dueUtc, Carbon::now('UTC'));
                $followUp->reminder_key = $followUp->generateReminderKey();
                $followUp->reminder_status = in_array($followUp->status, ['completed', 'cancelled']) ? 'cancelled' : 'pending';
                $followUp->reminder_sent = false;
                $followUp->reminder_sent_at = null;
            }

            if ($followUp->isDirty('status')) {
                if (in_array($followUp->status, ['completed', 'cancelled'])) {
                    if ($followUp->reminder_status === 'pending') {
                        $followUp->reminder_status = 'cancelled';
                    }
                }
            }
        });
    }

    public static function parseToUtc(mixed $datetime): Carbon
    {
        if ($datetime instanceof Carbon) {
            return $datetime->copy()->utc();
        }

        // If string without timezone offset (e.g. "2026-10-27T10:57" or "2026-10-27 10:57:00"), treat as Asia/Kolkata
        $str = (string) $datetime;
        if (!preg_match('/[Z\+\-]\d{2}(:?\d{2})?$/', $str) && !str_ends_with($str, 'Z')) {
            return Carbon::parse($str, 'Asia/Kolkata')->utc();
        }

        return Carbon::parse($str)->utc();
    }

    /**
     * Calculates reminder time (5 minutes prior to due time) with explicit late-scheduling policy.
     *
     * Late-scheduling policy:
     * If the follow-up is created/rescheduled with < 5 minutes remaining before due time,
     * the reminder cannot be placed 5 minutes before; instead, it is scheduled immediately
     * (at $createdAtUtc or now) for instant eligibility.
     */
    public static function calculateReminderAt(Carbon $dueAtUtc, ?Carbon $createdAtUtc = null): Carbon
    {
        $now = Carbon::now('UTC');
        $referenceTime = $createdAtUtc ? $createdAtUtc->copy()->utc() : $now;
        $standardReminder = $dueAtUtc->copy()->subMinutes(5);

        if ($standardReminder->greaterThan($referenceTime)) {
            return $standardReminder;
        }

        // Late scheduling policy: immediately eligible
        return $referenceTime;
    }

    public function generateReminderKey(): string
    {
        $ts = $this->scheduled_at ? $this->scheduled_at->timestamp : time();
        $salt = substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
        return "rem_fu_{$this->id}_{$ts}_{$salt}";
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'follow_up_id');
    }

    public function getScheduledAtIstAttribute(): ?string
    {
        return $this->scheduled_at
            ? $this->scheduled_at->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A')
            : null;
    }

    public function getReminderAtIstAttribute(): ?string
    {
        return $this->reminder_at
            ? $this->reminder_at->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A')
            : null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<', Carbon::now('UTC'));
    }

    public function scopeToday(Builder $query): Builder
    {
        $startOfTodayUtc = Carbon::today('Asia/Kolkata')->utc();
        $endOfTodayUtc = Carbon::today('Asia/Kolkata')->endOfDay()->utc();

        return $query->where('status', 'pending')
            ->whereBetween('scheduled_at', [$startOfTodayUtc, $endOfTodayUtc]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        $endOfTodayUtc = Carbon::today('Asia/Kolkata')->endOfDay()->utc();

        return $query->where('status', 'pending')
            ->where('scheduled_at', '>', $endOfTodayUtc);
    }

    public function scopePendingReminders(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('reminder_status', 'pending')
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', Carbon::now('UTC'));
    }
}
