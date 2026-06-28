<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'value' => ['required'],
            'type' => ['nullable', Rule::in(['string', 'boolean', 'integer', 'json'])],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'A setting key is required.',
            'value.required' => 'A setting value is required.',
            'type.in' => 'Type must be one of: string, boolean, integer, json.',
        ];
    }
}
