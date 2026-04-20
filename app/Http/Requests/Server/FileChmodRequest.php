<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FileChmodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateFile', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*.file' => ['required', 'string'],
            // Accept octal string (e.g. "755") or integer (493).
            'files.*.mode' => ['required'],
        ];
    }
}
