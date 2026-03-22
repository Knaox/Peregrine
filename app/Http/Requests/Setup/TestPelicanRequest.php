<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class TestPelicanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !config('panel.installed');
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'api_key' => ['required', 'string'],
        ];
    }
}
