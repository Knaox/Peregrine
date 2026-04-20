<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CreateDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createDatabase', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'database' => ['required', 'string', 'max:48', 'regex:/^[a-zA-Z0-9_]+$/'],
            'remote' => ['required', 'string', 'max:255'],
        ];
    }
}
