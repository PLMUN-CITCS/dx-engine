<?php

declare(strict_types=1);

namespace DxEngine\Core\Contracts;

/**
 * Contract for interchangeable authentication guard implementations.
 */
interface GuardInterface
{
    /**
     * Determine whether a user is authenticated.
     */
    public function check(): bool;

    /**
     * Get current authenticated user.
     */
    public function user(): ?AuthenticatableInterface;

    /**
     * Authenticate and persist session for the given user.
     */
    public function login(AuthenticatableInterface $user): void;

    /**
     * Logout the current authenticated user.
     */
    public function logout(): void;

    /**
     * Get current authenticated user identifier.
     *
     * @return string|int|null
     */
    public function id(): string|int|null;
}
