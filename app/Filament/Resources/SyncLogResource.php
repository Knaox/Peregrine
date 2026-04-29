<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncLogResource\Pages;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use BackedEnum;
use UnitEnum;

class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.sync_logs.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.sync_logs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.sync_logs.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'success' => 'success',
                        'running', 'in_progress' => 'warning',
                        'failed', 'error' => 'danger',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Summary')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }
                        $first = collect($state)->take(2)->map(fn ($v, $k) => "{$k}: " . (is_scalar($v) ? $v : json_encode($v)))->implode(' • ');
                        return $first ?: '—';
                    })
                    ->limit(60)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function (SyncLog $record): ?string {
                        if (! $record->started_at || ! $record->completed_at) {
                            return null;
                        }

                        $seconds = $record->completed_at->diffInSeconds($record->started_at);

                        if ($seconds < 60) {
                            return $seconds . 's';
                        }

                        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
                    })
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(fn () => SyncLog::query()
                        ->distinct()
                        ->pluck('type', 'type')
                        ->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                Action::make('viewPayload')
                    ->label(__('admin.common.view_payload'))
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(__('admin.common.payload_modal_title'))
                    ->modalContent(fn (SyncLog $record): HtmlString => new HtmlString(
                        '<pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-md overflow-auto max-h-[60vh] whitespace-pre-wrap break-all">'
                        . e(is_array($record->summary) ? json_encode($record->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $record->summary)
                        . '</pre>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->emptyStateHeading(__('admin.resources.sync_logs.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.logs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncLogs::route('/'),
        ];
    }
}
