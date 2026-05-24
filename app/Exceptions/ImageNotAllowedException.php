<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a caller asks to switch a server onto a Docker image that isn't
 * part of the server's allowed set (the egg's docker_images + yolks fallback).
 * The console quick-fix controller maps this to a 422 — never let an arbitrary
 * image string reach Pelican.
 */
final class ImageNotAllowedException extends RuntimeException
{
    public function __construct(public readonly string $image)
    {
        parent::__construct("Docker image not allowed for this server: {$image}");
    }
}
