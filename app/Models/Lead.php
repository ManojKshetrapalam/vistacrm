<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_id',
        'name',
        'phone',
        'phone_normalized',
        'email',
        'company_name',
        'source',
        'service_interest',
        'additional_details',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'last_contact_date',
        'next_follow_up_date',
        'custom_fields',
        'client_uuid',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'last_contact_date' => 'datetime',
            'next_follow_up_date' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if ($lead->phone && !$lead->phone_normalized) {
                $lead->phone_normalized = static::normalizePhone($lead->phone);
            }
            if (!$lead->reference_id) {
                $lead->reference_id = static::generateReferenceId();
            }
        });

        static::updating(function (Lead $lead) {
            if ($lead->isDirty('phone')) {
                $lead->phone_normalized = static::normalizePhone($lead->phone);
            }
        });
    }

    public static function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        // Remove everything except numbers
        $digits = preg_replace('/\D+/', '', $phone);

        // If starts with 91 and has 12 digits, strip country code to match standard 10 digit Indian numbers
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public static function generateReferenceId(): string
    {
        $year = date('Y');
        $prefix = "VB-LD-{$year}-";

        $lastLead = static::withTrashed()
            ->where('reference_id', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastLead && preg_match('/(\d+)$/', $lastLead->reference_id, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class)->orderBy('scheduled_at', 'asc');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->orderBy('created_at', 'desc');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);
        $normalizedTerm = static::normalizePhone($term);

        return $query->where(function ($q) use ($term, $normalizedTerm) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('reference_id', 'like', "%{$term}%")
              ->orWhere('company_name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");

            if (!empty($normalizedTerm)) {
                $q->orWhere('phone_normalized', 'like', "%{$normalizedTerm}%");
            }
        });
    }
}
