<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource;

/**
 * Edit page for the singleton modpack config. Adds the "Import egg" header
 * action (pushes the bundled installer egg into Pelican) and busts the
 * manifest enricher cache after each save so sidebar tab visibility +
 * route_suffix reflect the new settings on the very next page load.
 */
class EditModpackConfig extends EditRecord
{
    protected static string $resource = ModpackConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModpackConfigResource::importEggAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Never expose the stored CurseForge key in the UI — admin types
        // a new value to replace it. Empty string means "keep existing".
        $data['curseforge_api_key'] = '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Treat blank input as "leave unchanged" so the admin doesn't have
        // to retype the CurseForge key on every save.
        if (! isset($data['curseforge_api_key']) || $data['curseforge_api_key'] === '') {
            unset($data['curseforge_api_key']);
        }

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('minecraft-modpack-installer::admin.notifications.saved'))
            ->success();
    }

    protected function afterSave(): void
    {
        // Sidebar tab visibility (requires_egg_ids) + route_suffix come
        // from the manifest enricher which caches its read of this row
        // for 60s; bust both keys so the change is instant.
        Cache::forget('modpack_settings.whitelisted_egg_ids');
        Cache::forget('modpack_settings.page_route');
        Cache::forget('modpack_settings.page_label');
    }
}
