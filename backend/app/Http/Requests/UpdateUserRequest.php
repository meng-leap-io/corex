<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'avatar' => ['sometimes', 'string', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already taken.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
