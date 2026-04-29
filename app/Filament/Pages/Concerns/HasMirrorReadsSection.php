<?php

namespace App\Filament\Pages\Concerns;

use App\Jobs\Mirror\EnableLocalDbReadJob;
use App\Models\MirrorBackfillProgress;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

/**
 * Drop-in section for "Activer la lecture DB locale" — used by
 * `App\Filament\Pages\PelicanWebhookSettings` (and any future page that
 * wants the same control).
 *
 * Replaces the legacy plain toggle on `mirror_reads_enabled` with a
 * stateful UX :
 *   - Désactivé : bouton "Activer la lecture DB locale" qui dispatch
 *     EnableLocalDbReadJob (le job lui-même flippe le flag à la fin si
 *     le backfill est complet sans erreur).
 *   - En cours  : spinner + dernier report en lecture seule, bouton
 *     désactivé pour empêcher les double-clics.
 *   - Activé    : bouton "Désactiver" qui flippe juste le flag, sans
 *     toucher aux tables miroir (l'admin peut réactiver instantanément).
 *   - Échec     : badge rouge + extrait d'erreur + bouton "Réessayer".
 *
 * La page hôte reste responsable de poller (Livewire `wire:poll.5s` côté
 * vue) pour rafraîchir l'affichage pendant qu'un backfill tourne.
 */
trait HasMirrorReadsSection
{
    public function enableMirrorReads(): void
    {
        if (MirrorBackfillProgress::isAnyRunning()) {
            Notification::make()
                ->title(__('admin.mirror_reads.notifications.already_running'))
                ->warning()
                ->send();

            return;
        }

        $progress = MirrorBackfillProgress::startNew();
        EnableLocalDbReadJob::dispatch($progress->id);

        Notification::make()
            ->title(__('admin.mirror_reads.notifications.dispatched_title'))
            ->body(__('admin.mirror_reads.notifications.dispatched_body'))
            ->success()
            ->send();
    }

    public function disableMirrorReads(): void
    {
        app(SettingsService::class)->set('mirror_reads_enabled', 'false');

        Notification::make()
            ->title(__('admin.mirror_reads.notifications.disabled'))
            ->success()
            ->send();
    }

    protected function mirrorReadsSection(): Section
    {
        $latest = MirrorBackfillProgress::latest();
        $isEnabled = $this->isMirrorReadsEnabled();
        $isRunning = $latest?->isRunning() === true;

        return Section::make(__('admin.mirror_reads.section_title'))
            ->description(__('admin.mirror_reads.section_description'))
            ->icon('heroicon-o-circle-stack')
            ->schema([
                Placeholder::make('mirror_reads_state')
                    ->label(__('admin.mirror_reads.fields.current_state'))
                    ->content($this->renderStateBadge($isEnabled, $latest)),
            ])
            ->headerActions([
                Action::make('enableMirrorReads')
                    ->label($latest?->isFailed()
                        ? __('admin.mirror_reads.actions.retry')
                        : __('admin.mirror_reads.actions.enable'))
                    ->icon('heroicon-o-bolt')
                    ->color('primary')
                    ->visible(! $isEnabled && ! $isRunning)
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.mirror_reads.modals.enable_description'))
                    ->action(fn () => $this->enableMirrorReads()),

                Action::make('disableMirrorReads')
                    ->label(__('admin.mirror_reads.actions.disable'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible($isEnabled && ! $isRunning)
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.mirror_reads.modals.disable_description'))
                    ->action(fn () => $this->disableMirrorReads()),
            ]);
    }

    private function isMirrorReadsEnabled(): bool
    {
        $value = (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false');

        return $value === 'true' || $value === '1';
    }

    private function renderStateBadge(bool $isEnabled, ?MirrorBackfillProgress $latest): HtmlString
    {
        if ($latest?->isRunning() === true) {
            $label = __('admin.mirror_reads.state.running');
            $color = '#fbbf24';
        } elseif ($isEnabled) {
            $label = __('admin.mirror_reads.state.active');
            $color = '#34d399';
        } elseif ($latest?->isFailed() === true) {
            $label = __('admin.mirror_reads.state.failed');
            $color = '#f87171';
        } else {
            $label = __('admin.mirror_reads.state.disabled');
            $color = 'rgba(255,255,255,0.5)';
        }

        $detail = '';
        if ($latest !== null) {
            $detail = '<p style="margin-top: 0.25rem; font-size: 0.75rem; color: rgba(255,255,255,0.5);">'
                .e(__('admin.mirror_reads.state.last_run', [
                    'date' => $latest->started_at?->diffForHumans() ?? '—',
                ]))
                .'</p>';

            if ($latest->isFailed() && $latest->error !== null) {
                $detail .= '<p style="margin-top: 0.25rem; font-size: 0.75rem; color: #f87171;">'
                    .e($latest->error).'</p>';
            }
        }

        return new HtmlString(
            '<span style="display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; '
            .'background: '.$color.'33; color: '.$color.'; font-size: 0.75rem; font-weight: 500;">'
            .e($label).'</span>'.$detail
        );
    }
}
