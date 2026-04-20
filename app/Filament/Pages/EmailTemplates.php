<?php

namespace App\Filament\Pages;

use App\Models\Plugin;
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

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 85;

    protected static ?string $title = 'Email Templates';

    protected static ?string $navigationLabel = 'Email Templates';

    protected string $view = 'filament.pages.email-templates';

    // Invitation template fields
    public ?string $invitation_subject_en = '';

    public ?string $invitation_subject_fr = '';

    public ?string $invitation_body_en = '';

    public ?string $invitation_body_fr = '';

    // Global email settings
    public ?string $email_footer_text = '';

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->form->fill([
            'invitation_subject_en' => $settings->get(
                'email_tpl_invitation_subject_en',
                "You've been invited to join {server_name}",
            ),
            'invitation_subject_fr' => $settings->get(
                'email_tpl_invitation_subject_fr',
                'Vous avez été invité à rejoindre {server_name}',
            ),
            'invitation_body_en' => $settings->get(
                'email_tpl_invitation_body_en',
                $this->defaultBodyEn(),
            ),
            'invitation_body_fr' => $settings->get(
                'email_tpl_invitation_body_fr',
                $this->defaultBodyFr(),
            ),
            'email_footer_text' => $settings->get('email_footer_text', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make('Global')
                ->description('Settings applied to all emails sent by Peregrine.')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsible()
                ->schema([
                    TextInput::make('email_footer_text')
                        ->label('Footer text')
                        ->helperText('Appears at the bottom of every email. Leave empty for default.'),
                ]),
        ];

        // Only show invitation templates if the invitations plugin is active
        if ($this->isPluginActive('invitations')) {
            $sections[] = Section::make('Invitation Email — English')
                ->description('Template for server invitation emails (EN). Use variables: {inviter_name}, {server_name}, {permissions_list}, {accept_url}, {expires_at}, {app_name}.')
                ->icon('heroicon-o-envelope')
                ->collapsible()
                ->schema([
                    TextInput::make('invitation_subject_en')
                        ->label('Subject')
                        ->helperText('Variables: {server_name}, {inviter_name}, {app_name}'),
                    Textarea::make('invitation_body_en')
                        ->label('Body (HTML)')
                        ->rows(12)
                        ->helperText('HTML content. Variables: {inviter_name}, {server_name}, {permissions_list}, {accept_url}, {expires_at}, {app_name}'),
                ]);

            $sections[] = Section::make('Invitation Email — French')
                ->description('Template sent to recipients with a French locale. Variables: {inviter_name}, {server_name}, {permissions_list}, {accept_url}, {expires_at}, {app_name}.')
                ->icon('heroicon-o-envelope')
                ->collapsible()
                ->schema([
                    TextInput::make('invitation_subject_fr')
                        ->label('Subject'),
                    Textarea::make('invitation_body_fr')
                        ->label('Body (HTML)')
                        ->rows(12),
                ]);
        }

        return $schema->schema($sections);
    }

    private function isPluginActive(string $pluginId): bool
    {
        try {
            return Plugin::where('plugin_id', $pluginId)->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $keys = [
            'email_tpl_invitation_subject_en',
            'email_tpl_invitation_subject_fr',
            'email_tpl_invitation_body_en',
            'email_tpl_invitation_body_fr',
            'email_footer_text',
        ];

        foreach ($keys as $key) {
            $formKey = str_replace('email_tpl_', '', $key);
            $settings->set($key, $data[$formKey] ?? '');
        }

        $settings->clearCache();

        Notification::make()
            ->title('Email templates saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Templates')
                ->submit('save'),
            Action::make('reset')
                ->label('Reset to Defaults')
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
        $settings->clearCache();

        $this->mount();

        Notification::make()
            ->title('Templates reset to defaults')
            ->success()
            ->send();
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
