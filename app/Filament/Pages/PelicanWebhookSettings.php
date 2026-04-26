<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BridgeSettings\BridgeSettingsHtmlHelpers;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Standalone admin page for the Pelican outgoing-webhook receiver.
 *
 * Decoupled from Bridge mode: the webhook endpoint is its own feature with
 * its own toggle and token. Useful in any Bridge mode :
 *   - shop_stripe : Pelican notifies install completion → Peregrine flips
 *     the local server status from `provisioning` to `active` and fires the
 *     "your server is playable" email.
 *   - paymenter   : Pelican mirrors every server/user change so the local DB
 *     stays in sync with what Paymenter has done.
 *   - disabled    : same as paymenter, useful for admin-imported servers.
 *
 * The Shop is always source of truth for ownership and billing fields. The
 * sync job applies a guard whitelist on Shop-owned servers (servers that
 * have a `stripe_subscription_id` or `plan_id`) — see SyncServerFromPelican
 * WebhookJob for the exact field set Pelican is allowed to write.
 */
class PelicanWebhookSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Pelican';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Pelican Webhook';

    protected static ?string $navigationLabel = 'Webhook receiver';

    protected static ?string $slug = 'pelican-webhook-settings';

    protected string $view = 'filament.pages.pelican-webhook-settings';

    public bool $pelican_webhook_enabled = false;

    public ?string $pelican_webhook_token = '';

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $enabled = (string) $settings->get('pelican_webhook_enabled', 'false');
        $this->pelican_webhook_enabled = $enabled === 'true' || $enabled === '1';

        // Never display the stored token — admin types a new one to replace.
        $this->pelican_webhook_token = '';

        $this->form->fill([
            'pelican_webhook_enabled' => $this->pelican_webhook_enabled,
            'pelican_webhook_token' => $this->pelican_webhook_token,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $auditLogUrl = url('/admin/pelican-webhook-logs');
        $docsUrl = url('/docs/pelican-webhook');

        return $schema->schema([
            Section::make('Receiver')
                ->description('Toggle the public webhook endpoint on or off. When off, Pelican calls return 503 and no events are processed.')
                ->icon('heroicon-o-power')
                ->schema([
                    Toggle::make('pelican_webhook_enabled')
                        ->label('Enable Pelican webhook receiver')
                        ->helperText('Disable to temporarily stop accepting webhook events without losing the configured token.'),
                ]),

            Section::make('1. Generate the bearer token')
                ->description('Pelican does not sign its webhooks — auth relies entirely on this token.')
                ->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('pelican_webhook_token')
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
                                    $set('pelican_webhook_token', $token);
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
                ->schema([
                    Placeholder::make('pelican_top_fields_display')
                        ->label('Top fields')
                        ->content(BridgeSettingsHtmlHelpers::renderKeyValueList([
                            ['Type',        'Regular'],
                            ['Description', 'Peregrine — Pelican webhook receiver'],
                            ['Endpoint',    $baseUrl.'/api/pelican/webhook'],
                        ])),
                    Placeholder::make('pelican_headers_display')
                        ->label('Headers (keep the default row, add the second)')
                        ->content(BridgeSettingsHtmlHelpers::renderHeadersList([
                            ['X-Webhook-Event', '{{event}}', 'Pelican\'s default — keep it'],
                            ['Authorization',   'Bearer &lt;token above&gt;', 'Add this row'],
                        ])),
                    Placeholder::make('pelican_events_display')
                        ->label('Events to tick (use the search bar)')
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'created: Server',
                            'updated: Server',
                            'deleted: Server',
                            'created: User',
                        ], note: 'These cover create / suspend / unsuspend / rename / build update / delete / install-finished. The `updated: Server` event is the one that fires when an install finishes (Pelican flips status from "installing" to null). ℹ️ Note: do NOT tick `event: Server\\Installed` — in some Pelican releases it crashes Pelican\'s own queue with `Cannot use object as array`. The `updated: Server` event covers install-finished anyway.')),
                ]),

            Section::make('3. Verify')
                ->description('Once Pelican saves, every event lands here for audit.')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Placeholder::make('pelican_docs_link')
                        ->label('Step-by-step walkthrough')
                        ->content(BridgeSettingsHtmlHelpers::renderDocLink($docsUrl, 'Full setup guide, troubleshooting, known limits, and how the install-status sync interacts with Bridge modes.')),
                    Placeholder::make('pelican_audit_link')
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

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $enabled = (bool) ($data['pelican_webhook_enabled'] ?? false);
        $settings->set('pelican_webhook_enabled', $enabled ? 'true' : 'false');

        $typedToken = (string) ($data['pelican_webhook_token'] ?? '');
        if ($typedToken !== '') {
            $settings->set('pelican_webhook_token', Crypt::encryptString($typedToken));
            // Also drop the legacy Bridge-coupled key so VerifyPelicanWebhookToken
            // never falls back to a stale value after rotation.
            $settings->forget('bridge_pelican_webhook_token');
        }

        Notification::make()->title('Pelican webhook settings saved')->success()->send();

        $this->pelican_webhook_token = '';
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }
}
