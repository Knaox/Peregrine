<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new boost: a positive multiplier, end after start, and at least
 * one parameter (each with an optional per-parameter max_cap). Template boost
 * eligibility + parameter boostability are re-checked in the controller.
 */
class CreateBoostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'string', 'max:255'],
            'multiplier' => ['required', 'numeric', 'gt:0'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.file_id' => ['required', 'string', 'max:255'],
            'parameters.*.section' => ['nullable', 'string', 'max:255'],
            'parameters.*.key' => ['required', 'string', 'max:255'],
            'parameters.*.max_cap' => ['nullable', 'numeric'],
        ];
    }
}
