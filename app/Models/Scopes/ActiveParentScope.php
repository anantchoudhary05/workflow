<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides records whose parent (project or client) is soft-deleted, so a deleted project's tasks,
 * milestones, defects and so on drop out of every list, dashboard, KPI, search and reminder,
 * and come back automatically when the parent is restored. Records without a parent stay visible.
 */
class ActiveParentScope implements Scope
{
    public function __construct(private string $column, private string $parentTable) {}

    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn($this->column);
        $table = $this->parentTable;

        $builder->where(fn (Builder $q) => $q->whereNull($column)
            ->orWhereIn($column, fn ($sub) => $sub->select('id')->from($table)->whereNull('deleted_at')));
    }
}
