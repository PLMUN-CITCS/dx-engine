<?php

declare(strict_types=1);

namespace DxEngine\Core\Middleware;

use DxEngine\App\Models\UserModel;
use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;

/**
 * Session-based authentication guard implementation.
 */
final class SessionGuard implements GuardInterface
{
    private const SESSION_AUTH_KEY = '_dx_auth_user_id';
    private const SESSION_REFRESH_KEY = '_dx_auth_refresh_at';

    public function __construct(
        private readonly UserModel $userModel,
        private readonly int $refreshIntervalSeconds = 300
    ) {
        $this->ensureSessionStarted();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->recallFromSession();
    }

    public function login(AuthenticatableInterface $user): void
    {
        $this->ensureSessionStarted();
        $_SESSION[self::SESSION_AUTH_KEY] = (string) $user->getAuthId();
        $_SESSION[self::SESSION_REFRESH_KEY] = time();
    }

    public function logout(): void
    {
        $this->ensureSessionStarted();
        unset($_SESSION[self::SESSION_AUTH_KEY], $_SESSION[self::SESSION_REFRESH_KEY]);
        session_regenerate_id(true);
    }

    public function id(): string|int|null
    {
        $this->ensureSessionStarted();
        $id = $_SESSION[self::SESSION_AUTH_KEY] ?? null;

        return is_string($id) || is_int($id) ? $id : null;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user instanceof UserModel) {
            return false;
        }

        if (!$user->verifyPassword($password)) {
            $user->incrementFailedLogin();
            $user->save();
            return false;
        }

        if (!$user->isActive()) {
            return false;
        }

        $user->resetFailedLogin();
        $user->save();
        $this->login($user);

        return true;
    }

    public function recallFromSession(): ?AuthenticatableInterface
    {
        $this->ensureSessionStarted();
        $authId = $_SESSION[self::SESSION_AUTH_KEY] ?? null;
        if (!is_string($authId) && !is_int($authId)) {
            return null;
        }

        $lastRefreshAt = (int) ($_SESSION[self::SESSION_REFRESH_KEY] ?? 0);
        $needsRefresh = $lastRefreshAt === 0 || (time() - $lastRefreshAt) >= $this->refreshIntervalSeconds;

        $user = $this->userModel->find($authId);
        if (!$user instanceof UserModel) {
            $this->logout();
            return null;
        }

        if ($needsRefresh) {
            $_SESSION[self::SESSION_REFRESH_KEY] = time();
        }

        return $user;
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
