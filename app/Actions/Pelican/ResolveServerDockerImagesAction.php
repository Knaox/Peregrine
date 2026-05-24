<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Models\Server;
use App\Services\Pelican\JavaImageMatcher;
use App\Services\Pelican\PelicanApplicationService;

/**
 * Build the Docker-image picker shown by the console quick-fix when a
 * Minecraft server reports an incompatible Java version. Reads the egg's
 * docker_images map (+ yolks fallback) and flags the current + recommended
 * images.
 */
final readonly class ResolveServerDockerImagesAction
{
    public function __construct(
        private PelicanApplicationService $pelican,
        private JavaImageMatcher $matcher,
    ) {}

    /**
     * @return array{current: string|null, images: list<array{label: string, image: string, java_major: int|null, is_recommended: bool, is_current: bool}>}
     */
    public function __invoke(Server $server, ?int $requiredJava = null): array
    {
        $eggImages = $server->egg !== null
            ? $this->pelican->getEggDockerImages($server->egg->pelican_egg_id)
            : [];

        $current = null;
        if ($server->pelican_server_id !== null && $server->pelican_server_id > 0) {
            $container = $this->pelican->getServerContainer($server->pelican_server_id);
            $current = $container['image'] !== '' ? $container['image'] : null;

            // Some Pelican builds only expose docker_images on the server's egg
            // relationship — use it when the direct egg lookup came back empty.
            if ($eggImages === [] && $container['egg_docker_images'] !== []) {
                $eggImages = $container['egg_docker_images'];
            }
        }

        $images = [];
        foreach ($this->matcher->catalog($eggImages, $requiredJava) as $item) {
            $images[] = [
                ...$item,
                'is_current' => $current !== null && $item['image'] === $current,
            ];
        }

        return ['current' => $current, 'images' => $images];
    }
}
