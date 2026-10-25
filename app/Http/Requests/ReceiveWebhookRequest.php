<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveWebhookRequest extends FormRequest
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
            'source_event_id' => [
                'required',
                'string',
                'max:255',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/',
            ],
            'event_type' => [
                'required',
                'string',
                'max:255',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_event_id.required' => 'L’en-tête X-SyncOps-Event-ID est obligatoire.',
            'source_event_id.max' => 'L’en-tête X-SyncOps-Event-ID ne peut pas dépasser 255 caractères.',
            'source_event_id.regex' => 'L’en-tête X-SyncOps-Event-ID contient des caractères non autorisés.',
            'event_type.required' => 'L’en-tête X-SyncOps-Event-Type est obligatoire.',
            'event_type.max' => 'L’en-tête X-SyncOps-Event-Type ne peut pas dépasser 255 caractères.',
            'event_type.regex' => 'L’en-tête X-SyncOps-Event-Type contient des caractères non autorisés.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_event_id' => $this->header('X-SyncOps-Event-ID'),
            'event_type' => $this->header('X-SyncOps-Event-Type'),
        ]);
    }
}
