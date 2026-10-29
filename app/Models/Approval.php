<?php

namespace App\Models;

use App\Enums\ApprovalOutcome;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class Approval extends Model
{
    use BelongsToProject;

    protected $fillable = ['project_id', 'outcome', 'version_label', 'decided_by_name', 'source_reference', 'notes', 'decided_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['outcome' => ApprovalOutcome::class, 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Approval records are immutable.'));
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
