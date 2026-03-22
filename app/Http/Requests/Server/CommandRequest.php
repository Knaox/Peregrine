<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sendCommand', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:1000'],
        ];
    }
}
