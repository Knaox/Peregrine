<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Requests;

/**
 * Validates an edit to a still-pending boost. Same shape as creation
 * (multiplier, window, recurrence, parameters + per-parameter max_cap); the
 * controller additionally refuses the edit when the boost is no longer pending.
 */
final class UpdateBoostRequest extends CreateBoostRequest {}
