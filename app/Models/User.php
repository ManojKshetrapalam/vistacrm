<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
        'role_id',
        'team_id',
        'is_active',
        'notification_preferences',
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
        ];
    }

    public function roleRelation(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || ($this->roleRelation && $this->roleRelation->name === 'admin');
    }

    public function isEmployee(): bool
    {
        return !$this->isAdmin();
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

    /**
     * Check if user has a specific permission.
     * Follows the strict hierarchy: User Explicit Override (Allow/Deny) -> Role Template -> Default Fallback.
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // 1. User Explicit Overrides (Highest Precedence)
        $override = DB::table('user_has_permissions')
            ->join('permissions', 'user_has_permissions.permission_id', '=', 'permissions.id')
            ->where('user_has_permissions.user_id', $this->id)
            ->where('permissions.name', $permissionName)
            ->value('user_has_permissions.effect');

        if ($override === 'deny') {
            return false;
        }
        if ($override === 'allow') {
            return true;
        }

        // 2. Inherited Role Template
        if ($this->role_id) {
            $hasRolePerm = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->where('role_has_permissions.role_id', $this->role_id)
                ->where('permissions.name', $permissionName)
                ->exists();

            return $hasRolePerm;
        }

        // 3. Fallback baseline for standard employees
        $defaultEmployeePermissions = [
            'leads.view',
            'leads.create',
            'leads.edit',
            'follow_ups.manage',
            'whatsapp.send',
        ];

        return in_array($permissionName, $defaultEmployeePermissions);
    }

    /**
     * Retrieve all effective permission names for this user.
     */
    public function getEffectivePermissions(): array
    {
        if ($this->isAdmin()) {
            return Permission::pluck('name')->toArray();
        }

        $allPermissions = Permission::all();
        $granted = [];

        foreach ($allPermissions as $perm) {
            if ($this->hasPermission($perm->name)) {
                $granted[] = $perm->name;
            }
        }

        return $granted;
    }

    /**
     * Resolve effective visibility scope: 'assigned_only', 'team_only', or 'all'.
     */
    public function getEffectiveVisibilityScope(): string
    {
        if ($this->isAdmin()) {
            return 'all';
        }

        // 1. User explicit visibility override
        $userScope = DB::table('user_visibility_overrides')
            ->where('user_id', $this->id)
            ->value('scope');

        if ($userScope && $userScope !== 'inherit') {
            return $userScope;
        }

        // 2. Role template visibility scope
        if ($this->roleRelation) {
            return $this->roleRelation->getVisibilityScope();
        }

        // 3. Default invariant: assigned_only
        return 'assigned_only';
    }

    /**
     * Resolve effective allowed lead/service categories.
     * Returns null for unrestricted (all categories allowed), or string array of allowed category names.
     */
    public function getEffectiveAllowedCategories(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        // User explicit allow overrides
        $userAllowed = DB::table('user_category_assignments')
            ->where('user_id', $this->id)
            ->where('is_allowed', true)
            ->pluck('category_name')
            ->toArray();

        // User explicit deny overrides
        $userDenied = DB::table('user_category_assignments')
            ->where('user_id', $this->id)
            ->where('is_allowed', false)
            ->pluck('category_name')
            ->toArray();

        // Role allowed categories
        $roleCategories = $this->roleRelation ? $this->roleRelation->getAllowedCategories() : [];

        // If neither user nor role has restrictions, unrestricted
        if (empty($roleCategories) && empty($userAllowed) && empty($userDenied)) {
            return null;
        }

        $effective = !empty($userAllowed) ? $userAllowed : $roleCategories;

        if (!empty($userDenied)) {
            $effective = array_values(array_diff($effective, $userDenied));
        }

        return $effective;
    }

    /**
     * Team member IDs helper.
     */
    public function teamMemberIds(): array
    {
        if (!$this->team_id) {
            return [$this->id];
        }

        return static::where('team_id', $this->team_id)->pluck('id')->toArray();
    }

    /**
     * Centralized lead access determination.
     */
    public function canAccessLead(Lead $lead): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->hasPermission('leads.view')) {
            return false;
        }

        // Check category restriction
        $allowedCats = $this->getEffectiveAllowedCategories();
        if ($allowedCats !== null && !empty($lead->service_interest) && !in_array($lead->service_interest, $allowedCats)) {
            return false;
        }

        // Check visibility scope
        $scope = $this->getEffectiveVisibilityScope();
        if ($scope === 'all') {
            return true;
        }

        if ($scope === 'team_only') {
            if ($this->team_id && $lead->assigned_to) {
                return in_array($lead->assigned_to, $this->teamMemberIds());
            }
            return $lead->assigned_to === $this->id;
        }

        // Default: assigned_only
        return $lead->assigned_to === $this->id;
    }
}
