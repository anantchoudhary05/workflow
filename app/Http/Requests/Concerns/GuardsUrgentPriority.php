<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Priority;
use Closure;

/** Only users with the "set-urgency" ability (Managers, administrators) may raise work to Urgent. */
trait GuardsUrgentPriority
{
    protected function urgentPriorityRule(?Priority $current): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if ($value === Priority::Urgent->value && $current !== Priority::Urgent && ! $this->user()->can('set-urgency')) {
                $fail('Only a Manager can mark work as Urgent.');
            }
        };
    }
}
