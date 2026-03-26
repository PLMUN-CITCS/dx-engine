<?php

declare(strict_types=1);

namespace DxEngine\Core\Traits;

use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;

/**
 * Provides resolved permission helper methods with per-request caching.
 */
trait HasPermissions
{
    /**
     * @var array<int, string>|null
     */
    protected ?array $resolvedPermissionsCache = null;

    abstract protected function getGuard(): GuardInterface;

    abstract protected function getDbal(): DBALWrapper;

    public function can(string $permissionKey): bool
    {
        if ($permissionKey === '') {
            return false;
        }

        $roles = $this->getRoles();
        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return true;
        }

        return in_array($permissionKey, $this->getPermissions(), true);
    }

    public function cannot(string $permissionKey): bool
    {
        return !$this->can($permissionKey);
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    public function canAny(array $permissionKeys): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if ($this->can($permissionKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    public function canAll(array $permissionKeys): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if (!$this->can($permissionKey)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getPermissions(): array
    {
        if ($this->resolvedPermissionsCache !== null) {
            return $this->resolvedPermissionsCache;
        }

        $this->resolvedPermissionsCache = $this->resolvePermissions($this->getRoles());
        return $this->resolvedPermissionsCache;
    }

    /**
     * @param array<int, string> $roles
     *
     * @return array<int, string>
     */
    public function resolvePermissions(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $sql = 'SELECT DISTINCT p.key
                FROM dx_permissions p
                INNER JOIN dx_role_permissions rp ON rp.permission_id = p.id
                INNER JOIN dx_roles r ON r.id = rp.role_id
                WHERE r.name IN (' . $placeholders . ')';

        $rows = $this->getDbal()->select($sql, $roles);
        $keys = array_map(
            static fn (array $row): string => (string) ($row['key'] ?? ''),
            $rows
        );

        return array_values(array_unique(array_filter($keys, static fn (string $v): bool => $v !== '')));
    }
}
