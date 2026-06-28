<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMember', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', Rule::in(['member', 'editor', 'admin'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please specify the user to add.',
            'user_id.uuid' => 'User ID must be a valid UUID.',
            'user_id.exists' => 'The specified user does not exist.',
            'role.required' => 'A role is required for the new member.',
            'role.in' => 'Role must be one of: member, editor, admin.',
        ];
    }
}
