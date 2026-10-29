<?php

namespace App\Http\Requests;

use App\Enums\ChangeClassification;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Logging a request is open to anyone who records client communication; assessment is admin-only.
        return $this->isMethod('post') ? $this->user()->can('record-communication') : $this->user()->can('manage');
    }

    public function rules(): array
    {
        $projectId = $this->route('changeRequest')?->project_id ?? $this->route('project')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:40'],
            'received_at' => ['required', 'date'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'reviewer_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'classification' => ['nullable', Rule::enum(ChangeClassification::class)],
            'classification_rationale' => ['nullable', 'required_with:classification', 'string', 'max:5000'],
            'scope_impact' => ['nullable', 'string', 'max:5000'],
            'extra_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'cost_impact' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'timeline_impact_days' => ['nullable', 'integer', 'min:-365', 'max:365'],
            'milestones' => ['array'],
            'milestones.*' => [Rule::exists('milestones', 'id')->where('project_id', $projectId)],
            'requirements' => ['array'],
            'requirements.*' => [Rule::exists('requirements', 'id')->where('project_id', $projectId)],
        ];
    }
}
