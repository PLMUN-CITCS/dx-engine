<?php

declare(strict_types=1);

namespace DxEngine\Database\Seeds;

use DxEngine\Core\DBALWrapper;

final class RolePermissionSeeder
{
    private DBALWrapper $db;

    public function __construct(DBALWrapper $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        $this->db->transactional(function (): void {
            $roles = $this->seedRoles();
            $permissions = $this->seedPermissions();
            $this->seedRolePermissionMappings($roles, $permissions);
        });
    }

    /**
     * @return array<string, string> role_name => role_id
     */
    private function seedRoles(): array
    {
        $roleDefinitions = [
            'ROLE_SUPER_ADMIN' => 'Super Administrator',
            'ROLE_ADMIN' => 'Administrator',
            'ROLE_MANAGER' => 'Manager',
            'ROLE_OPERATOR' => 'Operator',
            'ROLE_VIEWER' => 'Viewer',
        ];

        $result = [];

        foreach ($roleDefinitions as $name => $displayName) {
            $existing = $this->db->selectOne(
                'SELECT id FROM dx_roles WHERE name = :name',
                ['name' => $name]
            );

            if ($existing !== null) {
                $result[$name] = (string) $existing['id'];
                continue;
            }

            $id = $this->uuidV4();
            $this->db->insert('dx_roles', [
                'id' => $id,
                'name' => $name,
                'display_name' => $displayName,
                'description' => $displayName . ' system role',
                'is_system' => true,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);

            $result[$name] = $id;
        }

        return $result;
    }

    /**
     * @return array<string, string> permission_key => permission_id
     */
    private function seedPermissions(): array
    {
        $permissionDefinitions = [
            'case:create' => ['description' => 'Create cases', 'category' => 'case'],
            'case:read' => ['description' => 'Read cases', 'category' => 'case'],
            'case:update' => ['description' => 'Update cases', 'category' => 'case'],
            'case:delete' => ['description' => 'Delete cases', 'category' => 'case'],
            'case:approve' => ['description' => 'Approve cases', 'category' => 'case'],
            'case:reassign' => ['description' => 'Reassign cases', 'category' => 'case'],
            'worklist:claim' => ['description' => 'Claim worklist assignments', 'category' => 'worklist'],
            'worklist:release' => ['description' => 'Release worklist assignments', 'category' => 'worklist'],
            'report:export' => ['description' => 'Export reports', 'category' => 'report'],
            'user:manage' => ['description' => 'Manage users', 'category' => 'user'],
            'rbac:manage' => ['description' => 'Manage RBAC', 'category' => 'rbac'],
        ];

        $result = [];

        foreach ($permissionDefinitions as $key => $meta) {
            $existing = $this->db->selectOne(
                'SELECT id FROM dx_permissions WHERE key = :key',
                ['key' => $key]
            );

            if ($existing !== null) {
                $result[$key] = (string) $existing['id'];
                continue;
            }

            $id = $this->uuidV4();
            $this->db->insert('dx_permissions', [
                'id' => $id,
                'key' => $key,
                'description' => $meta['description'],
                'category' => $meta['category'],
                'created_at' => $this->now(),
            ]);

            $result[$key] = $id;
        }

        return $result;
    }

    /**
     * @param array<string, string> $roles
     * @param array<string, string> $permissions
     */
    private function seedRolePermissionMappings(array $roles, array $permissions): void
    {
        $allPermissions = array_keys($permissions);

        $mapping = [
            'ROLE_SUPER_ADMIN' => $allPermissions,
            'ROLE_ADMIN' => array_values(array_filter(
                $allPermissions,
                static fn(string $key): bool => $key !== 'rbac:manage'
            )),
            'ROLE_MANAGER' => [
                'case:create',
                'case:read',
                'case:update',
                'case:delete',
                'case:approve',
                'case:reassign',
                'worklist:claim',
                'worklist:release',
                'report:export',
            ],
            'ROLE_OPERATOR' => [
                'case:read',
                'case:update',
                'worklist:claim',
                'worklist:release',
            ],
            'ROLE_VIEWER' => [
                'case:read',
            ],
        ];

        foreach ($mapping as $roleName => $permissionKeys) {
            $roleId = $roles[$roleName] ?? null;
            if ($roleId === null) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                $permissionId = $permissions[$permissionKey] ?? null;
                if ($permissionId === null) {
                    continue;
                }

                $exists = $this->db->selectOne(
                    'SELECT role_id, permission_id
                     FROM dx_role_permissions
                     WHERE role_id = :roleId AND permission_id = :permissionId',
                    [
                        'roleId' => $roleId,
                        'permissionId' => $permissionId,
                    ]
                );

                if ($exists !== null) {
                    continue;
                }

                $this->db->insert('dx_role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
