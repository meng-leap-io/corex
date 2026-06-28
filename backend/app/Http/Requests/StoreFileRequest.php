<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'],
            'folder' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::in(Project::VISIBILITIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'No file was uploaded.',
            'file.file' => 'The uploaded item must be a file.',
            'file.max' => 'File size must not exceed 100 MB.',
            'visibility.in' => 'Visibility must be private, team, or public.',
        ];
    }
}
