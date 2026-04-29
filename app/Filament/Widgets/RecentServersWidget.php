<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentServersWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('admin.widgets.recent_servers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Server::query()
                    ->with(['user'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('admin.fields.owner')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        $key = 'admin.statuses.'.$state;
                        $tr = __($key);
                        return $tr === $key ? $state : $tr;
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'running' => 'success',
                        'stopped' => 'warning',
                        'suspended', 'terminated' => 'danger',
                        'offline' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
