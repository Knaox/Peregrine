<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An import is just the raw JSON content of a dropped `.json` file. The
 * controller decodes + schema-validates it before writing to disk.
 */
class ImportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1048576'],
        ];
    }
}
