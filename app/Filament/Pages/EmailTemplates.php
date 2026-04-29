<?php

namespace App\Filament\Pages;

use App\Models\Plugin;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class EmailTemplates extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 85;

    protected string $view = 'filament.pages.email-templates';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.email_templates.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.email_templates.title');
    }

    // Invitation template fields — legacy layout kept as-is for backward compat
    public ?string $invitation_subject_en = '';

    public ?string $invitation_subject_fr = '';

    public ?string $invitation_body_en = '';

    public ?string $invitation_body_fr = '';

    // Global email settings
    public ?string $email_footer_text = '';

    /**
     * Auth notifications templates — driven by MailTemplateRegistry.
     * Shape: [template_id => [subject_en, subject_fr, body_en, body_fr]].
     *
     * @var array<string, array<string, string>>
     */
    public array $template_values = [];

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $legacy = [
            'invitation_subject_en' => $settings->get('email_tpl_invitation_subject_en', "You've been invited to join {server_name}"),
            'invitation_subject_fr' => $settings->get('email_tpl_invitation_subject_fr', 'Vous avez été invité à rejoindre {server_name}'),
            'invitation_body_en' => $settings->get('email_tpl_invitation_body_en', $this->defaultBodyEn()),
            'invitation_body_fr' => $settings->get('email_tpl_invitation_body_fr', $this->defaultBodyFr()),
            'email_footer_text' => $settings->get('email_footer_text', ''),
        ];

        foreach (MailTemplateRegistry::all() as $tpl) {
            $this->template_values[$tpl['id']] = [
                'subject_en' => (string) $settings->get("email_tpl_{$tpl['id']}_subject_en", $tpl['default_subject_en']),
                'subject_fr' => (string) $settings->get("email_tpl_{$tpl['id']}_subject_fr", $tpl['default_subject_fr']),
                'body_en' => (string) $settings->get("email_tpl_{$tpl['id']}_body_en", $tpl['default_body_en']),
                'body_fr' => (string) $settings->get("email_tpl_{$tpl['id']}_body_fr", $tpl['default_body_fr']),
            ];
        }

        $this->form->fill(array_merge($legacy, ['template_values' => $this->template_values]));
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make(__('admin.email_templates.global_section'))
                ->description(__('admin.email_templates.global_description'))
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsible()
                ->schema([
                    TextInput::make('email_footer_text')
                        ->label(__('admin.email_templates.footer'))
                        ->helperText(__('admin.email_templates.footer_helper')),
                ]),
        ];

        // Templates from the registry — one section per entry. Bridge
        // templates are hidden when the Bridge feature is disabled (matches
        // the visibility rule used elsewhere : ServerPlanResource navigation,
        // BridgeSyncLogResource navigation).
        $bridgeActive = $this->isBridgeActive();
        foreach (MailTemplateRegistry::all() as $tpl) {
            if ($tpl['group'] === 'Bridge' && ! $bridgeActive) {
                continue;
            }
            $sections[] = $this->authTemplateSection($tpl);
        }

        // Only show invitation templates if the invitations plugin is active
        if ($this->isPluginActive('invitations')) {
            $sections[] = Section::make(__('admin.email_templates.invitation_en'))
                ->description(__('admin.email_templates.invitation_en_description'))
                ->icon('heroicon-o-envelope')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('invitation_subject_en')->label(__('admin.email_templates.subject')),
                    Textarea::make('invitation_body_en')->label(__('admin.email_templates.body'))->rows(12),
                ]);

            $sections[] = Section::make(__('admin.email_templates.invitation_fr'))
                ->description(__('admin.email_templates.invitation_fr_description'))
                ->icon('heroicon-o-envelope')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('invitation_subject_fr')->label(__('admin.email_templates.subject')),
                    Textarea::make('invitation_body_fr')->label(__('admin.email_templates.body'))->rows(12),
                ]);
        }

        return $schema->schema($sections);
    }

    /**
     * @param  array<string, mixed>  $tpl
     */
    private function authTemplateSection(array $tpl): Section
    {
        $varsHelper = 'Variables: '.implode(', ', array_map(fn ($v) => '{'.$v.'}', (array) $tpl['variables']));

        return Section::make("[{$tpl['group']}] {$tpl['label']}")
            ->description($tpl['description'].' — '.$varsHelper)
            ->icon('heroicon-o-envelope')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextInput::make("template_values.{$tpl['id']}.subject_en")->label(__('admin.email_templates.subject_en')),
                TextInput::make("template_values.{$tpl['id']}.subject_fr")->label(__('admin.email_templates.subject_fr')),
                Textarea::make("template_values.{$tpl['id']}.body_en")->label(__('admin.email_templates.body_en'))->rows(10),
                Textarea::make("template_values.{$tpl['id']}.body_fr")->label(__('admin.email_templates.body_fr'))->rows(10),
            ]);
    }

    private function isPluginActive(string $pluginId): bool
    {
        try {
            return Plugin::where('plugin_id', $pluginId)->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function isBridgeActive(): bool
    {
        // Bridge emails are ONLY relevant in Shop+Stripe mode. In Paymenter
        // mode, Paymenter sends every customer email itself — we must not
        // duplicate them.
        return app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $legacyKeys = [
            'email_tpl_invitation_subject_en' => 'invitation_subject_en',
            'email_tpl_invitation_subject_fr' => 'invitation_subject_fr',
            'email_tpl_invitation_body_en' => 'invitation_body_en',
            'email_tpl_invitation_body_fr' => 'invitation_body_fr',
            'email_footer_text' => 'email_footer_text',
        ];

        foreach ($legacyKeys as $key => $formKey) {
            $settings->set($key, $data[$formKey] ?? '');
        }

        // Auth templates — write each field only when it differs from the
        // registry default. Keeps the settings table tidy and lets the
        // registry drive future copy changes on first-time installs.
        foreach (MailTemplateRegistry::all() as $tpl) {
            $values = $data['template_values'][$tpl['id']] ?? [];
            foreach (['subject_en', 'subject_fr', 'body_en', 'body_fr'] as $field) {
                $key = "email_tpl_{$tpl['id']}_{$field}";
                $default = $tpl["default_{$field}"];
                $current = (string) ($values[$field] ?? '');

                if ($current === '' || $current === $default) {
                    $settings->forget($key);
                } else {
                    $settings->set($key, $current);
                }
            }
        }

        $settings->clearCache();

        Notification::make()->title(__('admin.notifications.email_templates_saved'))->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('admin.actions.save_templates'))->submit('save'),
            Action::make('reset')
                ->label(__('admin.actions.reset_defaults'))
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->resetToDefaults()),
        ];
    }

    private function resetToDefaults(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('email_tpl_invitation_subject_en', "You've been invited to join {server_name}");
        $settings->set('email_tpl_invitation_subject_fr', 'Vous avez été invité à rejoindre {server_name}');
        $settings->set('email_tpl_invitation_body_en', $this->defaultBodyEn());
        $settings->set('email_tpl_invitation_body_fr', $this->defaultBodyFr());
        $settings->set('email_footer_text', '');

        // Auth templates: clear the setting rows so the registry defaults
        // take over on next read.
        foreach (MailTemplateRegistry::all() as $tpl) {
            foreach (['subject_en', 'subject_fr', 'body_en', 'body_fr'] as $field) {
                $settings->forget("email_tpl_{$tpl['id']}_{$field}");
            }
        }

        $settings->clearCache();
        $this->mount();

        Notification::make()->title(__('admin.notifications.email_templates_reset'))->success()->send();
    }

    private function defaultBodyEn(): string
    {
        return <<<'HTML'
        <h1>You've been invited to a server</h1>
        <p><strong>{inviter_name}</strong> has invited you to join the server <strong>{server_name}</strong>.</p>
        <p>You will have the following permissions:</p>
        {permissions_list}
        <p style="text-align: center; margin: 24px 0;">
            <a href="{accept_url}" style="display: inline-block; padding: 12px 28px; background-color: #e11d48; color: #ffffff; text-decoration: none; font-weight: 600; border-radius: 6px;">Accept Invitation</a>
        </p>
        <p style="color: #6b7280; font-size: 13px;">This invitation expires on {expires_at}.</p>
        <p style="color: #6b7280; font-size: 13px;">If you didn't expect this invitation, you can safely ignore this email.</p>
        HTML;
    }

    private function defaultBodyFr(): string
    {
        return <<<'HTML'
        <h1>Vous avez été invité sur un serveur</h1>
        <p><strong>{inviter_name}</strong> vous a invité à rejoindre le serveur <strong>{server_name}</strong>.</p>
        <p>Vous aurez les permissions suivantes :</p>
        {permissions_list}
        <p style="text-align: center; margin: 24px 0;">
            <a href="{accept_url}" style="display: inline-block; padding: 12px 28px; background-color: #e11d48; color: #ffffff; text-decoration: none; font-weight: 600; border-radius: 6px;">Accepter l'invitation</a>
        </p>
        <p style="color: #6b7280; font-size: 13px;">Cette invitation expire le {expires_at}.</p>
        <p style="color: #6b7280; font-size: 13px;">Si vous n'attendiez pas cette invitation, vous pouvez ignorer cet email.</p>
        HTML;
    }
}
