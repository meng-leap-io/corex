<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'permission' => ['required', Rule::in(['view', 'edit', 'download'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please specify the user to share with.',
            'user_id.uuid' => 'User ID must be a valid UUID.',
            'user_id.exists' => 'The specified user does not exist.',
            'permission.required' => 'A permission level is required.',
            'permission.in' => 'Permission must be view, edit, or download.',
            'expires_at.date' => 'Expiration must be a valid date.',
            'expires_at.after' => 'Expiration must be in the future.',
        ];
    }
}
