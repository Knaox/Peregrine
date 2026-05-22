<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Plugins\EasyConfiguration\Exceptions\BoostOverlapException;
use Plugins\EasyConfiguration\Http\Concerns\ResolvesServerAccess;
use Plugins\EasyConfiguration\Http\Requests\CreateBoostRequest;
use Plugins\EasyConfiguration\Models\BoostHistory;
use Plugins\EasyConfiguration\Models\BoostSchedule;
use Plugins\EasyConfiguration\Services\Boost\BoostService;
use Plugins\EasyConfiguration\Services\Templates\TemplateDefinition;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;

/**
 * Boost CRUD for a server. Reads are gated by easyconfig.read, mutations by
 * easyconfig.write. Creation re-checks that the template allows boost and that
 * every chosen parameter is numeric and not blacklisted, and maps the
 * one-boost-per-parameter rule to a clear 422.
 */
final class BoostController
{
    use ResolvesServerAccess;

    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly BoostService $boosts,
    ) {}

    public function index(Request $request, string $server): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.read', 'file.read');

        $rows = BoostSchedule::query()->where('server_id', $model->id)->live()->orderBy('start_at')->get();

        return response()->json(['data' => $rows->map(fn (BoostSchedule $boost): array => $this->serialize($boost))]);
    }

    public function store(CreateBoostRequest $request, string $server): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.write', 'file.update');

        $validated = $request->validated();
        $definition = $this->registry->definition($validated['template_id']);
        if ($definition === null || ! $definition->boostEnabled()) {
            return response()->json(['error' => ['code' => 'boost_not_allowed']], 422);
        }

        $invalid = $this->firstNonBoostable($definition, $validated['parameters']);
        if ($invalid !== null) {
            return response()->json(['error' => ['code' => 'not_boostable', 'parameter' => $invalid]], 422);
        }

        try {
            $boost = $this->boosts->create(
                $model,
                $validated['template_id'],
                (float) $validated['multiplier'],
                Carbon::parse($validated['start_at']),
                Carbon::parse($validated['end_at']),
                $validated['parameters'],
                $request->user()?->id,
            );
        } catch (BoostOverlapException $e) {
            return response()->json(['error' => [
                'code' => 'boost_overlap',
                'conflict' => ['start_at' => $e->conflict->start_at, 'end_at' => $e->conflict->end_at],
            ]], 422);
        }

        return response()->json(['data' => $this->serialize($boost)], 201);
    }

    public function destroy(Request $request, string $server, int $boost): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.write', 'file.update');

        $schedule = BoostSchedule::query()->where('server_id', $model->id)->find($boost);
        if ($schedule === null) {
            abort(404);
        }

        $this->boosts->cancel($schedule);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    public function history(Request $request, string $server): JsonResponse
    {
        $model = $this->resolveServer($server, $request);
        $this->authorizeServer($request, $model, 'easyconfig.read', 'file.read');

        $rows = BoostHistory::query()->where('server_id', $model->id)->orderByDesc('created_at')->limit(50)->get();

        return response()->json(['data' => $rows->map(fn (BoostHistory $row): array => [
            'id' => $row->id,
            'template_id' => $row->template_id,
            'multiplier' => $row->multiplier,
            'start_at' => $row->start_at,
            'end_at' => $row->end_at,
            'final_status' => $row->final_status,
            'parameters' => $row->parameters,
            'note' => $row->note,
            'created_at' => $row->created_at,
        ])]);
    }

    /** @return array<string, mixed> */
    private function serialize(BoostSchedule $boost): array
    {
        return [
            'id' => $boost->id,
            'template_id' => $boost->template_id,
            'multiplier' => $boost->multiplier,
            'start_at' => $boost->start_at,
            'end_at' => $boost->end_at,
            'status' => $boost->status,
            'parameters' => $boost->parameters,
            'applied_at' => $boost->applied_at,
            'ended_at' => $boost->ended_at,
        ];
    }

    /**
     * @param  list<array{file_id: string, section?: string|null, key: string}>  $params
     */
    private function firstNonBoostable(TemplateDefinition $definition, array $params): ?string
    {
        $blacklist = $definition->boostBlacklist();

        foreach ($params as $param) {
            $def = $this->paramDef($definition, (string) $param['file_id'], $param['section'] ?? null, (string) $param['key']);
            $type = is_array($def) ? ($def['display_type'] ?? null) : null;
            if (! in_array($type, ['number', 'slider'], true) || in_array($param['key'], $blacklist, true)) {
                return (string) $param['key'];
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function paramDef(TemplateDefinition $definition, string $fileId, ?string $section, string $key): ?array
    {
        foreach ($definition->files() as $file) {
            if ((string) ($file['id'] ?? '') !== $fileId) {
                continue;
            }
            $params = is_array($file['parameters'] ?? null) ? $file['parameters'] : [];
            if ($section !== null && is_array($params[$section][$key] ?? null)) {
                return $params[$section][$key];
            }
            if (is_array($params[$key] ?? null) && isset($params[$key]['display_type'])) {
                return $params[$key];
            }
        }

        return null;
    }
}
