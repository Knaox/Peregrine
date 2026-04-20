<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FileCompressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('archiveFile', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'string'],
        ];
    }
}
