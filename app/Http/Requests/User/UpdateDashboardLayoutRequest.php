<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardLayoutRequest extends FormRequest
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
            'layout' => ['required', 'array'],
            'layout.categories' => ['present', 'array', 'max:50'],
            'layout.categories.*.id' => ['required', 'string', 'max:20'],
            'layout.categories.*.name' => ['required', 'string', 'max:100'],
            'layout.categories.*.serverIds' => ['present', 'array'],
            'layout.categories.*.serverIds.*' => ['integer'],
            'layout.uncategorizedOrder' => ['present', 'array'],
            'layout.uncategorizedOrder.*' => ['integer'],
        ];
    }
}
