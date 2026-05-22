<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Plugins\EasyConfiguration\Http\Concerns\ResolvesServerAccess;
use Plugins\EasyConfiguration\Http\Requests\CopyConfigRequest;
use Plugins\EasyConfiguration\Jobs\CopyConfigJob;
use Plugins\EasyConfiguration\Models\CopyLog;
use Throwable;

/**
 * Copy a server's configuration to other servers of the same egg owned by the
 * caller. `targets` lists the eligible servers (with their live running state
 * so the UI can disable running ones); `store` dispatches the background job;
 * `log` is polled to build the per-server recap.
 */
final class CopyController
{
    use ResolvesServerAccess;

    public function targets(Request $request, string $server, PelicanClientService $client): JsonResponse
    {
        $source = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $source, 'easyconfig.read', 'file.read');

        $user = $request->user();
        $candidates = Server::query()
            ->where('egg_id', $source->egg_id)
            ->where('id', '!=', $source->id)
            ->when($user !== null && ! $user->is_admin, fn ($query) => $query->where('user_id', $user->id))
            ->with('egg')
            ->orderBy('name')
            ->get();

        $data = $candidates->map(function (Server $candidate) use ($client): array {
            $running = false;
            try {
                $running = $client->getServerResources($candidate->identifier)->state !== 'offline';
            } catch (Throwable) {
                $running = false;
            }

            return [
                'id' => $candidate->id,
                'identifier' => $candidate->identifier,
                'name' => $candidate->name,
                'running' => $running,
                'egg' => [
                    'id' => $candidate->egg?->id,
                    'name' => $candidate->egg?->name,
                    'banner_image' => $candidate->egg?->banner_image ? asset('storage/'.$candidate->egg->banner_image) : null,
                ],
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(CopyConfigRequest $request, string $server): JsonResponse
    {
        $source = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $source, 'easyconfig.write', 'file.update');

        $validated = $request->validated();
        $user = $request->user();

        /** @var list<int> $targetIds */
        $targetIds = Server::query()
            ->whereIn('id', array_values(array_unique($validated['targets'])))
            ->where('egg_id', $source->egg_id)
            ->where('id', '!=', $source->id)
            ->when($user !== null && ! $user->is_admin, fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id')
            ->all();

        $batchId = (string) Str::uuid();
        CopyConfigJob::dispatch($batchId, $source->id, $targetIds, $validated['files'], $user?->id);

        return response()->json(['data' => ['batch_id' => $batchId, 'targets' => count($targetIds)]]);
    }

    public function log(Request $request, string $server): JsonResponse
    {
        $source = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $source, 'easyconfig.read', 'file.read');

        $rows = CopyLog::query()
            ->where('batch_id', (string) $request->query('batch_id', ''))
            ->where('source_server_id', $source->id)
            ->get(['target_server_id', 'status', 'params_count', 'error']);

        return response()->json(['data' => $rows]);
    }
}
