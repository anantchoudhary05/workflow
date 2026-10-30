<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who soft-deleted the record and why (deleted_by, deletion_reason). Used with SoftDeletes. */
trait RecordsDeletion
{
    public function initializeRecordsDeletion(): void
    {
        $this->mergeCasts(['deleted_at' => 'datetime']);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }
}
