<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            'ignored' => ['nullable', 'string'],
            'is_locked' => ['boolean'],
        ];
    }
}
