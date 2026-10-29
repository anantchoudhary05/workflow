<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use BelongsToProject;

    public const STATUSES = ['pending' => 'Pending', 'done' => 'Done', 'not_applicable' => 'Not applicable'];

    protected $fillable = ['type', 'label', 'is_mandatory', 'responsible_id', 'sort_order'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isSatisfied(): bool
    {
        return $this->status === 'done' || ($this->status === 'not_applicable' && ! $this->is_mandatory);
    }
}
