<?php

declare(strict_types=1);

namespace DxEngine\Core\Middleware;

use Closure;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\Contracts\MiddlewareInterface;

/**
 * Authentication middleware for API and browser requests.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly string $loginUrl = '/login'
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public function handle(array $request, Closure $next): mixed
    {
        if ($this->guard->check() && $this->guard->user()?->isActive() === true) {
            return $next($request);
        }

        if ($this->isApiRequest($request)) {
            $this->sendUnauthorizedResponse();
            return null;
        }

        $this->redirectToLogin();
        return null;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function isApiRequest(array $request): bool
    {
        $headers = $request['headers'] ?? [];
        if (!is_array($headers)) {
            return false;
        }

        $accept = strtolower((string) ($headers['Accept'] ?? $headers['accept'] ?? ''));
        $requestedWith = strtolower(
            (string) ($headers['X-Requested-With'] ?? $headers['x-requested-with'] ?? '')
        );

        return str_contains($accept, 'application/json') || $requestedWith !== '';
    }

    public function sendUnauthorizedResponse(): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(
            [
                'error' => 'Unauthenticated',
                'code' => 401,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    public function redirectToLogin(): void
    {
        header('Location: ' . $this->loginUrl, true, 302);
    }
}
