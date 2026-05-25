<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Filament\Pages;

use App\Services\Plugin\PluginSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Plugins\PeregrinePhpmyadmin\Filament\Pages\PmaPluginSettings\PmaSettingsFormSchema;
use Plugins\PeregrinePhpmyadmin\PhpMyAdminServiceProvider;
use Plugins\PeregrinePhpmyadmin\Services\PmaDocRenderer;
use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;
use Throwable;

/**
 * Admin config + install guide for the phpMyAdmin plugin. Reached via the
 * Plugins page "Manage" button (manage_url = /admin/pma-settings), hidden from
 * the sidebar. Settings persist to the `plugins.settings` JSON column; the
 * shared secret is encrypted by PmaSettings. Header actions expose the
 * bilingual guide, a ready-to-paste test curl, and a reachability probe.
 */
class PmaPluginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $slug = 'pma-settings';

    protected string $view = 'peregrine-phpmyadmin::pages.settings';

    private const NS = 'peregrine-phpmyadmin::messages.settings.';

    public bool $enabled = false;

    public ?string $pma_url = '';

    public ?string $shared_secret = '';

    public int $token_ttl = 30;

    public bool $auto_select_db = true;

    public bool $auto_login = true;

    public ?int $pma_server_index = null;

    public ?string $ip_allowlist = '';

    public int $rate_limit_per_user = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __(self::NS.'title');
    }

    public function mount(): void
    {
        $settings = PmaSettings::make();

        // Generate a secret on first visit so the admin always has one to copy.
        if ($settings->sharedSecret === '') {
            PmaSettings::storeSecret(PmaSettings::generateSecret());
            $settings = PmaSettings::make();
        }

        $store = app(PluginSettings::class);
        $id = PhpMyAdminServiceProvider::PLUGIN_ID;

        $this->enabled = $settings->enabled;
        $this->pma_url = $settings->pmaUrl;
        $this->shared_secret = $settings->sharedSecret;
        $this->token_ttl = $settings->tokenTtl;
        $this->auto_select_db = $settings->autoSelectDb;
        $this->auto_login = $settings->autoLogin;
        $this->pma_server_index = $settings->serverIndex > 0 ? $settings->serverIndex : null;
        $this->ip_allowlist = (string) $store->getSetting($id, 'ip_allowlist', '');
        $this->rate_limit_per_user = $settings->rateLimitPerUser;

        $this->form->fill([
            'enabled' => $this->enabled,
            'pma_url' => $this->pma_url,
            'shared_secret' => $this->shared_secret,
            'token_ttl' => $this->token_ttl,
            'auto_select_db' => $this->auto_select_db,
            'auto_login' => $this->auto_login,
            'pma_server_index' => $this->pma_server_index,
            'ip_allowlist' => $this->ip_allowlist,
            'rate_limit_per_user' => $this->rate_limit_per_user,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(PmaSettingsFormSchema::sections());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $store = app(PluginSettings::class);
        $id = PhpMyAdminServiceProvider::PLUGIN_ID;

        $store->setSetting($id, 'enabled', (bool) ($data['enabled'] ?? false));
        $store->setSetting($id, 'pma_url', rtrim((string) ($data['pma_url'] ?? ''), '/'));
        $store->setSetting($id, 'token_ttl', max(10, min(120, (int) ($data['token_ttl'] ?? 30))));
        $store->setSetting($id, 'auto_select_db', (bool) ($data['auto_select_db'] ?? true));
        $store->setSetting($id, 'auto_login', (bool) ($data['auto_login'] ?? true));
        $store->setSetting($id, 'pma_server_index', (int) ($data['pma_server_index'] ?? 0));
        $store->setSetting($id, 'ip_allowlist', (string) ($data['ip_allowlist'] ?? ''));
        $store->setSetting($id, 'rate_limit_per_user', max(1, (int) ($data['rate_limit_per_user'] ?? 20)));

        // Secret is prefilled from mount, so a normal save re-stores it.
        $secret = (string) ($data['shared_secret'] ?? '');
        if ($secret !== '') {
            PmaSettings::storeSecret($secret);
        }

        Notification::make()->title(__(self::NS.'saved'))->success()->send();
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->guideAction(),
            $this->curlAction(),
            $this->reachabilityAction(),
        ];
    }

    private function guideAction(): Action
    {
        return Action::make('guide')
            ->label(__(self::NS.'guide'))
            ->icon('heroicon-o-book-open')
            ->color('gray')
            ->modalHeading(__(self::NS.'guide'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (): View => view('peregrine-phpmyadmin::guide', [
                'ctx' => app(PmaDocRenderer::class)->context(PmaSettings::make()),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__(self::NS.'close'));
    }

    private function curlAction(): Action
    {
        return Action::make('curl')
            ->label(__(self::NS.'test_curl'))
            ->icon('heroicon-o-command-line')
            ->color('gray')
            ->modalHeading(__(self::NS.'test_curl'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(function (): View {
                $token = 'test_'.Str::random(40);
                app(PmaTokenStore::class)->put($token, [
                    'username' => 'test', 'password' => 'test',
                    'host' => 'test.invalid', 'port' => 3306, 'database' => 'test',
                ], 120);

                return view('peregrine-phpmyadmin::curl', [
                    'curl' => app(PmaDocRenderer::class)->curlSnippet(PmaSettings::make(), $token),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__(self::NS.'close'));
    }

    private function reachabilityAction(): Action
    {
        return Action::make('reachability')
            ->label(__(self::NS.'test_reachability'))
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function (): void {
                $url = PmaSettings::make()->pmaUrl;

                if ($url === '') {
                    Notification::make()->title(__(self::NS.'reachability_no_url'))->warning()->send();

                    return;
                }

                try {
                    Http::timeout(5)->get($url);
                    Notification::make()->title(__(self::NS.'reachability_ok'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__(self::NS.'reachability_fail'))->body($e->getMessage())->danger()->send();
                }
            });
    }
}
