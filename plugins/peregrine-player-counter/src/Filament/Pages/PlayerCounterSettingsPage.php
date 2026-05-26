<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Filament\Pages;

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
use Plugins\PeregrinePlayerCounter\Filament\Pages\PlayerCounterSettingsPage\PlayerCounterFormSchema;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Plugins\PeregrinePlayerCounter\Services\PlayerCounterDocRenderer;
use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;
use Throwable;

/**
 * Admin config + Docker guide for the Player Counter plugin. Reached via the
 * Plugins page "Manage" button (manage_url = /admin/player-counter-settings),
 * hidden from the sidebar. Settings persist to the `plugins.settings` JSON
 * column; the sidecar token is encrypted by PlayerCounterSettings. Header
 * actions expose the bilingual setup guide and a sidecar reachability probe.
 */
class PlayerCounterSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $slug = 'player-counter-settings';

    protected string $view = 'peregrine-player-counter::pages.settings';

    private const NS = 'peregrine-player-counter::messages.settings.';

    public bool $enabled = false;

    public ?string $sidecar_url = 'http://127.0.0.1:9899';

    public ?string $sidecar_token = '';

    /** @var list<int> */
    public array $egg_whitelist = [];

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
        $settings = PlayerCounterSettings::make();

        $this->enabled = $settings->enabled;
        $this->sidecar_url = $settings->sidecarUrl !== '' ? $settings->sidecarUrl : 'http://127.0.0.1:9899';
        $this->sidecar_token = $settings->sidecarToken;
        $this->egg_whitelist = $settings->eggWhitelist;

        $this->form->fill([
            'enabled' => $this->enabled,
            'sidecar_url' => $this->sidecar_url,
            'sidecar_token' => $this->sidecar_token,
            'egg_whitelist' => $this->egg_whitelist,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(PlayerCounterFormSchema::sections());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $store = app(PluginSettings::class);
        $id = PlayerCounterServiceProvider::PLUGIN_ID;

        $store->setSetting($id, 'enabled', (bool) ($data['enabled'] ?? false));
        $store->setSetting($id, 'sidecar_url', rtrim((string) ($data['sidecar_url'] ?? ''), '/'));
        $store->setSetting($id, 'egg_whitelist', PlayerCounterSettings::normalizeEggIds($data['egg_whitelist'] ?? []));
        PlayerCounterSettings::storeToken((string) ($data['sidecar_token'] ?? ''));

        Notification::make()->title(__(self::NS.'saved'))->success()->send();
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [$this->guideAction(), $this->reachabilityAction()];
    }

    private function guideAction(): Action
    {
        return Action::make('guide')
            ->label(__(self::NS.'guide'))
            ->icon('heroicon-o-book-open')
            ->color('gray')
            ->modalHeading(__(self::NS.'guide'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (): View => view('peregrine-player-counter::guide', [
                'ctx' => app(PlayerCounterDocRenderer::class)->context(PlayerCounterSettings::make()),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__(self::NS.'close'));
    }

    private function reachabilityAction(): Action
    {
        return Action::make('reachability')
            ->label(__(self::NS.'test'))
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function (): void {
                $settings = PlayerCounterSettings::make();
                $url = $settings->sidecarUrl;

                if ($url === '') {
                    Notification::make()->title(__(self::NS.'test_no_url'))->warning()->send();

                    return;
                }

                try {
                    $request = Http::timeout(5)->acceptJson();
                    if ($settings->sidecarToken !== '') {
                        $request = $request->withToken($settings->sidecarToken);
                    }
                    $response = $request->get($url.'/healthz');

                    $response->successful()
                        ? Notification::make()->title(__(self::NS.'test_ok'))->success()->send()
                        : Notification::make()->title(__(self::NS.'test_fail'))->body('HTTP '.$response->status())->danger()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__(self::NS.'test_fail'))->body($e->getMessage())->danger()->send();
                }
            });
    }
}
