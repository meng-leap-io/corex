<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'events' => ['required', 'array'],
            'events.*' => ['string'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'A webhook URL is required.',
            'url.url' => 'Please provide a valid URL.',
            'events.required' => 'At least one event must be specified.',
            'events.array' => 'Events must be provided as an array.',
            'events.*.string' => 'Each event must be a string.',
        ];
    }
}
