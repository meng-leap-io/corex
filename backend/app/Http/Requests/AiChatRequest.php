<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'conversation_id' => ['nullable', 'uuid', 'exists:conversations,id'],
            'model' => ['nullable', 'string'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'A message is required.',
            'conversation_id.uuid' => 'Conversation ID must be a valid UUID.',
            'conversation_id.exists' => 'The specified conversation does not exist.',
            'temperature.numeric' => 'Temperature must be a numeric value.',
            'temperature.between' => 'Temperature must be between 0 and 2.',
        ];
    }
}
