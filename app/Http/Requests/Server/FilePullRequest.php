<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class FilePullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createFile', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'directory' => ['nullable', 'string', 'max:500'],
            'filename' => ['nullable', 'string', 'max:255'],
        ];
    }
}
