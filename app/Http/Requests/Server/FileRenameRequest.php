<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FileRenameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*.from' => ['required', 'string'],
            'files.*.to' => ['required', 'string'],
        ];
    }
}
