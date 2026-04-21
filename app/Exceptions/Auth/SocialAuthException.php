<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base class for social-auth flow errors. Renders as a JSON {error: i18n_key}
 * response with the mapped status. Keeps each call site one-liner without
 * try/catch boilerplate.
 */
abstract class SocialAuthException extends RuntimeException
{
    abstract public function errorKey(): string;

    abstract public function statusCode(): int;

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['error' => $this->errorKey()], $this->statusCode());
    }
}
