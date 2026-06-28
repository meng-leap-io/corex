<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

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
            'expires_in' => [
                'nullable',
                'integer',
                'min:60',
                'max:604800',
            ],
            'permission' => [
                'nullable',
                Rule::in(['view', 'download']),
            ],
            'password' => [
                'nullable',
                'string',
                'min:4',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'expires_in.min' => 'Share link must be valid for at least 1 minute.',
            'expires_in.max' => 'Share link cannot be valid for more than 7 days.',
            'permission.in' => 'Permission must be view or download.',
            'password.min' => 'Password must be at least 4 characters.',
        ];
    }
}
