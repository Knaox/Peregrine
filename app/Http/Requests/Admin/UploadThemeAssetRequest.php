<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single image asset uploaded from the Theme Studio (login
 * background, future hero images, etc). Stored under
 * `storage/app/public/branding/{slot}/` to inherit the existing
 * `/storage` symlink — no nginx config needed.
 */
class UploadThemeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', 'string', 'in:login_background'],
            'file' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
