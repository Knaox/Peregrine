<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pelican\UpdateStartupVariablesAction;
use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Plugin\StartupVariableClaimRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Startup-variable endpoints for a server. Split out of ServerController to keep
 * both files within the 300-line rule and to group the read + single + batch
 * write of env variables in one cohesive place.
 */
class ServerStartupController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readStartup', $server);
        $variables = $this->clientService->getStartupVariables($server->identifier);

        // Flag variables a plugin has "claimed" via StartupVariableClaimRegistry
        // so the UI can badge them as linked. They are shown here — the core
        // startup page is the single place to edit them — and the claiming plugin
        // hides them from its own editor. Core stays plugin-agnostic.
        $claimed = StartupVariableClaimRegistry::getInstance()->claimedFor($server);
        if ($claimed !== []) {
            $variables = array_map(
                static function (array $variable) use ($claimed): array {
                    $variable['claimed'] = in_array($variable['env_variable'] ?? '', $claimed, true);

                    return $variable;
                },
                $variables,
            );
        }

        return response()->json(['data' => $variables]);
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $this->authorize('updateStartup', $server);
        // Clearing a variable must be savable. `required` rejected "" with a 422;
        // `present` alone isn't enough either because the global
        // ConvertEmptyStringsToNull middleware turns "" into null before
        // validation — so allow null and coerce it back to "".
        $validated = $request->validate(['key' => ['required', 'string'], 'value' => ['present', 'nullable', 'string']]);
        $value = $validated['value'] ?? '';
        $this->clientService->updateStartupVariable($server->identifier, $validated['key'], $value);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.startup.update',
            server: $server,
            payload: ['key' => $validated['key'], 'value' => mb_substr($value, 0, 500)],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }

    /**
     * Batch update — lets the unified save bar persist every edited variable in
     * one round-trip. Pelican exposes no bulk endpoint (and throttles to 5
     * req/min/server), so the action applies them one by one with partial-success
     * semantics: failed keys are reported, the rest still apply. `value` allows
     * null (a cleared "" arrives as null via ConvertEmptyStringsToNull) — the
     * action coerces it back to "".
     */
    public function updateBatch(Request $request, Server $server, UpdateStartupVariablesAction $action): JsonResponse
    {
        $this->authorize('updateStartup', $server);
        $validated = $request->validate([
            'variables' => ['required', 'array', 'min:1'],
            'variables.*.key' => ['required', 'string'],
            'variables.*.value' => ['present', 'nullable', 'string'],
        ]);

        $result = $action(
            admin: $request->user(),
            server: $server,
            variables: $validated['variables'],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json($result);
    }
}
