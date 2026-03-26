<?php

declare(strict_types=1);

namespace DxEngine\Core\Middleware;

use Closure;
use DxEngine\Core\Contracts\MiddlewareInterface;

/**
 * CSRF protection middleware for browser-originated state-changing requests.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SESSION_TOKEN_KEY = '_dx_csrf_token';

    /**
     * @param array<string, mixed> $request
     */
    public function handle(array $request, Closure $next): mixed
    {
        $method = strtoupper((string) ($request['method'] ?? 'GET'));
        if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
            $this->ensureSessionStarted();
            if (!isset($_SESSION[self::SESSION_TOKEN_KEY])) {
                $this->generateToken();
            }

            return $next($request);
        }

        if ($this->isTokenAuthenticatedApiRequest($request)) {
            return $next($request);
        }

        $this->ensureSessionStarted();
        $token = $this->getTokenFromRequest($request);

        if ($token === null || !$this->validateToken($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(
                [
                    'error' => 'CSRF token mismatch',
                    'code' => 419,
                ],
                JSON_THROW_ON_ERROR
            );

            return null;
        }

        return $next($request);
    }

    public function generateToken(): string
    {
        $this->ensureSessionStarted();
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_TOKEN_KEY] = $token;

        return $token;
    }

    public function validateToken(string $token): bool
    {
        $this->ensureSessionStarted();
        $sessionToken = (string) ($_SESSION[self::SESSION_TOKEN_KEY] ?? '');

        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function getTokenFromRequest(array $request): ?string
    {
        $headers = $request['headers'] ?? [];
        if (is_array($headers)) {
            $headerToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
            if (is_string($headerToken) && $headerToken !== '') {
                return $headerToken;
            }
        }

        $input = $request['input'] ?? [];
        if (is_array($input)) {
            $formToken = $input['_token'] ?? null;
            if (is_string($formToken) && $formToken !== '') {
                return $formToken;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function isTokenAuthenticatedApiRequest(array $request): bool
    {
        $headers = $request['headers'] ?? [];
        if (!is_array($headers)) {
            return false;
        }

        $authHeader = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        return str_starts_with(strtolower($authHeader), 'bearer ');
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
