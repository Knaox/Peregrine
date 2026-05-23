<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers;

use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\EasyConfiguration\Http\Concerns\ResolvesServerAccess;
use Plugins\EasyConfiguration\Http\Requests\PowerRequest;
use Plugins\EasyConfiguration\Http\Requests\SaveConfigRequest;
use Plugins\EasyConfiguration\Services\Config\ConfigReaderService;
use Plugins\EasyConfiguration\Services\Config\ConfigWriterService;

/**
 * Player-facing config endpoints. Thin: server resolution + permission gating
 * live in the trait, the read/write logic in the services. The status/power
 * pair backs the "stop the server before editing" overlay.
 */
final class ServerConfigController
{
    use ResolvesServerAccess;

    public function show(Request $request, string $server, ConfigReaderService $reader): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.read', 'file.read');

        $data = $reader->read($model);
        // Surface the caller's capabilities so the editor can render read-only
        // and hide copy/boost when the subuser lacks the matching permission.
        $data['permissions'] = [
            'write' => $this->canServer($request, $model, 'easyconfig.write', 'file.update'),
            'copy' => $this->canServer($request, $model, 'easyconfig.copy', 'file.update'),
            'boost' => $this->canServer($request, $model, 'easyconfig.boost', 'file.update'),
            // Admin-only: gates the inline "annotate a discovered parameter into
            // the template" action. The real barrier is EnsureAdmin on the
            // /admin/templates routes; this just hides the affordance otherwise.
            'manage_templates' => $request->user()?->is_admin === true,
        ];

        return response()->json(['data' => $data]);
    }

    public function update(SaveConfigRequest $request, string $server, ConfigWriterService $writer): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.write', 'file.update');

        /** @var list<array{id: string, values?: list<array{key: string, section?: string|null, value: string}>}> $files */
        $files = $request->validated()['files'];
        $result = $writer->write($model, $files);

        if ($result['errors'] !== []) {
            return response()->json([
                'error' => ['code' => 'validation_failed', 'fields' => $result['errors']],
            ], 422);
        }

        return response()->json(['data' => [
            'written' => $result['written'],
            'env_synced' => $result['env_synced'] ?? 0,
            'env_errors' => $result['env_errors'] ?? [],
        ]]);
    }

    public function status(Request $request, string $server, PelicanClientService $client): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.read', 'file.read');

        return response()->json(['data' => ['state' => $client->getServerResources($model->identifier)->state]]);
    }

    public function power(PowerRequest $request, string $server, PelicanClientService $client): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.write', 'file.update');

        $client->setPowerState($model->identifier, $request->validated()['signal']);

        return response()->json(['data' => ['ok' => true]]);
    }
}
