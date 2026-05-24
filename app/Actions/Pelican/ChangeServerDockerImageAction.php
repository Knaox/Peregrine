<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Exceptions\ImageNotAllowedException;
use App\Models\Server;
use App\Services\Pelican\JavaImageMatcher;
use App\Services\Pelican\PelicanApplicationService;
use RuntimeException;

/**
 * Switch a server's Docker image (Java version) from the console quick-fix and
 * power-cycle it. The requested image is validated against the egg's allowed
 * set (+ yolks fallback) so a caller can't inject an arbitrary image onto the
 * node.
 *
 * The image only takes effect once Wings recreates the container, so we use a
 * hard kill → wait-offline → start (see RestartServerCleanlyAction) rather than
 * a soft restart.
 */
final readonly class ChangeServerDockerImageAction
{
    public function __construct(
        private PelicanApplicationService $pelican,
        private JavaImageMatcher $matcher,
        private RestartServerCleanlyAction $restart,
    ) {}

    /**
     * @throws ImageNotAllowedException when $image is not in the allowed set
     */
    public function __invoke(Server $server, string $image): void
    {
        $image = trim($image);

        if ($server->pelican_server_id === null || $server->pelican_server_id <= 0) {
            throw new RuntimeException('Server is not linked to Pelican yet.');
        }

        if (! in_array($image, $this->matcher->allowedImages($this->eggImages($server)), true)) {
            throw new ImageNotAllowedException($image);
        }

        $this->pelican->updateServerStartupImage($server->pelican_server_id, $image);
        ($this->restart)($server);
    }

    /** @return array<string, string> */
    private function eggImages(Server $server): array
    {
        $eggImages = $server->egg !== null
            ? $this->pelican->getEggDockerImages($server->egg->pelican_egg_id)
            : [];

        if ($eggImages === [] && $server->pelican_server_id !== null && $server->pelican_server_id > 0) {
            $eggImages = $this->pelican->getServerContainer($server->pelican_server_id)['egg_docker_images'];
        }

        return $eggImages;
    }
}
