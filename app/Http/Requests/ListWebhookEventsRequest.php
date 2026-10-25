<?php

namespace App\Http\Requests;

use App\WebhookEventStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWebhookEventsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(WebhookEventStatus::class)],
            'connector' => [
                'nullable',
                'string',
                'max:64',
                Rule::exists('connectors', 'slug'),
            ],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['status', 'connector', 'q'] as $key) {
            $value = $this->input($key);

            $normalized[$key] = is_string($value) ? (trim($value) ?: null) : $value;
        }

        $this->merge($normalized);
    }
}
