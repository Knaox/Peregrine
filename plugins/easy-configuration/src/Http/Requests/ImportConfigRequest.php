<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an "import a real config file from a server" request. Authorization
 * is handled upstream by the `EnsureAdmin` route middleware, so this only shapes
 * the input: which server, which path, and an optional explicit format used when
 * the extension can't be auto-detected.
 */
final class ImportConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'path' => ['required', 'string', 'max:1024'],
            'format' => ['nullable', 'string', 'in:properties,ini,yaml,json,toml'],
        ];
    }
}
