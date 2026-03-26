<?php

declare(strict_types=1);

namespace DxEngine\Core\Traits;

use DxEngine\Core\Contracts\GuardInterface;

/**
 * Provides role helper methods backed by the active auth guard.
 */
trait HasRoles
{
    abstract protected function getGuard(): GuardInterface;

    public function hasRole(string $roleName): bool
    {
        return in_array($roleName, $this->getRoles(), true);
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function hasAnyRole(array $roleNames): bool
    {
        if ($roleNames === []) {
            return false;
        }

        return count(array_intersect($this->getRoles(), $roleNames)) > 0;
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function hasAllRoles(array $roleNames): bool
    {
        if ($roleNames === []) {
            return true;
        }

        $roleMap = array_fill_keys($this->getRoles(), true);
        foreach ($roleNames as $roleName) {
            if (!isset($roleMap[$roleName])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $user = $this->getGuard()->user();
        if ($user === null) {
            return [];
        }

        $roles = $user->getAuthRoles();
        return array_values(array_unique(array_filter($roles, 'is_string')));
    }
}
