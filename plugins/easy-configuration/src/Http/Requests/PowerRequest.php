<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a power signal. The plugin only exposes the signals needed for the
 * config-editing flow (stop the server, then start/restart it again).
 */
class PowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', Rule::in(['start', 'stop', 'restart'])],
        ];
    }
}
