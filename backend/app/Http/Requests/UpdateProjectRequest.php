<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::in(Project::VISIBILITIES)],
            'status' => ['nullable', Rule::in([Project::STATUS_DRAFT, Project::STATUS_ACTIVE, Project::STATUS_ARCHIVED])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Project name must not exceed 255 characters.',
            'visibility.in' => 'Visibility must be private, team, or public.',
            'status.in' => 'Status must be draft, active, or archived.',
        ];
    }
}
