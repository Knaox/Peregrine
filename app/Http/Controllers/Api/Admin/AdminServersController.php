<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminServerResource;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-only endpoint that returns ALL servers with their owner info.
 *
 * Authorization is layered: the `admin` middleware ensures `is_admin`, the
 * `two-factor` middleware ensures 2FA is set up when `auth_2fa_required_admins`
 * is enabled. Resource-level checks (ServerPolicy) are bypassed for admins
 * via the scoped Gate::before (AuthServiceProvider, plan §S5).
 */
class AdminServersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Server::query()->with(['user:id,name,email', 'egg', 'plan']);

        if ($search = $request->query('search')) {
            $search = (string) $search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', (int) $userId);
        }

        $perPage = (int) $request->query('per_page', '25');
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderBy('name')->paginate($perPage);

        return AdminServerResource::collection($paginator)->response();
    }
}
