<?php

namespace App\Models;

use App\Enums\TestCaseStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestCase extends Model
{
    use BelongsToProject, SoftDeletes;

    protected $fillable = ['requirement_id', 'milestone_id', 'title', 'description', 'preconditions', 'steps', 'expected_result', 'notes'];

    protected function casts(): array
    {
        return ['status' => TestCaseStatus::class, 'executed_at' => 'datetime'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class)->withTrashed();
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class)->withTrashed();
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id')->withTrashed();
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }
}
