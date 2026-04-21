<?php

namespace App\Http\Requests\User;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A user can only change their password if local auth is enabled AND
        // they actually have one (OAuth-only accounts have password = NULL).
        return app(SettingsService::class)->get('auth_local_enabled', 'true') === 'true'
            && ! empty($this->user()?->password);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
