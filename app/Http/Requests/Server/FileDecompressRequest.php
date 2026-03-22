<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FileDecompressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string'],
            'file' => ['required', 'string'],
        ];
    }
}
