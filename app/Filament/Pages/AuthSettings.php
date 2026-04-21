<?php

namespace App\Filament\Pages;

use App\Filament\Pages\AuthSettings\AuthSettingsFormSchema;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Admin-facing page for auth & security configuration. Étape B populates the
 * 2FA section only. Étape C appends Shop + social provider sections alongside.
 *
 * All settings are persisted to the `settings` table via SettingsService —
 * no .env writes, no config cache invalidation needed.
 */
class AuthSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Auth & Security';

    protected string $view = 'filament.pages.auth-settings';

    public bool $auth_2fa_enabled = true;

    public bool $auth_2fa_required_admins = false;

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->auth_2fa_enabled = $settings->get('auth_2fa_enabled', 'true') === 'true';
        $this->auth_2fa_required_admins = $settings->get('auth_2fa_required_admins', 'false') === 'true';

        $this->form->fill([
            'auth_2fa_enabled' => $this->auth_2fa_enabled,
            'auth_2fa_required_admins' => $this->auth_2fa_required_admins,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            AuthSettingsFormSchema::twoFactor(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $wantsAdminEnforcement = ! empty($data['auth_2fa_required_admins']);

        // Guardrail: if the current admin is the one toggling this on and they
        // don't have 2FA yet, block — they'd lock themselves out on next request.
        if ($wantsAdminEnforcement) {
            $user = auth()->user();

            if ($user !== null && $user->is_admin && ! $user->hasTwoFactor()) {
                Notification::make()
                    ->title('Set up 2FA first')
                    ->body('Enable 2FA on your own admin account before forcing it for all admins — otherwise you will be locked out on the next request.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $settings->set('auth_2fa_enabled', empty($data['auth_2fa_enabled']) ? 'false' : 'true');
        $settings->set('auth_2fa_required_admins', $wantsAdminEnforcement ? 'true' : 'false');

        Notification::make()->title('Auth settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }
}
