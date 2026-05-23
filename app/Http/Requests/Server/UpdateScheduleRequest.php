<?php

namespace App\Http\Requests\Server;

/**
 * Same fields as creating a schedule, but gated on `updateSchedule` rather
 * than `createSchedule` so the "Edit schedule" UI (shown to anyone with the
 * schedule.update permission) doesn't 403 for users who can edit but not create.
 */
class UpdateScheduleRequest extends CreateScheduleRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSchedule', $this->route('server'));
    }
}
