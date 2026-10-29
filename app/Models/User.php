<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TaskStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // role and is_active are deliberately NOT fillable: only admin actions set them explicitly.
    protected $fillable = ['name', 'email', 'password', 'job_title', 'skills', 'weekly_capacity_hours'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'weekly_capacity_hours' => 'decimal:2',
        ];
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = ($this->role ?? Role::Developer)->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function openTasks(): HasMany
    {
        return $this->assignedTasks()->whereIn('status', TaskStatus::open());
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Can this user see the given project (record-level)? Memoised per request. */
    public function canAccessProject(?int $projectId): bool
    {
        if ($projectId === null) {
            return $this->hasAbility('view-all-projects');
        }
        $ids = $this->accessibleProjectIds();

        return $ids === null || in_array($projectId, $ids, true);
    }

    /** null = all projects. */
    public function accessibleProjectIds(): ?array
    {
        if ($this->hasAbility('view-all-projects')) {
            return null;
        }

        return $this->projectIdsCache ??= Project::query()->visibleTo($this)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private ?array $projectIdsCache = null;
}
