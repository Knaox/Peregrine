<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class PowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $server = $this->route('server');
        $ability = match ((string) $this->input('signal')) {
            'start' => 'startServer',
            'stop', 'kill' => 'stopServer',
            'restart' => 'restartServer',
            default => 'startServer',
        };

        return $this->user()->can($ability, $server);
    }

    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', 'in:start,stop,restart,kill'],
        ];
    }
}
