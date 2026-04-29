<?php

namespace App\Filament\Pages\BridgeSettings;

use App\Enums\BridgeMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

/**
 * Form schema sections for the Bridge admin page.
 *
 * Mirrors the pattern used by `Settings/SettingsFormSchema.php` and
 * `AuthSettings/AuthSettingsFormSchema.php` — each top-level section is a
 * static factory method returning a Filament `Section`. Keeps the parent
 * `BridgeSettings` page focused on lifecycle (mount / save) and well below
 * the 300-line file budget.
 *
 * The `shopStripeSection()` and `paymenterSection()` accept the absolute
 * URLs as parameters (rather than recomputing them inline) so they can be
 * reused in tests / preview rendering without depending on the request
 * context.
 */
final class BridgeSettingsFormSchema
{
    public static function modeSelector(): Section
    {
        return Section::make(__('admin.bridge_form.mode.section'))
            ->description(__('admin.bridge_form.mode.description'))
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Radio::make('bridge_mode')
                    ->label(__('admin.bridge_form.mode.label'))
                    ->options([
                        BridgeMode::Disabled->value => __('admin.bridge_form.mode.options.disabled'),
                        BridgeMode::ShopStripe->value => __('admin.bridge_form.mode.options.shop_stripe'),
                        BridgeMode::Paymenter->value => __('admin.bridge_form.mode.options.paymenter'),
                    ])
                    ->default(BridgeMode::Disabled->value)
                    ->required()
                    ->live(),
            ]);
    }

    public static function shopStripeSection(string $baseUrl, string $bridgeApiDocsUrl): Section
    {
        return Section::make(__('admin.bridge_form.shop_stripe.section'))
            ->description(__('admin.bridge_form.shop_stripe.description'))
            ->icon('heroicon-o-shopping-bag')
            ->visible(fn (Get $get): bool => $get('bridge_mode') === BridgeMode::ShopStripe->value)
            ->schema([

                Section::make(__('admin.bridge_form.shop_stripe.shop_section'))
                    ->description(__('admin.bridge_form.shop_stripe.shop_section_description'))
                    ->icon('heroicon-o-arrow-path')
                    ->compact()
                    ->schema([
                        TextInput::make('bridge_shop_url')
                            ->label(__('admin.bridge_form.shop_stripe.shop_url'))
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://int.biomebounty.com')
                            ->helperText(__('admin.bridge_form.shop_stripe.shop_url_helper')),
                        TextInput::make('bridge_shop_shared_secret')
                            ->label(__('admin.bridge_form.shop_stripe.hmac_secret'))
                            ->password()
                            ->revealable()
                            ->minLength(32)
                            ->helperText(__('admin.bridge_form.shop_stripe.hmac_secret_helper'))
                            ->suffixAction(
                                Action::make('generateShopSecret')
                                    ->icon('heroicon-o-key')
                                    ->tooltip(__('admin.bridge_form.shop_stripe.hmac_action_tooltip'))
                                    ->action(function (Set $set): void {
                                        $secret = base64_encode(random_bytes(48));
                                        $set('bridge_shop_shared_secret', $secret);
                                        Notification::make()
                                            ->title(__('admin.bridge_form.shop_stripe.hmac_notification_title'))
                                            ->body(__('admin.bridge_form.shop_stripe.hmac_notification_body'))
                                            ->success()
                                            ->send();
                                    }),
                            ),
                        Placeholder::make('bridge_base_url_display')
                            ->label(__('admin.bridge_form.shop_stripe.paste_url'))
                            ->content(BridgeSettingsHtmlHelpers::renderUrlBox($baseUrl, __('admin.bridge_form.shop_stripe.paste_url_hint'))),
                        Placeholder::make('bridge_endpoints_display')
                            ->label(__('admin.bridge_form.shop_stripe.endpoints'))
                            ->content(BridgeSettingsHtmlHelpers::renderEndpointList([
                                ['POST',   $baseUrl.'/api/bridge/ping',                       __('admin.bridge_form.shop_stripe.endpoint_health')],
                                ['POST',   $baseUrl.'/api/bridge/plans/upsert',               __('admin.bridge_form.shop_stripe.endpoint_upsert')],
                                ['DELETE', $baseUrl.'/api/bridge/plans/{shop_plan_id}',      __('admin.bridge_form.shop_stripe.endpoint_delete')],
                            ])),
                        Placeholder::make('bridge_docs_link')
                            ->label(__('admin.bridge_form.shop_stripe.docs'))
                            ->content(BridgeSettingsHtmlHelpers::renderDocLink($bridgeApiDocsUrl, __('admin.bridge_form.shop_stripe.docs_hint'))),
                    ]),

                Section::make(__('admin.bridge_form.shop_stripe.stripe_section'))
                    ->description(__('admin.bridge_form.shop_stripe.stripe_section_description'))
                    ->icon('heroicon-o-credit-card')
                    ->compact()
                    ->schema([
                        TextInput::make('bridge_stripe_webhook_secret')
                            ->label(__('admin.bridge_form.shop_stripe.stripe_webhook_secret'))
                            ->password()
                            ->revealable()
                            ->placeholder('whsec_…')
                            ->helperText(__('admin.bridge_form.shop_stripe.stripe_webhook_secret_helper')),
                        TextInput::make('bridge_stripe_api_secret')
                            ->label(__('admin.bridge_form.shop_stripe.stripe_api_secret'))
                            ->password()
                            ->revealable()
                            ->placeholder('sk_live_… or sk_test_…')
                            ->helperText(__('admin.bridge_form.shop_stripe.stripe_api_secret_helper')),
                        TextInput::make('bridge_stripe_billing_portal_url')
                            ->label(__('admin.bridge_form.shop_stripe.stripe_portal'))
                            ->url()
                            ->placeholder('https://billing.stripe.com/p/login/…')
                            ->helperText(__('admin.bridge_form.shop_stripe.stripe_portal_helper')),
                        TextInput::make('bridge_resubscribe_url')
                            ->label(__('admin.bridge_form.shop_stripe.resubscribe_url'))
                            ->placeholder('https://shop.biomebounty.com/checkout/{plan_slug}')
                            ->helperText(__('admin.bridge_form.shop_stripe.resubscribe_url_helper')),
                        TextInput::make('bridge_grace_period_days')
                            ->label(__('admin.bridge_form.shop_stripe.grace_period'))
                            ->suffix(__('admin.bridge_form.shop_stripe.grace_period_suffix'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->required()
                            ->default(14)
                            ->helperText(__('admin.bridge_form.shop_stripe.grace_period_helper')),
                        Placeholder::make('bridge_stripe_endpoint_display')
                            ->label(__('admin.bridge_form.shop_stripe.stripe_endpoint_url'))
                            ->content(BridgeSettingsHtmlHelpers::renderUrlBox($baseUrl.'/api/stripe/webhook', __('admin.bridge_form.shop_stripe.stripe_endpoint_hint'))),
                        Placeholder::make('bridge_stripe_events_display')
                            ->label(__('admin.bridge_form.shop_stripe.stripe_events'))
                            ->content(BridgeSettingsHtmlHelpers::renderTagList([
                                'checkout.session.completed',
                                'customer.subscription.updated',
                                'customer.subscription.deleted',
                                'customer.subscription.trial_will_end',
                                'invoice.paid',
                                'invoice.payment_failed',
                            ])),
                    ]),
            ]);
    }

    public static function paymenterSection(string $baseUrl, string $paymenterDocsUrl, string $pelicanWebhookSettingsUrl): Section
    {
        return Section::make(__('admin.bridge_form.paymenter.section'))
            ->description(__('admin.bridge_form.paymenter.description'))
            ->icon('heroicon-o-bolt')
            ->visible(fn (Get $get): bool => $get('bridge_mode') === BridgeMode::Paymenter->value)
            ->schema([

                Section::make(__('admin.bridge_form.paymenter.webhook_section'))
                    ->description(__('admin.bridge_form.paymenter.webhook_section_description'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->compact()
                    ->schema([
                        Placeholder::make('paymenter_pelican_webhook_link')
                            ->label(__('admin.bridge_form.paymenter.webhook_link'))
                            ->content(new HtmlString(
                                '<a href="'.e($pelicanWebhookSettingsUrl).'" '
                                .'class="text-primary-600 underline hover:text-primary-500">'
                                .e(__('admin.bridge_form.paymenter.webhook_link_html')).'</a>'
                                .'<p class="mt-1 text-xs text-gray-500">'.e(__('admin.bridge_form.paymenter.webhook_link_hint')).'</p>'
                            )),
                        Placeholder::make('paymenter_docs_link')
                            ->label(__('admin.bridge_form.paymenter.docs'))
                            ->content(BridgeSettingsHtmlHelpers::renderDocLink($paymenterDocsUrl, __('admin.bridge_form.paymenter.docs_hint'))),
                    ]),
            ]);
    }
}
