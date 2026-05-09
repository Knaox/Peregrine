<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Webhooks\WebhookEventTypes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateWebhookEndpointRequest extends FormRequest
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
        $allowed = WebhookEventTypes::all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https', 'max:1024', $this->urlSafetyRule()],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => ['string', 'in:'.implode(',', $allowed)],
            'max_retries' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:120'],
        ];
    }

    /**
     * Block obvious DNS-rebinding / loopback / private IP destinations.
     * Done as a closure so we can return a meaningful message and add
     * IPv6 cases without touching `urlSafetyRule()` callers.
     */
    private function urlSafetyRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            $host = parse_url($value, PHP_URL_HOST);
            if ($host === false || $host === null) {
                return;
            }
            $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
            if (in_array(strtolower($host), $blocked, true)) {
                $fail("URL host {$host} is not allowed.");
                return;
            }
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    $fail("URL targets a private/reserved IP and is not allowed.");
                }
            }
        };
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'validation_failed',
                'message' => __('api_v1.validation_failed'),
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
