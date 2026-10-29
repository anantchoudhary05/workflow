<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['project_id', 'from_status', 'to_status', 'user_id', 'reason', 'is_override'];

    protected function casts(): array
    {
        return ['from_status' => ProjectStatus::class, 'to_status' => ProjectStatus::class, 'is_override' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
