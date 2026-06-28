<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'plan' => ['nullable', Rule::in(['free', 'pro', 'team', 'enterprise'])],
            'role' => ['nullable', Rule::in(['user', 'admin', 'moderator'])],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address.',
            'plan.in' => 'Plan must be one of: free, pro, team, enterprise.',
            'role.in' => 'Role must be one of: user, admin, moderator.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
