<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FileWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateFile', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
        ];
    }
}
