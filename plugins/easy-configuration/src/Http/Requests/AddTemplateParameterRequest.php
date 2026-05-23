<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inline "annotate a discovered parameter into the template"
 * payload: which template file + key (optionally under a native section), the
 * chosen display type and the localised label/description/config. The whole
 * template is re-validated against the JSON schema in the controller after the
 * parameter is merged in. Authorisation is the route's EnsureAdmin middleware.
 */
final class AddTemplateParameterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file_id' => ['required', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'display_type' => ['required', 'string', 'in:boolean,slider,select,multiselect,text,number,textarea,color'],
            'label' => ['nullable', 'array'],
            'label.en' => ['nullable', 'string', 'max:255'],
            'label.fr' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.fr' => ['nullable', 'string'],
            'config' => ['nullable', 'array'],
            'env_var' => ['nullable', 'string', 'max:255'],
        ];
    }
}
