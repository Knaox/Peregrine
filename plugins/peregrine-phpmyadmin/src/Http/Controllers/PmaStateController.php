<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;

/**
 * Lightweight, SPA-polled flag telling the React button whether to render.
 * Kept separate from the launch endpoint so the button can hide itself when
 * the integration is disabled or unconfigured, without attempting a launch.
 */
class PmaStateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = PmaSettings::make();

        return response()->json([
            'enabled' => $settings->enabled && $settings->pmaUrl !== '',
            'auto_select_db' => $settings->autoSelectDb,
        ]);
    }
}
