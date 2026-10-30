<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsDeletion, SoftDeletes;

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

    /**
     * A deleted user still appears on the records they worked on (tasks, comments, history) as "Deleted user (Name)".
     * Deleted users cannot sign in: the soft-delete scope hides them from authentication and session lookups.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->isDeletedAccount() ? 'Deleted user ('.$value.')' : $value);
    }

    /**
     * Works even when the user was loaded with a narrow select such as "assignee:id,name" (no deleted_at column):
     * the ids of deleted users are looked up once per request.
     */
    public function isDeletedAccount(): bool
    {
        if (array_key_exists('deleted_at', $this->attributes)) {
            return $this->attributes['deleted_at'] !== null;
        }
        if (! $this->getKey()) {
            return false;
        }
        if (! app()->bound(self::TRASHED_IDS)) {
            app()->instance(self::TRASHED_IDS, static::onlyTrashed()->pluck('id')->map(fn ($id) => (int) $id)->flip()->all());
        }

        return isset(app(self::TRASHED_IDS)[(int) $this->getKey()]);
    }

    /** Call after deleting or restoring a user so the request-level cache above is rebuilt. */
    public static function forgetDeletedIds(): void
    {
        app()->forgetInstance(self::TRASHED_IDS);
    }

    private const TRASHED_IDS = 'users.trashed-ids';

    /** The stored name without the "Deleted user" label (trash page, restore messages). */
    public function realName(): string
    {
        return (string) $this->getRawOriginal('name', $this->attributes['name'] ?? '');
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

    /** Personal to-dos: private to this user. */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
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
