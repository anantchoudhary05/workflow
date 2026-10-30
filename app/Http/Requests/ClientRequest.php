<?php

namespace App\Http\Requests;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client ? $this->user()->can('update', $client) : $this->user()->can('create', Client::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'primary_contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'owner_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
