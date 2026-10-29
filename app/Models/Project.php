<?php

namespace App\Models;

use App\Enums\HandoverStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    // status, handover_status, approved_* and closure fields are set only by workflow services.
    protected $fillable = ['name', 'client_id', 'type', 'description', 'manager_id', 'start_date', 'due_date', 'launch_date', 'priority', 'estimated_hours', 'internal_notes', 'client_summary', 'repository_url', 'staging_url', 'production_url'];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'handover_status' => HandoverStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'original_due_date' => 'date',
            'due_date' => 'date',
            'launch_date' => 'date',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $p) {
            $p->code ??= sprintf('PRJ-%04d', (static::withTrashed()->max('id') ?? 0) + 1);
            $p->status ??= ProjectStatus::Lead;
            $p->original_due_date ??= $p->due_date;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function approvedScope(): BelongsTo
    {
        return $this->belongsTo(ScopeVersion::class, 'approved_scope_version_id');
    }

    public function approvedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'approved_quotation_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function scopeVersions(): HasMany
    {
        return $this->hasMany(ScopeVersion::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('due_date');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function previews(): HasMany
    {
        return $this->hasMany(Preview::class);
    }

    public function clientActions(): HasMany
    {
        return $this->hasMany(ClientAction::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->latest('id');
    }

    public function dateChanges(): HasMany
    {
        return $this->hasMany(ProjectDateChange::class)->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('decided_at');
    }

    /** Final client acceptance recorded as a positive approval on the project itself. */
    public function hasAcceptance(): bool
    {
        return $this->approvals()->whereIn('outcome', ['approved', 'approved_with_conditions'])->exists();
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasAbility('view-all-projects')) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('manager_id', $user->id)
            ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id)));
    }

    /** Active delivery work (Ready To Start → Ready For Launch, including Blocked). Used by the dashboard, filters and client counts. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ProjectStatus::active());
    }

    /** Pipeline work that has not started delivery yet (Lead, Planning, Awaiting Approval). */
    public function scopePipeline(Builder $q): Builder
    {
        return $q->whereIn('status', ProjectStatus::pipeline());
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term ? $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")) : $q;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [ProjectStatus::Closed, ProjectStatus::Cancelled], true);
    }

    public function isManagedBy(User $user): bool
    {
        return $this->manager_id === $user->id;
    }
}
