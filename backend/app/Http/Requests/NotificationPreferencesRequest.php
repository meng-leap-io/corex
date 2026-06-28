<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email_notifications' => ['boolean'],
            'push_notifications' => ['boolean'],
            'desktop_notifications' => ['boolean'],
            'digest_frequency' => [Rule::in(['none', 'daily', 'weekly'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email_notifications.boolean' => 'Email notifications must be true or false.',
            'push_notifications.boolean' => 'Push notifications must be true or false.',
            'desktop_notifications.boolean' => 'Desktop notifications must be true or false.',
            'digest_frequency.in' => 'Digest frequency must be none, daily, or weekly.',
        ];
    }
}
