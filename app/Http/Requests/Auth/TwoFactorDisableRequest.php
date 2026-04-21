<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorDisableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Requires either the account password OR a valid current TOTP (for users
     * without a password — OAuth-only accounts).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'required_without:code'],
            'code' => ['nullable', 'string', 'digits:6', 'required_without:password'],
        ];
    }
}
