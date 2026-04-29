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

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'pelican-webhook-settings';

    protected string $view = 'filament.pages.pelican-webhook-settings';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.pelican');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.pelican_webhook_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.pelican_webhook_settings.title');
    }

    public bool $pelican_webhook_enabled = false;

    public ?string $pelican_webhook_token = '';

    public bool $mirror_reads_enabled = false;

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $enabled = (string) $settings->get('pelican_webhook_enabled', 'false');
        $this->pelican_webhook_enabled = $enabled === 'true' || $enabled === '1';

        $mirror = (string) $settings->get('mirror_reads_enabled', 'false');
        $this->mirror_reads_enabled = $mirror === 'true' || $mirror === '1';

        // Never display the stored token — admin types a new one to replace.
        $this->pelican_webhook_token = '';

        $this->form->fill([
            'pelican_webhook_enabled' => $this->pelican_webhook_enabled,
            'pelican_webhook_token' => $this->pelican_webhook_token,
            'mirror_reads_enabled' => $this->mirror_reads_enabled,
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
                ->description('Top fields + headers + events to tick. The events list is grouped by priority — start with "Required", add others as needed.')
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

                    Placeholder::make('pelican_events_required')
                        ->label('Required (Shop+Stripe install completion)')
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'created: Server',
                            'updated: Server',
                            'deleted: Server',
                            'created: User',
                        ], note: 'These four are mandatory in Shop+Stripe mode — `updated: Server` is the canonical install-completion signal (Pelican flips status from "installing" to null). Without it, servers created via Stripe stay in "provisioning" forever and a "stuck" badge shows in /admin/servers.')),

                    Placeholder::make('pelican_events_recommended')
                        ->label('Recommended — Phase 1 (cuts manual sync)')
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'updated: User',
                            'deleted: User',
                            'created: Node',
                            'updated: Node',
                            'deleted: Node',
                            'created: Egg',
                            'updated: Egg',
                            'deleted: Egg',
                            'created: EggVariable',
                            'updated: EggVariable',
                            'deleted: EggVariable',
                        ], note: 'Mirrors user email/name changes, node infrastructure, and egg/variable definitions in real time. With these ticked, the manual `sync:users / sync:nodes / sync:eggs` commands become safety nets you rarely need.')),

                    Placeholder::make('pelican_events_phase2')
                        ->label('Phase 2 preview — DB-local mirrors (not active yet)')
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'created: Backup',
                            'updated: Backup',
                            'deleted: Backup',
                            'created: Allocation',
                            'updated: Allocation',
                            'deleted: Allocation',
                            'created: Database',
                            'updated: Database',
                            'deleted: Database',
                            'created: DatabaseHost',
                            'updated: DatabaseHost',
                            'deleted: DatabaseHost',
                            'created: ServerTransfer',
                            'updated: ServerTransfer',
                            'deleted: ServerTransfer',
                        ], note: 'Reserved for the upcoming Phase 2 (DB-local mirrors that make /backups, /databases, /network pages instant). Ticking them now is harmless — the receiver will record them as ignored until Phase 2 ships.')),

                    Placeholder::make('pelican_events_blocklist')
                        ->label('À NE PAS cocher')
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'event: Server\\Installed',
                            'event: ActivityLogged',
                            'created: Schedule',
                            'updated: Schedule',
                            'deleted: Schedule',
                            'created: Task',
                            'updated: Task',
                            'created: ApiKey',
                            'updated: ApiKey',
                            'created: Webhook',
                            'updated: WebhookConfiguration',
                        ], note: '`event: Server\\Installed` crashes Pelican\'s own queue on some releases (`Cannot use object as array`) — `updated: Server` already covers install-finished. `Schedule` and `Task` fire on every cron tick (flood). `ActivityLog` fires on every user action (flood). `ApiKey` updates `last_used_at` on every API call (noise). `Webhook` / `WebhookConfiguration` create infinite loops.')),
                ]),

            Section::make('Phase 2 — Lectures DB locale')
                ->description('Active la lecture des pages Backups / Databases / Network depuis la base locale Peregrine au lieu d\'appeler Pelican à chaque chargement. Active uniquement APRÈS avoir lancé `php artisan pelican:backfill-mirrors` au moins une fois.')
                ->icon('heroicon-o-circle-stack')
                ->schema([
                    Toggle::make('mirror_reads_enabled')
                        ->label('Activer la lecture DB locale')
                        ->helperText('Si désactivé, les controllers continuent d\'appeler l\'API Pelican avec un cache de 2-10 min (comportement Phase 1). Si activé, les pages lisent les tables miroir pelican_backups / pelican_databases / pelican_allocations — pages instantanées + Peregrine continue de fonctionner même si Pelican est temporairement indisponible.'),
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

        $mirrorReads = (bool) ($data['mirror_reads_enabled'] ?? false);
        $settings->set('mirror_reads_enabled', $mirrorReads ? 'true' : 'false');

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
