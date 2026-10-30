<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Priority;
use Closure;

/**
 * Only holders of the given ability may raise work to Urgent: "set-urgency" (Managers, administrators) for projects,
 * "set-task-priority" (Team Leads, Managers, administrators) for tasks.
 */
trait GuardsUrgentPriority
{
    protected function urgentPriorityRule(?Priority $current, string $ability = 'set-urgency'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($current, $ability): void {
            if ($value === Priority::Urgent->value && $current !== Priority::Urgent && ! $this->user()->can($ability)) {
                $fail($ability === 'set-urgency' ? 'Only a Manager can mark work as Urgent.' : 'Only a Team Lead or Manager can mark a task as Urgent.');
            }
        };
    }
}
