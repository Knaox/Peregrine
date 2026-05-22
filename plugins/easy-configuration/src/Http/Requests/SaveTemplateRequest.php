<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape guard for creating/updating a template. The deep schema validation is
 * done by TemplateSchemaValidator in the controller (it returns rich,
 * field-level errors the editor surfaces). Admin gating is the route middleware.
 */
class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template' => ['required', 'array'],
            'template.id' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/i', 'max:255'],
        ];
    }
}
