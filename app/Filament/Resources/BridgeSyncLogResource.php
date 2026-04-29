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
 * when Bridge is disabled (matches ServerPlanResource gating).
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
        return __('admin.resources.bridge_sync_logs.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.bridge_sync_logs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.bridge_sync_logs.plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();
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
                    ->label('When'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'upsert' ? 'info' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop_plan_id')
                    ->label('Shop ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('serverPlan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        $state >= 200 && $state < 300 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('signature_valid')
                    ->label('HMAC')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(['upsert' => 'Upsert', 'delete' => 'Delete']),
                Tables\Filters\TernaryFilter::make('signature_valid')->label('Signature valid'),
                Tables\Filters\SelectFilter::make('response_status')
                    ->label('HTTP outcome')
                    ->options([
                        '2xx' => '2xx — success',
                        '4xx' => '4xx — client error',
                        '5xx' => '5xx — server error',
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
                    ->label(__('admin.common.view_payload'))
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(__('admin.common.payload_modal_title'))
                    ->modalContent(fn (BridgeSyncLog $record): HtmlString => new HtmlString(
                        '<div class="space-y-3 text-xs">'
                        . '<div><div class="font-semibold mb-1">Request</div>'
                        . '<pre class="bg-gray-50 dark:bg-gray-900 p-3 rounded-md overflow-auto max-h-[30vh] whitespace-pre-wrap break-all">'
                        . e(is_array($record->request_payload) ? json_encode($record->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $record->request_payload)
                        . '</pre></div>'
                        . '<div><div class="font-semibold mb-1">Response (HTTP ' . e((string) $record->response_status) . ')</div>'
                        . '<pre class="bg-gray-50 dark:bg-gray-900 p-3 rounded-md overflow-auto max-h-[30vh] whitespace-pre-wrap break-all">'
                        . e((string) ($record->response_body ?? '—'))
                        . '</pre></div>'
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([])
            ->defaultSort('attempted_at', 'desc')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading(__('admin.resources.bridge_sync_logs.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.logs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBridgeSyncLogs::route('/'),
        ];
    }
}
