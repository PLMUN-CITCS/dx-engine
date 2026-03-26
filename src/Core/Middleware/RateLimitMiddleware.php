<?php

declare(strict_types=1);

namespace DxEngine\Core\Middleware;

use Closure;
use DxEngine\Core\Contracts\MiddlewareInterface;

/**
 * Simple in-memory request rate-limiting middleware.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, array{count:int, resetAt:int}>
     */
    private static array $attemptStore = [];

    public function __construct(
        private readonly int $maxAttempts = 60,
        private readonly int $windowSeconds = 60
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public function handle(array $request, Closure $next): mixed
    {
        $key = $this->buildRateLimitKey($request);

        if ($this->tooManyAttempts($key, $this->maxAttempts)) {
            $this->sendThrottleResponse();
            return null;
        }

        $this->incrementAttempts($key);

        return $next($request);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $this->cleanupExpiredWindow($key);
        $bucket = self::$attemptStore[$key] ?? null;

        return $bucket !== null && $bucket['count'] >= $maxAttempts;
    }

    public function incrementAttempts(string $key): void
    {
        $this->cleanupExpiredWindow($key);

        if (!isset(self::$attemptStore[$key])) {
            self::$attemptStore[$key] = [
                'count' => 0,
                'resetAt' => time() + $this->windowSeconds,
            ];
        }

        self::$attemptStore[$key]['count']++;
    }

    public function resetAttempts(string $key): void
    {
        unset(self::$attemptStore[$key]);
    }

    public function sendThrottleResponse(): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . (string) $this->windowSeconds);

        echo json_encode(
            [
                'error' => 'Too many attempts',
                'code' => 429,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array<string, mixed> $request
     */
    private function buildRateLimitKey(array $request): string
    {
        $ipAddress = (string) ($request['ip'] ?? 'unknown');
        $userId = $request['user_id'] ?? null;
        $userSegment = is_scalar($userId) ? (string) $userId : 'guest';

        return $ipAddress . '|' . $userSegment;
    }

    private function cleanupExpiredWindow(string $key): void
    {
        $bucket = self::$attemptStore[$key] ?? null;
        if ($bucket === null) {
            return;
        }

        if (time() > $bucket['resetAt']) {
            unset(self::$attemptStore[$key]);
        }
    }
}
