<?php

namespace App\Filament\Widgets;

use App\Enums\BridgeMode;
use App\Models\PelicanProcessedEvent;
use App\Services\Bridge\BridgeModeService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Surface the latest Pelican webhook events on the dashboard so admins
 * notice install-completion failures or auth issues without opening the
 * full audit log.
 *
 * Hidden when the Pelican webhook receiver is disabled — no events to show.
 */
class RecentWebhooksWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $value = (string) app(\App\Services\SettingsService::class)
            ->get('pelican_webhook_enabled', 'false');
        return $value === 'true' || $value === '1';
    }

    public function getHeading(): string
    {
        return __('admin.widgets.recent_webhooks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PelicanProcessedEvent::query()
                    ->orderByDesc('processed_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('When')
                    ->dateTime()
                    ->since(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('pelican_model_id')
                    ->label('Pelican ID')
                    ->numeric(),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        $state >= 200 && $state < 300 => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn ($state): ?string => $state),
            ])
            ->paginated(false);
    }
}
