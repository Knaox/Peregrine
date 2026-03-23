<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'minute' => ['required', 'string'],
            'hour' => ['required', 'string'],
            'day_of_month' => ['required', 'string'],
            'month' => ['required', 'string'],
            'day_of_week' => ['required', 'string'],
            'is_active' => ['boolean'],
            'only_when_online' => ['boolean'],
        ];
    }
}
