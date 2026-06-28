<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $bucket = $this->input('bucket', 'projects');

        return [
            'file' => [
                'required',
                'file',
                'max:'.config("supabase.storage.buckets.{$bucket}.max_size", 10240),
            ],
            'bucket' => [
                'required',
                'string',
                'in:'.implode(',', array_keys(config('supabase.storage.buckets', ['projects' => []]))),
            ],
            'directory' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\/_-]+$/',
            ],
            'optimize' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'No file was uploaded.',
            'file.max' => 'The file exceeds the maximum allowed size for this bucket.',
            'bucket.in' => 'The specified storage bucket is invalid.',
            'directory.regex' => 'The directory path contains invalid characters.',
        ];
    }
}
