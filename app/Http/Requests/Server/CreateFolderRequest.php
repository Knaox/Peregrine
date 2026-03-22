<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CreateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
