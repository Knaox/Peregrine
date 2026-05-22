<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a copy request: the chosen target server ids and, per file, the
 * parameters to copy. Target ownership + egg match are re-checked in the
 * controller; this only guards the body shape.
 */
class CopyConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['integer'],
            'files' => ['required', 'array', 'min:1'],
            'files.*.id' => ['required', 'string', 'max:255'],
            'files.*.params' => ['present', 'array'],
            'files.*.params.*.key' => ['required', 'string', 'max:255'],
            'files.*.params.*.section' => ['nullable', 'string', 'max:255'],
            'copy_boosts' => ['sometimes', 'boolean'],
        ];
    }
}
