<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateMemberRole', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['member', 'editor', 'admin', 'owner'])],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'A role is required.',
            'role.in' => 'Role must be one of: member, editor, admin, owner.',
        ];
    }
}
