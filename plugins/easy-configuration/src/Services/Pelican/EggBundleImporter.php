<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Pelican;

use App\Services\Pelican\PelicanHttpClient;
use App\Services\Sync\InfrastructureSync;
use Illuminate\Http\Client\RequestException;

/**
 * Pushes a template's bundled egg JSON into Pelican through the Application
 * API. Pelican's `POST /api/application/eggs/import` upserts by the egg's
 * embedded `uuid`: the first import creates the egg, re-importing the same
 * bundle overwrites the existing one — which is exactly the "import once,
 * update on re-import" contract the admin button promises. After the import
 * the local egg mirror is re-synced so the fresh egg is immediately usable
 * (server cards, template target_eggs, pickers…).
 */
final class EggBundleImporter
{
    public function __construct(
        private readonly PelicanHttpClient $http,
        private readonly InfrastructureSync $sync,
    ) {}

    /**
     * @return array{pelican_egg_id: int|null, updated: bool}
     *
     * @throws RequestException
     */
    public function import(string $eggJson): array
    {
        $uuid = (string) (json_decode($eggJson, true)['uuid'] ?? '');
        $existedBefore = $uuid !== '' && $this->uuidExists($uuid);

        $response = $this->http->request()
            ->withBody($eggJson, 'application/json')
            ->post('/api/application/eggs/import')
            ->throw();

        $pelicanEggId = (int) ($response->json('attributes.id') ?? 0);

        $this->sync->syncEggs();

        return [
            'pelican_egg_id' => $pelicanEggId > 0 ? $pelicanEggId : null,
            'updated' => $existedBefore,
        ];
    }

    /** @throws RequestException */
    private function uuidExists(string $uuid): bool
    {
        $page = 1;
        do {
            $json = $this->http->request()
                ->get('/api/application/eggs', ['page' => $page])
                ->throw()
                ->json();

            foreach ((array) ($json['data'] ?? []) as $item) {
                if (($item['attributes']['uuid'] ?? null) === $uuid) {
                    return true;
                }
            }

            $totalPages = (int) ($json['meta']['pagination']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return false;
    }
}
