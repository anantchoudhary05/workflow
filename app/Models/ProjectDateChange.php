<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDateChange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['project_id', 'old_date', 'new_date', 'reason', 'delay_source', 'user_id'];

    protected function casts(): array
    {
        return ['old_date' => 'date', 'new_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
