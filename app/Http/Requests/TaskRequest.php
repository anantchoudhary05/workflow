<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    /**
     * Statuses a task may start in. Later statuses (review, done, …) are reached through the workflow.
     *
     * @return list<string>
     */
    public static function initialStatuses(): array
    {
        return [TaskStatus::Backlog->value, TaskStatus::Ready->value, TaskStatus::InProgress->value];
    }

    public function authorize(): bool
    {
        return $this->user()->can('manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->route('task')?->project_id ?? $this->input('project_id');

        return [
            'project_id' => [$this->route('task') ? 'prohibited' : 'required', Rule::exists('projects', 'id')->whereNot('status', 'closed')],
            'status' => $this->route('task') ? ['prohibited'] : ['nullable', Rule::in(self::initialStatuses())],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->where('project_id', $projectId)],
            'change_request_id' => ['nullable', Rule::exists('change_requests', 'id')->where('project_id', $projectId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'reviewer_id' => ['nullable', 'exists:users,id', 'different:assignee_id'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'tags' => ['nullable', 'string', 'max:255'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.exists' => 'Choose an open project. Closed projects cannot receive new tasks.',
            'status.in' => 'A new task can start in Backlog, Ready or In Progress.',
            'status.prohibited' => 'Change the status from the task page so the workflow rules are applied.',
        ];
    }
}
