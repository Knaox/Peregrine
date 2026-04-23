<?php

namespace App\Http\Requests\Bridge;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the JSON payload pushed by the Shop on POST /api/bridge/plans/upsert.
 *
 * Authorization is handled upstream by VerifyBridgeSignature (HMAC). This
 * request only validates payload structure — invalid payloads return 422
 * with field-level details.
 */
class BridgeUpsertPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization happens in VerifyBridgeSignature middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_plan_id' => ['required', 'integer', 'min:1'],
            'shop_plan_slug' => ['required', 'string', 'max:255'],
            'shop_plan_type' => ['required', 'in:subscription,one_time'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],

            'billing' => ['required', 'array'],
            'billing.price_cents' => ['required', 'integer', 'min:0'],
            'billing.currency' => ['required', 'string', 'size:3'],
            'billing.interval' => ['nullable', 'required_if:shop_plan_type,subscription', 'in:day,week,month,year'],
            'billing.interval_count' => ['nullable', 'required_if:shop_plan_type,subscription', 'integer', 'min:1'],
            'billing.has_trial' => ['required', 'boolean'],
            'billing.trial_interval' => ['nullable', 'in:day,week,month,year'],
            'billing.trial_interval_count' => ['nullable', 'integer', 'min:1'],
            'billing.stripe_price_id' => ['nullable', 'string', 'max:255'],

            'pelican_specs' => ['required', 'array'],
            'pelican_specs.ram_mb' => ['required', 'integer', 'min:128'],
            'pelican_specs.swap_mb' => ['nullable', 'integer', 'min:0'],
            'pelican_specs.disk_mb' => ['required', 'integer', 'min:256'],
            'pelican_specs.cpu_percent' => ['required', 'integer', 'min:1'],
            'pelican_specs.io_weight' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'pelican_specs.cpu_pinning' => ['nullable', 'string', 'max:255'],

            'checkout' => ['nullable', 'array'],
            'checkout.custom_fields' => ['nullable', 'array', 'max:3'], // Stripe limit
            'checkout.custom_fields.*.key' => ['required_with:checkout.custom_fields', 'string', 'max:40', 'alpha_dash'],
            'checkout.custom_fields.*.label' => ['required_with:checkout.custom_fields', 'string', 'max:100'],
            'checkout.custom_fields.*.type' => ['required_with:checkout.custom_fields', 'in:text,numeric,dropdown'],
            'checkout.custom_fields.*.optional' => ['required_with:checkout.custom_fields', 'boolean'],
        ];
    }
}
