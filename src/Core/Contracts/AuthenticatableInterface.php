<?php

declare(strict_types=1);

namespace DxEngine\Core\Contracts;

/**
 * Contract for authenticated user entities used by guards and middleware.
 */
interface AuthenticatableInterface
{
    /**
     * Get unique authenticated user identifier.
     *
     * @return string|int
     */
    public function getAuthId(): string|int;

    /**
     * Get authenticated user email.
     */
    public function getAuthEmail(): string;

    /**
     * Get authenticated user role names.
     *
     * @return array<int, string>
     */
    public function getAuthRoles(): array;

    /**
     * Get flattened authenticated user permission keys.
     *
     * @return array<int, string>
     */
    public function getAuthPermissions(): array;

    /**
     * Determine whether authenticated user is active.
     */
    public function isActive(): bool;
}
