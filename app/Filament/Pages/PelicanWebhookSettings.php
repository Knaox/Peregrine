<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BridgeSettings\BridgeSettingsHtmlHelpers;
use App\Filament\Pages\Concerns\HasMirrorReadsSection;
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
    use HasMirrorReadsSection;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'pelican-webhook-settings';

    protected string $view = 'filament.pages.pelican-webhook-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
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
            Section::make(__('admin.webhook_settings.sections.receiver'))
                ->description(__('admin.webhook_settings.sections.receiver_description'))
                ->icon('heroicon-o-power')
                ->schema([
                    Toggle::make('pelican_webhook_enabled')
                        ->label(__('admin.webhook_settings.fields.enabled'))
                        ->helperText(__('admin.webhook_settings.fields.enabled_helper')),
                ]),

            Section::make(__('admin.webhook_settings.sections.token'))
                ->description(__('admin.webhook_settings.sections.token_description'))
                ->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('pelican_webhook_token')
                        ->label(__('admin.webhook_settings.fields.token'))
                        ->password()
                        ->revealable()
                        ->minLength(32)
                        ->helperText(__('admin.webhook_settings.fields.token_helper'))
                        ->suffixAction(
                            Action::make('generatePelicanToken')
                                ->icon('heroicon-o-key')
                                ->tooltip(__('admin.webhook_settings.fields.token_action_tooltip'))
                                ->action(function (Set $set): void {
                                    $token = base64_encode(random_bytes(48));
                                    $set('pelican_webhook_token', $token);
                                    Notification::make()
                                        ->title(__('admin.webhook_settings.notifications.token_generated_title'))
                                        ->body(__('admin.webhook_settings.notifications.token_generated_body'))
                                        ->success()
                                        ->send();
                                }),
                        ),
                ]),

            Section::make(__('admin.webhook_settings.sections.configure'))
                ->description(__('admin.webhook_settings.sections.configure_description'))
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Placeholder::make('pelican_top_fields_display')
                        ->label(__('admin.webhook_settings.fields.top_fields'))
                        ->content(BridgeSettingsHtmlHelpers::renderKeyValueList([
                            ['Type',        __('admin.webhook_settings.top_fields.type')],
                            ['Description', __('admin.webhook_settings.top_fields.description')],
                            ['Endpoint',    $baseUrl.'/api/pelican/webhook'],
                        ])),
                    Placeholder::make('pelican_headers_display')
                        ->label(__('admin.webhook_settings.fields.headers'))
                        ->content(BridgeSettingsHtmlHelpers::renderHeadersList([
                            ['X-Webhook-Event', '{{event}}', __('admin.webhook_settings.header_descriptions.pelican_default')],
                            ['Authorization',   __('admin.webhook_settings.header_values.token_placeholder'), __('admin.webhook_settings.header_descriptions.add_row')],
                        ])),

                    Placeholder::make('pelican_events_required')
                        ->label(__('admin.webhook_settings.fields.events_required'))
                        ->content(BridgeSettingsHtmlHelpers::renderTagList([
                            'created: Server',
                            'updated: Server',
                            'deleted: Server',
                            'created: User',
                        ], note: __('admin.webhook_settings.fields.events_required_note'))),

                    Placeholder::make('pelican_events_recommended')
                        ->label(__('admin.webhook_settings.fields.events_recommended'))
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
                        ], note: __('admin.webhook_settings.fields.events_recommended_note'))),

                    Placeholder::make('pelican_events_phase2')
                        ->label(__('admin.webhook_settings.fields.events_phase2'))
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
                        ], note: __('admin.webhook_settings.fields.events_phase2_note'))),

                    Placeholder::make('pelican_events_blocklist')
                        ->label(__('admin.webhook_settings.fields.events_blocklist'))
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
                        ], note: __('admin.webhook_settings.fields.events_blocklist_note'))),
                ]),

            $this->mirrorReadsSection(),

            Section::make(__('admin.webhook_settings.sections.verify'))
                ->description(__('admin.webhook_settings.sections.verify_description'))
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Placeholder::make('pelican_docs_link')
                        ->label(__('admin.webhook_settings.fields.docs'))
                        ->content(BridgeSettingsHtmlHelpers::renderDocLink($docsUrl, __('admin.webhook_settings.fields.docs_note'))),
                    Placeholder::make('pelican_audit_link')
                        ->label(__('admin.webhook_settings.fields.audit'))
                        ->content(new HtmlString(
                            '<a href="'.e($auditLogUrl).'" '
                            .'style="color: rgb(var(--primary-400)); text-decoration: underline;">'
                            .'/admin/pelican-webhook-logs ↗</a>'
                            .'<p style="margin-top: 0.25rem; font-size: 0.75rem; color: rgba(255,255,255,0.5);">'
                            .e(__('admin.webhook_settings.fields.audit_note'))
                            .'</p>'
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

        // mirror_reads_enabled is no longer toggled from this form — it's
        // driven by the dedicated "Activer la lecture DB locale" action
        // (HasMirrorReadsSection trait) which dispatches the backfill job
        // and only flips the flag once the backfill completes successfully.

        $typedToken = (string) ($data['pelican_webhook_token'] ?? '');
        if ($typedToken !== '') {
            $settings->set('pelican_webhook_token', Crypt::encryptString($typedToken));
            // Also drop the legacy Bridge-coupled key so VerifyPelicanWebhookToken
            // never falls back to a stale value after rotation.
            $settings->forget('bridge_pelican_webhook_token');
        }

        Notification::make()->title(__('admin.webhook_settings.notifications.saved'))->success()->send();

        $this->pelican_webhook_token = '';
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('admin.actions.save_settings'))->submit('save'),
        ];
    }
}
