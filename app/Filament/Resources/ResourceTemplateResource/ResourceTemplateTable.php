<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResourceTemplateResource;

use App\Actions\Admin\DuplicateResourceTemplateAction;
use App\Filament\Resources\ResourceTemplateResource;
use App\Models\ResourceTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Table for `ResourceTemplateResource`. Mirror of
 * `ServerConfigurationTable` — same column shape (id, name, key specs,
 * usage count) and the same Duplicate row action + bulk action so admins
 * get a consistent admin UX across both entities.
 *
 * Delete behaviour : the FK on `server_configurations.resource_template_id`
 * is `nullOnDelete`, so removing a template detaches every bound
 * configuration (which then surfaces in the configurations table as
 * "needs_config"). The delete confirmation modal warns when this would
 * orphan rows.
 */
final class ResourceTemplateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->recordActions(self::recordActions())
            ->toolbarActions(self::toolbarActions())
            ->defaultSort('id', 'desc');
    }

    /** @return array<int, mixed> */
    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')->label(__('admin/_shell.fields.id'))->sortable(),
            Tables\Columns\TextColumn::make('name')
                ->label(__('admin/resource_templates.fields.name'))
                ->searchable()->sortable(),
            Tables\Columns\TextColumn::make('ram')
                ->label(__('admin/resource_templates.fields.ram'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state).' MB')
                ->sortable(),
            Tables\Columns\TextColumn::make('cpu')
                ->label(__('admin/resource_templates.fields.cpu'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : $state.' %')
                ->sortable(),
            Tables\Columns\TextColumn::make('disk')
                ->label(__('admin/resource_templates.fields.disk'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state).' MB')
                ->sortable(),
            Tables\Columns\TextColumn::make('serverConfigurations_count')
                ->label(__('admin/resource_templates.fields.usage'))
                ->counts('serverConfigurations')
                ->sortable()
                ->badge(),
        ];
    }

    /** @return array<int, mixed> */
    private static function recordActions(): array
    {
        return [
            EditAction::make(),
            self::duplicateAction(),
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading(fn (ResourceTemplate $record): string => __(
                    'admin/resource_templates.delete.modal_heading',
                    ['name' => $record->name]
                ))
                ->modalDescription(function (ResourceTemplate $record): string {
                    $count = $record->serverConfigurations()->count();
                    $base = $count === 0
                        ? __('admin/resource_templates.delete.no_configs')
                        : __('admin/resource_templates.delete.with_configs', ['count' => $count]);
                    return $base.' '.__('admin/resource_templates.delete.irreversible');
                })
                ->modalSubmitActionLabel(__('admin/resource_templates.delete.submit')),
        ];
    }

    /** @return array<int, mixed> */
    private static function toolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                self::duplicateBulkAction(),
                DeleteBulkAction::make()->requiresConfirmation(),
            ]),
        ];
    }

    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label(__('admin/resource_templates.duplicate.label'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (ResourceTemplate $record): string => __(
                'admin/resource_templates.duplicate.modal_heading',
                ['name' => $record->name]
            ))
            ->modalDescription(__('admin/resource_templates.duplicate.modal_description'))
            ->modalSubmitActionLabel(__('admin/resource_templates.duplicate.submit'))
            ->action(function (ResourceTemplate $record) {
                $clone = app(DuplicateResourceTemplateAction::class)($record);

                Notification::make()
                    ->title(__('admin/resource_templates.duplicate.notification_title'))
                    ->body(__('admin/resource_templates.duplicate.notification_body', [
                        'name' => $clone->name,
                    ]))
                    ->success()
                    ->send();

                return redirect()->to(ResourceTemplateResource::getUrl('edit', ['record' => $clone]));
            });
    }

    private static function duplicateBulkAction(): BulkAction
    {
        return BulkAction::make('duplicate')
            ->label(__('admin/resource_templates.duplicate_bulk.label'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin/resource_templates.duplicate_bulk.modal_heading'))
            ->modalDescription(fn ($records): string => __(
                'admin/resource_templates.duplicate_bulk.modal_description',
                ['count' => $records->count()]
            ))
            ->modalSubmitActionLabel(__('admin/resource_templates.duplicate_bulk.submit'))
            ->action(function ($records) {
                $duplicator = app(DuplicateResourceTemplateAction::class);
                $count = 0;
                foreach ($records as $record) {
                    $duplicator($record);
                    $count++;
                }
                Notification::make()
                    ->title(__('admin/resource_templates.duplicate_bulk.notification_title'))
                    ->body(__('admin/resource_templates.duplicate_bulk.notification_body', ['count' => $count]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
