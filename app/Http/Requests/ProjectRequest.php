<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'A new project can start as Lead, Planning, Awaiting Approval, Ready To Start or In Progress.',
            'status.prohibited' => 'Change the status from the project overview so the change is recorded with a reason.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            // After creation the delivery date changes only via the recorded "change due date" action.
            'due_date' => $creating ? ['nullable', 'date', 'after_or_equal:start_date'] : ['prohibited'],
            'launch_date' => ['nullable', 'date'],
            // Only creation may set a status; afterwards it changes through the recorded status workflow.
            'status' => $creating
                ? ['nullable', Rule::in(array_map(fn (ProjectStatus $s): string => $s->value, ProjectStatus::initial()))]
                : ['prohibited'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'client_summary' => ['nullable', 'string', 'max:5000'],
            'repository_url' => ['nullable', 'url', 'max:255'],
            'staging_url' => ['nullable', 'url', 'max:255'],
            'production_url' => ['nullable', 'url', 'max:255'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ];
    }
}
