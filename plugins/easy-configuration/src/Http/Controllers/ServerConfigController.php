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

        return response()->json(['data' => $reader->read($model)]);
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

        return response()->json(['data' => ['written' => $result['written']]]);
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
