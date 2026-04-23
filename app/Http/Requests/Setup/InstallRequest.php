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

            // Auth — only the registration toggle is asked at install time.
            // Providers (Shop / Google / Discord / LinkedIn) and 2FA are
            // configured post-install at /admin/auth-settings. Optional
            // here: AuthSettingsSeeder already provides a `true` default.
            'auth.allow_local_registration' => ['sometimes', 'boolean'],

            // Bridge configuration is post-install only (admin enables and
            // sets the HMAC secret + Stripe webhook secret in
            // /admin/bridge-settings). Not asked here.

            // Locale
            'locale' => ['required', 'string', 'in:en,fr'],
        ];
    }
}
