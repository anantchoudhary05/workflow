<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\RequirementCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(RequirementCategory::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'source' => ['nullable', 'string', 'max:60'],
            'module' => ['nullable', 'string', 'max:120'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
