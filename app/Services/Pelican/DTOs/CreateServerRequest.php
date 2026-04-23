<?php

namespace App\Services\Pelican\DTOs;

/**
 * Structured input for PelicanInfrastructureClient::createServerAdvanced().
 *
 * The legacy createServer() method only exposed name/user/egg/nest/ram/cpu/
 * disk/node — far too narrow for Bridge provisioning which needs full control
 * over swap, io, oom, environment, allocation, feature limits, etc. This DTO
 * carries the complete shape of the Pelican Application API
 * POST /api/application/servers payload.
 *
 * Use Pelican-side defaults sentinel : pass null for `dockerImage` or
 * `startup` to let Pelican use the egg's default.
 */
final readonly class CreateServerRequest
{
    /**
     * @param  array<string, scalar>  $environment    Pelican egg env vars (UPPER_SNAKE_CASE keys)
     * @param  array<int>             $additionalAllocations Additional allocation IDs (besides the default)
     */
    public function __construct(
        public string $name,
        public int $userId,
        public int $eggId,
        public int $nestId,
        public int $memoryMb,
        public int $swapMb,
        public int $diskMb,
        public int $ioWeight,
        public int $cpuPercent,
        public int $featureLimitDatabases,
        public int $featureLimitBackups,
        public int $featureLimitAllocations,
        public array $environment,
        public int $defaultAllocationId,
        public array $additionalAllocations,
        public ?string $dockerImage,
        public ?string $startup,
        public bool $startOnCompletion,
        public bool $skipScripts,
        public bool $oomDisabled,
    ) {}

    /**
     * Build the array payload for the Pelican API.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return [
            'name' => $this->name,
            'user' => $this->userId,
            'egg' => $this->eggId,
            'nest' => $this->nestId,
            'docker_image' => $this->dockerImage ?? '~',
            'startup' => $this->startup ?? '~',
            'environment' => (object) $this->environment,
            'limits' => [
                'memory' => $this->memoryMb,
                'swap' => $this->swapMb,
                'disk' => $this->diskMb,
                'io' => $this->ioWeight,
                'cpu' => $this->cpuPercent,
                'oom_disabled' => $this->oomDisabled,
            ],
            'feature_limits' => [
                'databases' => $this->featureLimitDatabases,
                'backups' => $this->featureLimitBackups,
                'allocations' => $this->featureLimitAllocations,
            ],
            'allocation' => [
                'default' => $this->defaultAllocationId,
                'additional' => $this->additionalAllocations,
            ],
            'start_on_completion' => $this->startOnCompletion,
            'skip_scripts' => $this->skipScripts,
        ];
    }
}
