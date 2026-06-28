<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'model_used' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'A project ID is required.',
            'project_id.uuid' => 'Project ID must be a valid UUID.',
            'project_id.exists' => 'The specified project does not exist.',
            'title.required' => 'A conversation title is required.',
            'title.max' => 'Title must not exceed 255 characters.',
        ];
    }
}
