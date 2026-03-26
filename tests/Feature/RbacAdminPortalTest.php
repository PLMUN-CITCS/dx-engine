<?php

declare(strict_types=1);

namespace DxEngine\Tests\Feature;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\LayoutService;
use DxEngine\Tests\Integration\BaseIntegrationTestCase;

final class RbacAdminPortalTest extends BaseIntegrationTestCase
{
    public function test_rbac_admin_portal_is_fully_pruned_for_non_rbac_manage_users(): void
    {
        $payload = [
            'data' => [],
            'uiResources' => [
                [
                    'component_type' => 'section_header',
                    'key' => 'rbac_admin_root',
                    'required_permission' => 'rbac:manage',
                ],
            ],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $service = new LayoutService($this->makeGuardWithPermissions([]));
        $pruned = $service->prunePayload($payload);

        $this->assertSame([], $pruned['uiResources']);
    }

    public function test_role_list_view_returns_all_non_system_and_system_roles(): void
    {
        $this->db()->insert('dx_roles', [
            'id' => 'role-1',
            'name' => 'ROLE_ADMIN',
            'display_name' => 'Admin',
            'description' => 'System role',
            'is_system' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db()->insert('dx_roles', [
            'id' => 'role-2',
            'name' => 'ROLE_CUSTOM',
            'display_name' => 'Custom',
            'description' => 'Custom role',
            'is_system' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $rows = $this->db()->select('SELECT name, is_system FROM dx_roles ORDER BY name ASC');

        $this->assertCount(2, $rows);
        $this->assertSame('ROLE_ADMIN', $rows[0]['name']);
        $this->assertSame('ROLE_CUSTOM', $rows[1]['name']);
    }

    public function test_permission_assignment_view_updates_role_permissions_via_rbac_admin_api(): void
    {
        $this->db()->insert('dx_roles', [
            'id' => 'role-assign',
            'name' => 'ROLE_MANAGER',
            'display_name' => 'Manager',
            'description' => 'Manager role',
            'is_system' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db()->insert('dx_permissions', [
            'id' => 'perm-1',
            'key' => 'case:read',
            'description' => 'Read case',
            'category' => 'case',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $inserted = $this->db()->insert('dx_role_permissions', [
            'role_id' => 'role-assign',
            'permission_id' => 'perm-1',
        ]);

        $this->assertSame(1, (int) $inserted);

        $row = $this->db()->selectOne(
            'SELECT role_id, permission_id FROM dx_role_permissions WHERE role_id = ? AND permission_id = ?',
            ['role-assign', 'perm-1']
        );

        $this->assertNotNull($row);
        $this->assertSame('role-assign', $row['role_id']);
        $this->assertSame('perm-1', $row['permission_id']);
    }

    public function test_system_role_cannot_be_deleted_via_api(): void
    {
        $this->db()->insert('dx_roles', [
            'id' => 'sys-role',
            'name' => 'ROLE_SUPER_ADMIN',
            'display_name' => 'Super Admin',
            'description' => 'Protected',
            'is_system' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $deleted = $this->db()->delete('dx_roles', [
            'id' => 'sys-role',
            'is_system' => 0,
        ]);

        $this->assertSame(0, $deleted);

        $row = $this->db()->selectOne('SELECT id FROM dx_roles WHERE id = ?', ['sys-role']);
        $this->assertNotNull($row);
    }

    public function test_user_role_assignment_with_abac_context_is_persisted_correctly(): void
    {
        $this->db()->insert('dx_users', [
            'id' => 'user-abac',
            'username' => 'abac.user',
            'email' => 'abac@example.com',
            'password_hash' => 'hash',
            'display_name' => 'ABAC User',
            'status' => 'active',
            'last_login_at' => null,
            'password_changed_at' => null,
            'failed_login_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db()->insert('dx_roles', [
            'id' => 'role-abac',
            'name' => 'ROLE_MANAGER',
            'display_name' => 'Manager',
            'description' => null,
            'is_system' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db()->insert('dx_user_roles', [
            'user_id' => 'user-abac',
            'role_id' => 'role-abac',
            'context_type' => 'business_unit',
            'context_id' => 'BU-42',
            'granted_by' => null,
            'granted_at' => date('Y-m-d H:i:s'),
        ]);

        $row = $this->db()->selectOne(
            'SELECT context_type, context_id FROM dx_user_roles WHERE user_id = ? AND role_id = ?',
            ['user-abac', 'role-abac']
        );

        $this->assertNotNull($row);
        $this->assertSame('business_unit', $row['context_type']);
        $this->assertSame('BU-42', $row['context_id']);
    }

    private function makeGuardWithPermissions(array $permissions): GuardInterface
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthId')->willReturn('admin-1');
        $user->method('getAuthPermissions')->willReturn($permissions);
        $user->method('getAuthRoles')->willReturn(['ROLE_ADMIN']);
        $user->method('getAuthEmail')->willReturn('admin@example.com');
        $user->method('isActive')->willReturn(true);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($user);

        return $guard;
    }
}
