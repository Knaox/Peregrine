<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class TestDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !config('panel.installed');
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'string'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ];
    }
}
