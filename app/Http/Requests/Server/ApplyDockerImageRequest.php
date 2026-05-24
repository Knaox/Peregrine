<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDockerImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Changing the Docker image is a startup-configuration change.
        return $this->user()->can('updateStartup', $this->route('server'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'string', 'max:255'],
        ];
    }
}
