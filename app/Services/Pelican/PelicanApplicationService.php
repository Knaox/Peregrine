<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\CreateServerRequest;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\DTOs\PelicanEgg;
use App\Services\Pelican\DTOs\PelicanNode;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\PelicanUser;
use Illuminate\Http\Client\RequestException;

/**
 * Façade preserving the original PelicanApplicationService public API.
 *
 * Internally delegates to PelicanUserClient (users + passwords) and
 * PelicanInfrastructureClient (servers, nodes, eggs). Both share a
 * PelicanHttpClient helper for auth + pagination.
 *
 * The 15 existing call sites (SyncService, ServerService,
 * ResourceDeletionService, UserController, UserResource, OAuthController)
 * continue using `app(PelicanApplicationService::class)->method(...)`
 * with no change.
 */
class PelicanApplicationService
{
    public function __construct(
        private PelicanUserClient $users,
        private PelicanInfrastructureClient $infra,
    ) {}

    // Users ---------------------------------------------------------------

    /** @throws RequestException */
    public function createUser(string $email, string $username, string $name): PelicanUser
    {
        return $this->users->createUser($email, $username, $name);
    }

    /** @throws RequestException */
    public function deleteUser(int $pelicanUserId): void
    {
        $this->users->deleteUser($pelicanUserId);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    public function updateUser(int $pelicanUserId, array $data): PelicanUser
    {
        return $this->users->updateUser($pelicanUserId, $data);
    }

    /** @throws RequestException */
    public function changeUserEmail(int $pelicanUserId, string $newEmail): PelicanUser
    {
        return $this->users->changeUserEmail($pelicanUserId, $newEmail);
    }

    /** @throws RequestException */
    public function getUser(int $pelicanUserId): PelicanUser
    {
        return $this->users->getUser($pelicanUserId);
    }

    /**
     * @return PelicanUser[]
     *
     * @throws RequestException
     */
    public function listUsers(): array
    {
        return $this->users->listUsers();
    }

    /** @throws RequestException */
    public function findUserByEmail(string $email): ?PelicanUser
    {
        return $this->users->findUserByEmail($email);
    }

    /** @throws RequestException */
    public function updateUserPassword(int $pelicanUserId, string $password): void
    {
        $this->users->updateUserPassword($pelicanUserId, $password);
    }

    // Servers -------------------------------------------------------------

    /** @throws RequestException */
    public function createServer(
        int $userId,
        int $eggId,
        int $nestId,
        int $ram,
        int $cpu,
        int $disk,
        int $nodeId,
        string $name,
    ): PelicanServer {
        return $this->infra->createServer($userId, $eggId, $nestId, $ram, $cpu, $disk, $nodeId, $name);
    }

    /** @throws RequestException */
    public function createServerAdvanced(CreateServerRequest $request): PelicanServer
    {
        return $this->infra->createServerAdvanced($request);
    }

    /**
     * @param  array<string, mixed>  $build
     *
     * @throws RequestException
     */
    public function updateServerBuild(int $pelicanServerId, array $build): PelicanServer
    {
        return $this->infra->updateServerBuild($pelicanServerId, $build);
    }

    /** @throws RequestException */
    public function suspendServer(int $pelicanServerId): void
    {
        $this->infra->suspendServer($pelicanServerId);
    }

    /** @throws RequestException */
    public function unsuspendServer(int $pelicanServerId): void
    {
        $this->infra->unsuspendServer($pelicanServerId);
    }

    /** @throws RequestException */
    public function deleteServer(int $pelicanServerId): void
    {
        $this->infra->deleteServer($pelicanServerId);
    }

    /** @throws RequestException */
    public function getServer(int $pelicanServerId): PelicanServer
    {
        return $this->infra->getServer($pelicanServerId);
    }

    /**
     * @return PelicanServer[]
     *
     * @throws RequestException
     */
    public function listServers(?int $userId = null): array
    {
        return $this->infra->listServers($userId);
    }

    // Nodes ---------------------------------------------------------------

    /**
     * @return PelicanNode[]
     *
     * @throws RequestException
     */
    public function listNodes(): array
    {
        return $this->infra->listNodes();
    }

    /** @throws RequestException */
    public function getNode(int $nodeId): PelicanNode
    {
        return $this->infra->getNode($nodeId);
    }

    /**
     * @return PelicanAllocation[]
     *
     * @throws RequestException
     */
    public function listNodeAllocations(int $nodeId): array
    {
        return $this->infra->listNodeAllocations($nodeId);
    }

    /** @throws RequestException */
    public function deleteNode(int $pelicanNodeId): void
    {
        $this->infra->deleteNode($pelicanNodeId);
    }

    // Eggs ----------------------------------------------------------------

    /**
     * @return PelicanEgg[]
     *
     * @throws RequestException
     */
    public function listEggs(): array
    {
        return $this->infra->listEggs();
    }

    /** @throws RequestException */
    public function getEgg(int $eggId): PelicanEgg
    {
        return $this->infra->getEgg($eggId);
    }

    /** @throws RequestException */
    public function deleteEgg(int $pelicanEggId): void
    {
        $this->infra->deleteEgg($pelicanEggId);
    }

    /**
     * @return array<string, scalar|null>
     *
     * @throws RequestException
     */
    public function getEggVariableDefaults(int $eggId): array
    {
        return $this->infra->getEggVariableDefaults($eggId);
    }
}
