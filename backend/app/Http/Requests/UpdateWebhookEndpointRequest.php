<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.url' => 'Please provide a valid URL.',
            'events.array' => 'Events must be provided as an array.',
            'events.*.string' => 'Each event must be a string.',
        ];
    }
}
