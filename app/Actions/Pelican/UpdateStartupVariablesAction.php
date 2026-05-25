<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Events\AdminActionPerformed;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanClientService;
use Throwable;

/**
 * Apply a batch of startup-variable edits to a server.
 *
 * Pelican's Client API has no bulk endpoint and throttles to 5 req/min/server,
 * so we forward each variable individually with PARTIAL-SUCCESS semantics: a
 * failure on one key (throttle, egg rule rejection, transient 5xx) is recorded
 * in `errors` and never aborts the others. The caller (the unified save bar)
 * keeps the failed keys dirty so they can be retried, while the ones that went
 * through stay saved.
 *
 * A single aggregated audit entry is emitted (keys + applied count) rather than
 * one per variable, to keep the admin action log readable.
 */
final readonly class UpdateStartupVariablesAction
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    /**
     * @param  list<array{key: string, value?: string|null}>  $variables
     * @return array{success: bool, updated: int, errors: array<string, string>}
     */
    public function __invoke(
        User $admin,
        Server $server,
        array $variables,
        ?string $ip = null,
        string $userAgent = '',
    ): array {
        $updated = 0;
        $errors = [];

        foreach ($variables as $variable) {
            $key = $variable['key'];
            // A cleared value arrives as null (ConvertEmptyStringsToNull); send "".
            $value = $variable['value'] ?? '';
            try {
                $this->clientService->updateStartupVariable($server->identifier, $key, $value);
                $updated++;
            } catch (Throwable $e) {
                report($e);
                $errors[$key] = 'update_failed';
            }
        }

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.startup.update',
            server: $server,
            payload: ['keys' => array_column($variables, 'key'), 'updated' => $updated],
            ip: $ip,
            userAgent: $userAgent,
        );

        return ['success' => $errors === [], 'updated' => $updated, 'errors' => $errors];
    }
}
