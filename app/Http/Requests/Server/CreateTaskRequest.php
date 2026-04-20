<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSchedule', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:command,power,backup'],
            'payload' => ['nullable', 'string'],
            'time_offset' => ['required', 'integer', 'min:0', 'max:900'],
        ];
    }
}
