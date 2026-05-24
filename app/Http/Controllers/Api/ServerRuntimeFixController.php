<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pelican\AcceptMinecraftEulaAction;
use App\Actions\Pelican\ChangeServerDockerImageAction;
use App\Actions\Pelican\ResolveServerDockerImagesAction;
use App\Events\AdminActionPerformed;
use App\Exceptions\ImageNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\ApplyDockerImageRequest;
use App\Models\Server;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minecraft console quick-fixes surfaced by the SPA when it detects a boot
 * failure in the live Wings log stream:
 *
 *   - accept the EULA      (writes eula.txt=true + restart)
 *   - switch the Java image (startup PATCH preserving egg/startup/env + restart)
 *
 * Each endpoint is gated by the matching ServerPolicy ability and audited when
 * an admin acts on a server they don't own. Pelican failures are translated to
 * the same error codes the console already understands.
 */
class ServerRuntimeFixController extends Controller
{
    /**
     * List the Docker images the server can switch to, flagging the current
     * one and (when `?java=N` is supplied) the recommended one.
     */
    public function dockerImages(Request $request, Server $server, ResolveServerDockerImagesAction $resolve): JsonResponse
    {
        // Readable by anyone who can read OR change the startup config — the
        // image-switch modal (gated on startup.update) must be able to list.
        abort_unless(
            $request->user()->can('readStartup', $server) || $request->user()->can('updateStartup', $server),
            403,
        );

        $java = $request->integer('java');

        try {
            $result = $resolve($server, $java > 0 ? $java : null);
        } catch (RequestException $e) {
            return $this->pelicanError($e);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Apply a Docker image and restart. Rejects images outside the server's
     * allowed set with a 422.
     */
    public function applyDockerImage(ApplyDockerImageRequest $request, Server $server, ChangeServerDockerImageAction $change): JsonResponse
    {
        $image = (string) $request->validated('image');

        try {
            $change($server, $image);
        } catch (ImageNotAllowedException) {
            return response()->json(['error' => 'server-console:fix.java.not_allowed'], 422);
        } catch (RequestException $e) {
            return $this->pelicanError($e);
        }

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.docker_image.change',
            server: $server,
            payload: ['image' => mb_substr($image, 0, 255)],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true, 'image' => $image]);
    }

    /** Accept the Minecraft EULA and restart the server. */
    public function acceptEula(Request $request, Server $server, AcceptMinecraftEulaAction $accept): JsonResponse
    {
        $this->authorize('restartServer', $server);

        try {
            $accept($server);
        } catch (RequestException $e) {
            return $this->pelicanError($e);
        }

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.eula.accept',
            server: $server,
            payload: [],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }

    private function pelicanError(RequestException $e): JsonResponse
    {
        $status = $e->response?->status() ?? 502;
        $code = match ($status) {
            429 => 'server-console:websocket.pelican_throttled',
            403, 404 => 'server-console:websocket.pelican_denied',
            default => 'server-console:websocket.pelican_unavailable',
        };

        return response()->json(['error' => $code], $status === 429 ? 429 : 502);
    }
}
