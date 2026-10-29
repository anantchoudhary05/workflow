<?php

namespace App\Models;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\Priority;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Defect extends Model
{
    use BelongsToProject, SoftDeletes;

    protected $fillable = ['test_case_id', 'file_id', 'title', 'description', 'severity', 'priority', 'steps', 'expected_behavior', 'actual_behavior', 'assignee_id'];

    protected function casts(): array
    {
        return [
            'status' => DefectStatus::class,
            'severity' => DefectSeverity::class,
            'priority' => Priority::class,
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class)->withTrashed();
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'file_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeUnresolved(Builder $q): Builder
    {
        return $q->whereIn('status', DefectStatus::unresolved());
    }
}
