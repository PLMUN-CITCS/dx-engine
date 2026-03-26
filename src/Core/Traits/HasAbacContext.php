<?php

declare(strict_types=1);

namespace DxEngine\Core\Traits;

use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;

/**
 * Provides ABAC context-scoped permission checks.
 */
trait HasAbacContext
{
    abstract protected function getGuard(): GuardInterface;

    abstract protected function getDbal(): DBALWrapper;

    public function canInContext(
        string $permissionKey,
        string $contextType,
        string $contextId
    ): bool {
        if ($permissionKey === '' || $contextType === '' || $contextId === '') {
            return false;
        }

        $roles = $this->getContextualRoles($contextType, $contextId);
        if ($roles === []) {
            return false;
        }

        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return true;
        }

        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $sql = 'SELECT p.key
                FROM dx_permissions p
                INNER JOIN dx_role_permissions rp ON rp.permission_id = p.id
                INNER JOIN dx_roles r ON r.id = rp.role_id
                WHERE r.name IN (' . $placeholders . ')
                  AND p.key = ?';

        $params = [...$roles, $permissionKey];
        $row = $this->getDbal()->selectOne($sql, $params);

        return $row !== null;
    }

    /**
     * @return array<int, string>
     */
    public function getContextualRoles(string $contextType, string $contextId): array
    {
        $user = $this->getGuard()->user();
        if ($user === null) {
            return [];
        }

        $sql = 'SELECT DISTINCT r.name
                FROM dx_user_roles ur
                INNER JOIN dx_roles r ON r.id = ur.role_id
                WHERE ur.user_id = ?
                  AND ur.context_type = ?
                  AND ur.context_id = ?';

        $rows = $this->getDbal()->select(
            $sql,
            [
                (string) $user->getAuthId(),
                $contextType,
                $contextId,
            ]
        );

        $roles = array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $rows
        );

        return array_values(array_unique(array_filter($roles, static fn (string $v): bool => $v !== '')));
    }
}
