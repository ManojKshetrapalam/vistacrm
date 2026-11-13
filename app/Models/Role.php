<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function getVisibilityScope(): string
    {
        $scope = DB::table('role_visibility_scopes')->where('role_id', $this->id)->value('scope');
        return $scope ?: ($this->name === 'admin' ? 'all' : 'assigned_only');
    }

    public function setVisibilityScope(string $scope): void
    {
        DB::table('role_visibility_scopes')->updateOrInsert(
            ['role_id' => $this->id],
            ['scope' => $scope, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function getAllowedCategories(): array
    {
        return DB::table('role_category_assignments')
            ->where('role_id', $this->id)
            ->pluck('category_name')
            ->toArray();
    }

    public function syncAllowedCategories(array $categories): void
    {
        DB::table('role_category_assignments')->where('role_id', $this->id)->delete();
        $inserts = array_map(function ($cat) {
            return [
                'role_id' => $this->id,
                'category_name' => $cat,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $categories);

        if (!empty($inserts)) {
            DB::table('role_category_assignments')->insert($inserts);
        }
    }
}
