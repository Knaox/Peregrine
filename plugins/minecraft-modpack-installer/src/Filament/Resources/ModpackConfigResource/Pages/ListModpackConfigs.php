<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource;
use Plugins\MinecraftModpackInstaller\Models\ModpackConfig;

/**
 * Singleton list page — there's only ever one row, so we redirect straight
 * to its edit screen on mount. The Filament list UI never actually renders.
 * Mirrors `ark-mods-installer`'s ListArkModsConfigs.
 */
class ListModpackConfigs extends ListRecords
{
    protected static string $resource = ModpackConfigResource::class;

    public function mount(): void
    {
        parent::mount();
        $config = ModpackConfig::current();
        $this->redirect(ModpackConfigResource::getUrl('edit', ['record' => $config->id]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
