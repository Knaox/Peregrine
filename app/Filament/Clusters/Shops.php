<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

/**
 * Bundles every shop-side concern under a single sidebar entry :
 *  - the `ShopResource` itself (CRUD shops, API keys, status)
 *  - the `ServerConfigurationResource` (the technical catalog)
 *  - the `ResourceTemplateResource` (reusable spec bundles)
 *
 * Filament renders this as a top-level "Shops" item with a sub-
 * navigation listing the three resources, mirroring the admin
 * pattern used elsewhere (e.g. Settings). Each resource keeps its own
 * route ; the cluster only affects navigation rendering.
 */
class Shops extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 5;

    protected static ?string $clusterBreadcrumb = null;

    public static function getNavigationLabel(): string
    {
        return __('admin/_shell.navigation.shops.cluster');
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // top-level item, no group
    }
}
