<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'role',
        'is_active',
        'notification_preferences',
        'allowed_sources',
        'allowed_services',
        'team_members',
        'assignable_users',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'notification_preferences' => 'array',
            'allowed_sources' => 'array',
            'allowed_services' => 'array',
            'team_members' => 'array',
            'assignable_users' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeamLead(): bool
    {
        return $this->role === 'team_lead';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isTeamLead()) {
            return in_array($permissionName, [
                'leads.view',
                'leads.create',
                'leads.edit',
                'leads.assign',
                'leads.export',
                'follow_ups.manage',
            ], true);
        }

        // Standard Employee
        return in_array($permissionName, [
            'leads.view',
            'leads.create',
            'leads.edit',
            'follow_ups.manage',
        ], true);
    }

    public function getEffectiveVisibilityScope(): string
    {
        if ($this->isAdmin()) {
            return 'all';
        }

        if ($this->isTeamLead()) {
            return 'team_only';
        }

        return 'assigned_only';
    }

    /**
     * Get team member user IDs whose leads this team lead can supervise (always includes self).
     *
     * @return list<int>
     */
    public function getTeamMemberIds(): array
    {
        if ($this->isTeamLead()) {
            $members = is_array($this->team_members) ? $this->team_members : [];
            $ids = array_merge([(int)$this->id], array_map('intval', $members));
            return array_values(array_unique($ids));
        }

        return [(int)$this->id];
    }

    /**
     * Alias for getTeamMemberIds()
     *
     * @return list<int>
     */
    public function teamMemberIds(): array
    {
        return $this->getTeamMemberIds();
    }

    /**
     * Get user IDs to whom this user can assign leads.
     *
     * @return list<int>
     */
    public function getAssignableUserIds(): array
    {
        if ($this->isAdmin()) {
            return static::where('is_active', true)->pluck('id')->map(fn($id) => (int)$id)->all();
        }

        if ($this->isTeamLead()) {
            if (!empty($this->assignable_users) && is_array($this->assignable_users)) {
                $ids = array_merge([(int)$this->id], array_map('intval', $this->assignable_users));
                return array_values(array_unique($ids));
            }
            return $this->getTeamMemberIds();
        }

        return [(int)$this->id];
    }

    /**
     * Check whether this user can assign a lead to target user.
     */
    public function canAssignTo(int $targetUserId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isTeamLead()) {
            return in_array($targetUserId, $this->getAssignableUserIds(), true);
        }

        return $targetUserId === (int)$this->id;
    }

    /**
     * Check if user is authorized to access a specific lead based on role, assignment, team delegation, and configured category/source permissions.
     */
    public function canAccessLead(Lead $lead): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isTeamLead()) {
            $teamIds = $this->getTeamMemberIds();
            if (!$lead->assigned_to || !in_array((int)$lead->assigned_to, $teamIds, true)) {
                return false;
            }

            if (!empty($this->allowed_sources) && is_array($this->allowed_sources)) {
                if (!in_array($lead->source, $this->allowed_sources, true)) {
                    return false;
                }
            }

            if (!empty($this->allowed_services) && is_array($this->allowed_services)) {
                if (!in_array($lead->service_interest, $this->allowed_services, true)) {
                    return false;
                }
            }

            return true;
        }

        // Employee: strict isolation to self
        if ((int)$lead->assigned_to !== (int)$this->id) {
            return false;
        }

        // Allowed Sources (Mandatory constraint for employees if configured)
        if (!empty($this->allowed_sources) && is_array($this->allowed_sources)) {
            if (!in_array($lead->source, $this->allowed_sources, true)) {
                return false;
            }
        }

        // Allowed Services (Optional constraint: only restricts if explicitly configured)
        if (!empty($this->allowed_services) && is_array($this->allowed_services)) {
            if (!in_array($lead->service_interest, $this->allowed_services, true)) {
                return false;
            }
        }

        return true;
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'user_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class, 'user_id');
    }
}
