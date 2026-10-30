<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');
        $project = $this->route('project');

        return $quotation
            ? $this->user()->can('update', $quotation)
            : $this->user()->can('create', Quotation::class) && $project && $this->user()->canAccessProject($project->id);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'scope_summary' => ['nullable', 'string', 'max:10000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'assumptions' => ['nullable', 'string', 'max:5000'],
            'delivery_period' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scope_version_id' => ['nullable', Rule::exists('scope_versions', 'id')->where('project_id', $this->route('project')?->id ?? $this->route('quotation')?->project_id)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.category' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.cost_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.is_optional' => ['nullable', 'boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function quotationData(): array
    {
        return collect($this->validated())->except('items')->map(fn ($v, $k) => in_array($k, ['discount_amount', 'tax_rate'], true) ? ($v ?? 0) : $v)->all();
    }
}
