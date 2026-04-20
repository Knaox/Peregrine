<?php

namespace App\Filament\Actions;

use App\Services\ResourceDeletionService;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Factories for Peregrine's 3-way delete (Peregrine / Pelican / Both).
 *
 * Used by UserResource, ServerResource, EggResource, NodeResource so the
 * admin can explicitly choose whether to drop the local row, the remote
 * Pelican record, or both. Defaults to 'both'.
 */
final class ResourceDeleteAction
{
    /**
     * Row-level delete action.
     */
    public static function row(?string $cascadeWarning = null): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading('Delete resource')
            ->modalDescription(static::description($cascadeWarning))
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel('Delete')
            ->schema(static::strategySchema())
            ->fillForm(fn (): array => ['strategy' => ResourceDeletionService::STRATEGY_BOTH])
            ->action(function (Model $record, array $data): void {
                static::performDelete([$record], $data['strategy'] ?? ResourceDeletionService::STRATEGY_BOTH);
            });
    }

    /**
     * Bulk delete action — same 3-way strategy applied to every selected row.
     */
    public static function bulk(?string $cascadeWarning = null): BulkAction
    {
        return BulkAction::make('delete')
            ->label('Delete selected')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected resources')
            ->modalDescription(static::description($cascadeWarning))
            ->modalSubmitActionLabel('Delete')
            ->schema(static::strategySchema())
            ->fillForm(fn (): array => ['strategy' => ResourceDeletionService::STRATEGY_BOTH])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                static::performDelete($records->all(), $data['strategy'] ?? ResourceDeletionService::STRATEGY_BOTH);
            });
    }

    /**
     * @return array<Radio>
     */
    private static function strategySchema(): array
    {
        return [
            Radio::make('strategy')
                ->label('Deletion target')
                ->options([
                    ResourceDeletionService::STRATEGY_PEREGRINE => 'Peregrine only (keep on Pelican)',
                    ResourceDeletionService::STRATEGY_PELICAN => 'Pelican only (keep local record)',
                    ResourceDeletionService::STRATEGY_BOTH => 'Both sides (delete everywhere)',
                ])
                ->default(ResourceDeletionService::STRATEGY_BOTH)
                ->required(),
        ];
    }

    private static function description(?string $cascadeWarning): string
    {
        $base = 'Choose where this resource should be removed. Peregrine-only leaves the Pelican record intact, Pelican-only removes it remotely while keeping the local sync data.';

        return $cascadeWarning
            ? $base . "\n\n" . $cascadeWarning
            : $base;
    }

    /**
     * @param  array<Model>  $records
     */
    private static function performDelete(array $records, string $strategy): void
    {
        $service = app(ResourceDeletionService::class);
        $ok = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                $service->delete($record, $strategy);
                $ok++;
            } catch (RequestException $e) {
                $failed++;
                report($e);
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        }

        if ($failed === 0) {
            Notification::make()
                ->title($ok === 1 ? 'Resource deleted' : "{$ok} resources deleted")
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title("Deleted {$ok} — failed {$failed}")
            ->body('Some deletions failed (see logs). Local records for failed items were preserved.')
            ->danger()
            ->send();
    }
}
