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
            // `present` lets the body include `content: ""` for empty file
            // creation. `nullable` is REQUIRED on top because Laravel's
            // global `ConvertEmptyStringsToNull` middleware converts the
            // submitted `""` to `null` before validation runs — without
            // `nullable`, the `string` rule would then reject the field.
            // The controller coerces null → "" so the parser sees a string.
            'content' => ['present', 'nullable', 'string'],
        ];
    }
}
