<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class PowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('controlPower', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', 'in:start,stop,restart,kill'],
        ];
    }
}
