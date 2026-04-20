<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class InstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !config('panel.installed');
    }

    public function rules(): array
    {
        return [
            // Database
            'database.host' => ['required', 'string'],
            'database.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database.name' => ['required', 'string'],
            'database.username' => ['required', 'string'],
            'database.password' => ['nullable', 'string'],
            'database.fresh' => ['sometimes', 'boolean'],

            // Admin
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin.password_confirmation' => ['required', 'string'],

            // Pelican
            'pelican.url' => ['required', 'url'],
            'pelican.api_key' => ['required', 'string'],
            'pelican.client_api_key' => ['required', 'string'],

            // Auth
            'auth.mode' => ['required', 'string', 'in:local,oauth'],
            'auth.oauth_client_id' => ['required_if:auth.mode,oauth', 'nullable', 'string'],
            'auth.oauth_client_secret' => ['required_if:auth.mode,oauth', 'nullable', 'string'],
            'auth.oauth_authorize_url' => ['required_if:auth.mode,oauth', 'nullable', 'url'],
            'auth.oauth_token_url' => ['required_if:auth.mode,oauth', 'nullable', 'url'],
            'auth.oauth_user_url' => ['required_if:auth.mode,oauth', 'nullable', 'url'],

            // Bridge
            'bridge.enabled' => ['required', 'boolean'],
            'bridge.stripe_webhook_secret' => ['required_if:bridge.enabled,true', 'nullable', 'string'],

            // Locale
            'locale' => ['required', 'string', 'in:en,fr'],
        ];
    }
}
