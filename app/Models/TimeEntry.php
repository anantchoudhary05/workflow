<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToProject;

    protected $fillable = ['task_id', 'work_date', 'hours', 'description', 'billable'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'hours' => 'decimal:2', 'billable' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
