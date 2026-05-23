<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of an atomic config save. Server access + Easy
 * Configuration write permission are enforced in the controller; this only
 * guards the request body shape.
 */
class SaveConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*.id' => ['required', 'string', 'max:255'],
            'files.*.values' => ['present', 'array'],
            'files.*.values.*.key' => ['required', 'string', 'max:255'],
            'files.*.values.*.section' => ['nullable', 'string', 'max:255'],
            'files.*.values.*.value' => ['present'],
            'files.*.values.*.occurrence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
