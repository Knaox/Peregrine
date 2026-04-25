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
        return Section::make('Mode')
            ->description('Select which bridge backend should be active. Only one can run at a time — Shop+Stripe and Paymenter cannot coexist.')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Radio::make('bridge_mode')
                    ->label('Active bridge backend')
                    ->options([
                        BridgeMode::Disabled->value => BridgeMode::Disabled->label() . ' — no external bridge',
                        BridgeMode::ShopStripe->value => BridgeMode::ShopStripe->label() . ' — your shop pushes plans via signed HTTP, Stripe sends webhooks',
                        BridgeMode::Paymenter->value => BridgeMode::Paymenter->label() . ' — Paymenter orchestrates, Pelican forwards events',
                    ])
                    ->default(BridgeMode::Disabled->value)
                    ->required()
                    ->live(),
            ]);
    }

    public static function shopStripeSection(string $baseUrl, string $bridgeApiDocsUrl): Section
    {
        return Section::make('Bridge Shop ↔ Peregrine + Stripe')
            ->description('Your shop pushes plan definitions to Peregrine via signed HTTP. Stripe sends subscription lifecycle events directly. Independent channels — both belong to this mode.')
            ->icon('heroicon-o-shopping-bag')
            ->visible(fn (Get $get): bool => $get('bridge_mode') === BridgeMode::ShopStripe->value)
            ->schema([

                Section::make('1. Shop API channel')
                    ->description('Authenticate the shop and tell it where Peregrine lives.')
                    ->icon('heroicon-o-arrow-path')
                    ->compact()
                    ->schema([
                        TextInput::make('bridge_shop_url')
                            ->label('Shop base URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://int.biomebounty.com')
                            ->helperText('Informational only — used for "back to Shop" links in the admin UI later.'),
                        TextInput::make('bridge_shop_shared_secret')
                            ->label('Shared HMAC secret')
                            ->password()
                            ->revealable()
                            ->minLength(32)
                            ->helperText('Leave blank to keep the stored value. Click 🔑 to generate a fresh 64-char secret. Must match the value configured on the shop side.')
                            ->suffixAction(
                                Action::make('generateShopSecret')
                                    ->icon('heroicon-o-key')
                                    ->tooltip('Generate a new random 64-char secret')
                                    ->action(function (Set $set): void {
                                        $secret = base64_encode(random_bytes(48));
                                        $set('bridge_shop_shared_secret', $secret);
                                        Notification::make()
                                            ->title('Secret generated')
                                            ->body('Copy it from the field, paste it on the shop side, then click Save.')
                                            ->success()
                                            ->send();
                                    }),
                            ),
                        Placeholder::make('bridge_base_url_display')
                            ->label('Paste this URL on the shop side')
                            ->content(BridgeSettingsHtmlHelpers::renderUrlBox($baseUrl, 'Use the base URL only — the shop client appends /api/bridge/* paths automatically. Never include a path here.')),
                        Placeholder::make('bridge_endpoints_display')
                            ->label('Endpoints the shop will call')
                            ->content(BridgeSettingsHtmlHelpers::renderEndpointList([
                                ['POST',   $baseUrl.'/api/bridge/ping',                       'Health check'],
                                ['POST',   $baseUrl.'/api/bridge/plans/upsert',               'Create or refresh a plan'],
                                ['DELETE', $baseUrl.'/api/bridge/plans/{shop_plan_id}',      'Deactivate a plan'],
                            ])),
                        Placeholder::make('bridge_docs_link')
                            ->label('Developer documentation')
                            ->content(BridgeSettingsHtmlHelpers::renderDocLink($bridgeApiDocsUrl, 'Full API contract, payload schema, HMAC signing examples (PHP / Node / Python), error codes.')),
                    ]),

                Section::make('2. Stripe webhook channel')
                    ->description('Stripe sends subscription lifecycle events straight to Peregrine — independent from the shop API above.')
                    ->icon('heroicon-o-credit-card')
                    ->compact()
                    ->schema([
                        TextInput::make('bridge_stripe_webhook_secret')
                            ->label('Stripe webhook signing secret')
                            ->password()
                            ->revealable()
                            ->placeholder('whsec_…')
                            ->helperText('Stripe Dashboard → Developers → Webhooks → click your endpoint → "Signing secret". Leave blank to keep the stored value.'),
                        TextInput::make('bridge_stripe_api_secret')
                            ->label('Stripe API secret key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk_live_… or sk_test_…')
                            ->helperText('Stripe Dashboard → Developers → API keys → "Secret key". Required to expand line_items on checkout.session.completed (Stripe never inlines them in the webhook payload). Leave blank to keep the stored value.'),
                        TextInput::make('bridge_grace_period_days')
                            ->label('Grace period before hard delete')
                            ->suffix('days')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->required()
                            ->default(14)
                            ->helperText('On subscription cancellation: server is suspended immediately and scheduled for deletion after this many days. Admin can cancel the deletion during the window. 0 = delete immediately. Max 90.'),
                        Placeholder::make('bridge_stripe_endpoint_display')
                            ->label('Endpoint URL to add in Stripe Dashboard')
                            ->content(BridgeSettingsHtmlHelpers::renderUrlBox($baseUrl.'/api/stripe/webhook', 'Stripe Dashboard → Developers → Webhooks → Add endpoint.')),
                        Placeholder::make('bridge_stripe_events_display')
                            ->label('Events to enable on that endpoint')
                            ->content(BridgeSettingsHtmlHelpers::renderTagList([
                                'checkout.session.completed',
                                'customer.subscription.updated',
                                'customer.subscription.deleted',
                                'invoice.payment_failed',
                            ])),
                    ]),
            ]);
    }

    public static function paymenterSection(string $baseUrl, string $paymenterDocsUrl, string $auditLogUrl): Section
    {
        return Section::make('Bridge Paymenter (via Pelican webhooks)')
            ->description('Paymenter handles billing, plans, emails, and orchestrates Pelican directly. Peregrine just mirrors server state by listening to Pelican\'s native outgoing webhooks. No plans page and no Bridge emails are sent in this mode.')
            ->icon('heroicon-o-bolt')
            ->visible(fn (Get $get): bool => $get('bridge_mode') === BridgeMode::Paymenter->value)
            ->schema([

                Section::make('1. Generate the bearer token')
                    ->description('Pelican does not sign its webhooks — auth relies entirely on this token.')
                    ->icon('heroicon-o-key')
                    ->compact()
                    ->schema([
                        TextInput::make('bridge_pelican_webhook_token')
                            ->label('Pelican webhook authentication token')
                            ->password()
                            ->revealable()
                            ->minLength(32)
                            ->helperText('Click 🔑 to generate a fresh random 64-char token. Leave blank on save to keep the stored value (rotation requires updating the Pelican headers in lockstep).')
                            ->suffixAction(
                                Action::make('generatePelicanToken')
                                    ->icon('heroicon-o-key')
                                    ->tooltip('Generate a new random 64-char token')
                                    ->action(function (Set $set): void {
                                        $token = base64_encode(random_bytes(48));
                                        $set('bridge_pelican_webhook_token', $token);
                                        Notification::make()
                                            ->title('Token generated')
                                            ->body('Copy it from the field, paste it in Pelican\'s webhook headers, then click Save.')
                                            ->success()
                                            ->send();
                                    }),
                            ),
                    ]),

                Section::make('2. Configure Pelican (/admin/webhooks → Create Webhook)')
                    ->description('Three pieces of config to enter on the Pelican side. Take them in order.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->compact()
                    ->schema([
                        Placeholder::make('paymenter_top_fields_display')
                            ->label('Top fields')
                            ->content(BridgeSettingsHtmlHelpers::renderKeyValueList([
                                ['Type',        'Regular'],
                                ['Description', 'Peregrine mirror — Bridge Paymenter'],
                                ['Endpoint',    $baseUrl.'/api/pelican/webhook'],
                            ])),
                        Placeholder::make('paymenter_headers_display')
                            ->label('Headers (keep the default row, add the second)')
                            ->content(BridgeSettingsHtmlHelpers::renderHeadersList([
                                ['X-Webhook-Event', '{{event}}', 'Pelican\'s default — keep it'],
                                ['Authorization',   'Bearer &lt;token above&gt;', 'Add this row'],
                            ])),
                        Placeholder::make('paymenter_events_display')
                            ->label('Events to tick (use the search bar)')
                            ->content(BridgeSettingsHtmlHelpers::renderTagList([
                                'created: Server',
                                'updated: Server',
                                'deleted: Server',
                                'created: User',
                                'event: Server\\Installed',
                            ], note: 'These cover create / suspend / unsuspend / rename / build update / delete / install-finished. The reconciliation job (every 5 min) catches anything Pelican fails to deliver — Pelican does not retry. ℹ️ Note: in older Pelican releases `event: Server\\Installed` could crash Pelican\'s own ProcessWebhook job (`Cannot use object as array`). If you see those errors in Pelican\'s queue, untick that single event — `updated: Server` covers the install-finished case anyway (Pelican flips status from "installing" to null).')),
                    ]),

                Section::make('3. Verify')
                    ->description('Once Pelican saves, every event lands here for audit.')
                    ->icon('heroicon-o-check-circle')
                    ->compact()
                    ->schema([
                        Placeholder::make('paymenter_docs_link')
                            ->label('Step-by-step walkthrough')
                            ->content(BridgeSettingsHtmlHelpers::renderDocLink($paymenterDocsUrl, 'Full setup guide, troubleshooting, known limits, and the matching Paymenter / Pelican-Paymenter extension setup.')),
                        Placeholder::make('paymenter_audit_link')
                            ->label('Live audit of received webhooks')
                            ->content(new HtmlString(
                                '<a href="'.e($auditLogUrl).'" '
                                .'class="text-primary-600 underline hover:text-primary-500">'
                                .'/admin/pelican-webhook-logs ↗</a>'
                                .'<p class="mt-1 text-xs text-gray-500">Every accepted webhook event with HTTP status, error message, and idempotency hash.</p>'
                            )),
                    ]),
            ]);
    }
}
