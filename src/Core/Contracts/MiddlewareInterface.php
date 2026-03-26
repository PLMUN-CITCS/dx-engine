<?php

declare(strict_types=1);

namespace DxEngine\Core\Contracts;

use Closure;

/**
 * Standard contract for HTTP middleware pipeline stages.
 */
interface MiddlewareInterface
{
    /**
     * Handle request and invoke next middleware stage.
     *
     * @param array<string, mixed> $request
     * @param Closure              $next
     *
     * @return mixed
     */
    public function handle(array $request, Closure $next): mixed;
}
