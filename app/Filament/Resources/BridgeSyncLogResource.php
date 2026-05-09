<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BridgeSyncLogResource\Pages;
use App\Models\BridgeSyncLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Read-only audit page for inbound Bridge calls from the Shop. Each row
 * captures one HTTP exchange (signature outcome, payload, response). Hidden
 * when no Stripe webhook is configured AND no shop is active.
 */
class BridgeSyncLogResource extends Resource
{
    protected static ?string $model = BridgeSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/logs.bridge.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/logs.bridge.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/logs.bridge.resource.plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Show the audit log when either Stripe is wired (legacy plan-sync
        // calls still surface here) or there's at least one active shop in
        // the multi-shop registry.
        $integrations = app(\App\Services\Integrations\IntegrationStatusService::class);
        return $integrations->hasStripeConfigured() || $integrations->hasActiveShop();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attempted_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('admin/_shell.fields.when')),
                Tables\Columns\TextColumn::make('action')
                    ->label(__('admin/_shell.fields.event_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin/_shell.statuses.'.$state))
                    ->color(fn (string $state): string => $state === 'upsert' ? 'info' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop_plan_id')
                    ->label(__('admin/_shell.fields.shop_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('serverPlan.name')
                    ->label(__('admin/_shell.fields.plan'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('response_status')
                    ->label(__('admin/_shell.fields.http'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        $state >= 200 && $state < 300 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('signature_valid')
                    ->label(__('admin/_shell.fields.hmac'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('admin/_shell.fields.ip'))
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label(__('admin/_shell.fields.event_type'))
                    ->options(['upsert' => __('admin/_shell.statuses.upsert'), 'delete' => __('admin/_shell.statuses.delete')]),
                Tables\Filters\TernaryFilter::make('signature_valid')->label(__('admin/_shell.fields.signature_valid')),
                Tables\Filters\SelectFilter::make('response_status')
                    ->label(__('admin/_shell.fields.http_outcome'))
                    ->options([
                        '2xx' => __('admin/_shell.http_filters.success'),
                        '4xx' => __('admin/_shell.http_filters.client_error'),
                        '5xx' => __('admin/_shell.http_filters.server_error'),
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        return match ($value) {
                            '2xx' => $query->whereBetween('response_status', [200, 299]),
                            '4xx' => $query->whereBetween('response_status', [400, 499]),
                            '5xx' => $query->whereBetween('response_status', [500, 599]),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('viewPayload')
                    ->label(__('admin/_shell.common.view_payload'))
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(__('admin/_shell.common.payload_modal_title'))
                    ->modalContent(fn (BridgeSyncLog $record): HtmlString => new HtmlString(
                        '<div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.75rem;">'
                        . '<div><div style="font-weight: 600; margin-bottom: 0.25rem;">'.e(__('admin/_shell.common.request')).'</div>'
                        . '<pre style="background: rgba(0,0,0,0.4); padding: 0.75rem; border-radius: 0.375rem; overflow: auto; max-height: 30vh; white-space: pre-wrap; word-break: break-all;">'
                        . e(is_array($record->request_payload) ? json_encode($record->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $record->request_payload)
                        . '</pre></div>'
                        . '<div><div style="font-weight: 600; margin-bottom: 0.25rem;">'.e(__('admin/_shell.common.response')).' (HTTP '.e((string) $record->response_status).')</div>'
                        . '<pre style="background: rgba(0,0,0,0.4); padding: 0.75rem; border-radius: 0.375rem; overflow: auto; max-height: 30vh; white-space: pre-wrap; word-break: break-all;">'
                        . e((string) ($record->response_body ?? '—'))
                        . '</pre></div>'
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin/_shell.common.close')),
            ])
            ->toolbarActions([])
            ->defaultSort('attempted_at', 'desc')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading(__('admin/logs.bridge.resource.plural'))
            ->emptyStateDescription(__('admin/_shell.common.empty_states.logs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBridgeSyncLogs::route('/'),
        ];
    }
}
