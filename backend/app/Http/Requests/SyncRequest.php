<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'table' => ['required', 'string'],
            'action' => ['required', Rule::in(['push', 'pull', 'sync'])],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'table.required' => 'A table name is required.',
            'action.required' => 'A sync action is required.',
            'action.in' => 'Action must be push, pull, or sync.',
            'ids.array' => 'IDs must be provided as an array.',
            'ids.*.uuid' => 'Each ID must be a valid UUID.',
        ];
    }
}
