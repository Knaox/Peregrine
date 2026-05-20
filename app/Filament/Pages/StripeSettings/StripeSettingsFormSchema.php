<?php

declare(strict_types=1);

namespace App\Filament\Pages\StripeSettings;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

/**
 * Form schema for `/admin/stripe-settings`. Three sections :
 *
 *   1. Info banners — pointers to the OTHER integration touchpoints :
 *      `/admin/shops`, `/admin/pelican-webhook-settings`, third-party
 *      billing systems (WHMCS / Paymenter) configuration hint.
 *   2. Stripe inbound — webhook secret + API secret.
 *   3. Stripe customer experience — billing portal URL, resubscribe URL
 *      template, suspension grace period.
 *
 * Persisted secrets are encrypted at the page level (Crypt::encryptString
 * before write). Empty input means "keep existing value" — admin types a
 * fresh value to rotate.
 */
final class StripeSettingsFormSchema
{
    /** @return array<int, mixed> */
    public static function sections(): array
    {
        return [
            self::infoSection(),
            self::stripeInboundSection(),
            self::stripeCustomerSection(),
        ];
    }

    private static function infoSection(): Section
    {
        return Section::make(__('admin/stripe_settings.sections.info.title'))
            ->description(__('admin/stripe_settings.sections.info.description'))
            ->schema([
                Placeholder::make('multi_shop_pointer')
                    ->label(__('admin/stripe_settings.info.multi_shop.label'))
                    ->content(new HtmlString(
                        '<p class="text-sm">'
                        .__('admin/stripe_settings.info.multi_shop.body')
                        .' <a class="text-primary-600 underline" href="'.url('/admin/shops').'">/admin/shops</a></p>'
                    )),
                Placeholder::make('pelican_pointer')
                    ->label(__('admin/stripe_settings.info.pelican.label'))
                    ->content(new HtmlString(
                        '<p class="text-sm">'
                        .__('admin/stripe_settings.info.pelican.body')
                        .' <a class="text-primary-600 underline" href="'.url('/admin/pelican-webhook-settings').'">/admin/pelican-webhook-settings</a></p>'
                    )),
                Placeholder::make('third_party_pointer')
                    ->label(__('admin/stripe_settings.info.third_party.label'))
                    ->content(new HtmlString(
                        '<p class="text-sm">'
                        .__('admin/stripe_settings.info.third_party.body')
                        .' <a class="text-primary-600 underline" href="'.url('/admin/pelican-webhook-settings').'">/admin/pelican-webhook-settings</a></p>'
                    )),
            ])
            ->columns(1);
    }

    private static function stripeInboundSection(): Section
    {
        return Section::make(__('admin/stripe_settings.sections.inbound.title'))
            ->description(__('admin/stripe_settings.sections.inbound.description'))
            ->schema([
                TextInput::make('bridge_stripe_webhook_secret')
                    ->label(__('admin/stripe_settings.fields.webhook_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->placeholder(__('admin/stripe_settings.placeholders.webhook_secret'))
                    ->helperText(__('admin/stripe_settings.helpers.webhook_secret')),

                TextInput::make('bridge_stripe_api_secret')
                    ->label(__('admin/stripe_settings.fields.api_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->placeholder(__('admin/stripe_settings.placeholders.api_secret'))
                    ->helperText(__('admin/stripe_settings.helpers.api_secret')),
            ])
            ->columns(1);
    }

    private static function stripeCustomerSection(): Section
    {
        return Section::make(__('admin/stripe_settings.sections.customer.title'))
            ->description(__('admin/stripe_settings.sections.customer.description'))
            ->schema([
                TextInput::make('bridge_stripe_billing_portal_url')
                    ->label(__('admin/stripe_settings.fields.billing_portal_url'))
                    ->url()
                    ->maxLength(512)
                    ->placeholder('https://billing.stripe.com/p/login/...')
                    ->helperText(__('admin/stripe_settings.helpers.billing_portal_url')),

                TextInput::make('bridge_shop_shared_secret')
                    ->label(__('admin/stripe_settings.fields.shared_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__('admin/stripe_settings.helpers.shared_secret'))
                    ->suffixAction(
                        Action::make('generateSharedSecret')
                            ->icon('heroicon-o-key')
                            ->tooltip(__('admin/stripe_settings.fields.shared_secret_action_tooltip'))
                            ->action(function (Set $set): void {
                                $set('bridge_shop_shared_secret', base64_encode(random_bytes(32)));
                                Notification::make()
                                    ->title(__('admin/stripe_settings.notifications.secret_generated_title'))
                                    ->body(__('admin/stripe_settings.notifications.secret_generated_body'))
                                    ->warning()
                                    ->send();
                            }),
                    ),

                TextInput::make('bridge_resubscribe_url')
                    ->label(__('admin/stripe_settings.fields.resubscribe_url'))
                    ->maxLength(512)
                    ->placeholder('https://shop.example.com/resubscribe?server_id={server_id}&configuration_id={configuration_id}&subscription_id={subscription_id}&ts={ts}&signature={signature}')
                    ->helperText(__('admin/stripe_settings.helpers.resubscribe_url')),

                TextInput::make('bridge_grace_period_days')
                    ->label(__('admin/stripe_settings.fields.grace_period_days'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(365)
                    ->default(14)
                    ->helperText(__('admin/stripe_settings.helpers.grace_period_days')),
            ])
            ->columns(1);
    }
}
