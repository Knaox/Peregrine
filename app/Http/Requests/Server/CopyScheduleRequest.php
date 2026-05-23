<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class CopyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reading the source schedule is the prerequisite; per-target
        // schedule.create is enforced in the controller (one server each).
        return $this->user()->can('readSchedule', $this->route('server'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_server_ids' => ['required', 'array', 'min:1'],
            'target_server_ids.*' => ['integer', 'distinct', 'exists:servers,id'],
        ];
    }
}
