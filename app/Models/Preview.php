<?php

namespace App\Models;

use App\Enums\PreviewStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Preview extends Model
{
    use BelongsToProject;

    protected $fillable = ['milestone_id', 'title', 'url', 'version_label', 'shared_at', 'review_due_at', 'feedback_summary', 'internal_notes'];

    protected function casts(): array
    {
        return ['status' => PreviewStatus::class, 'shared_at' => 'date', 'review_due_at' => 'date'];
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class)->withTrashed();
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by')->withTrashed();
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('decided_at');
    }
}
