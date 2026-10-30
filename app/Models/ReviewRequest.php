<?php

namespace App\Models;

use App\Enums\ReviewRequestStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Team Lead asks the Manager whether work is ready for client preview or needs corrections. */
class ReviewRequest extends Model
{
    use BelongsToProject;

    protected $fillable = ['title', 'notes', 'url'];

    protected function casts(): array
    {
        return [
            'status' => ReviewRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === ReviewRequestStatus::Pending;
    }
}
