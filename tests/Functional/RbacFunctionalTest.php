<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * RBAC (Role-Based Access Control) Functional Test Suite
 * 
 * Validates role and permission management, user role assignments,
 * and permission-based access control throughout the framework.
 */
final class RbacFunctionalTest extends BaseFunctionalTestCase
{
    public function test_user_has_assigned_roles(): void
    {
        $userRoles = $this->db->select(
            'SELECT r.role_name FROM dx_user_roles ur 
             JOIN dx_roles r ON ur.role_id = r.id 
             WHERE ur.user_id = ?',
            ['user-admin']
        );

        $this->assertNotEmpty($userRoles);
        $roleNames = array_column($userRoles, 'role_name');
        $this->assertContains('ROLE_ADMIN', $roleNames);
    }

    public function test_role_has_assigned_permissions(): void
    {
        $permissions = $this->db->select(
            'SELECT p.permission_key FROM dx_role_permissions rp 
             JOIN dx_permissions p ON rp.permission_id = p.id 
             WHERE rp.role_id = ?',
            ['role-admin']
        );

        $this->assertNotEmpty($permissions);
        $permissionKeys = array_column($permissions, 'permission_key');
        $this->assertContains('case:create', $permissionKeys);
        $this->assertContains('case:update', $permissionKeys);
        $this->assertContains('rbac:admin', $permissionKeys);
    }

    public function test_user_inherits_permissions_from_roles(): void
    {
        // Admin user should have all admin permissions
        $adminPermissions = $this->db->select(
            'SELECT DISTINCT p.permission_key 
             FROM dx_user_roles ur
             JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
             JOIN dx_permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ?',
            ['user-admin']
        );

        $this->assertGreaterThanOrEqual(5, count($adminPermissions));
    }

    public function test_standard_user_has_limited_permissions(): void
    {
        $userPermissions = $this->db->select(
            'SELECT DISTINCT p.permission_key 
             FROM dx_user_roles ur
             JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
             JOIN dx_permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ?',
            ['user-standard']
        );

        $permissionKeys = array_column($userPermissions, 'permission_key');
        
        // Standard user should only have read permission
        $this->assertContains('case:read', $permissionKeys);
        $this->assertNotContains('case:delete', $permissionKeys);
        $this->assertNotContains('rbac:admin', $permissionKeys);
    }

    public function test_assign_new_role_to_user(): void
    {
        $this->db->insert('dx_user_roles', [
            'user_id' => 'user-standard',
            'role_id' => 'role-manager',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        $userRoles = $this->db->select(
            'SELECT r.role_name FROM dx_user_roles ur 
             JOIN dx_roles r ON ur.role_id = r.id 
             WHERE ur.user_id = ?',
            ['user-standard']
        );

        $roleNames = array_column($userRoles, 'role_name');
        $this->assertContains('ROLE_MANAGER', $roleNames);
    }

    public function test_remove_role_from_user(): void
    {
        $deleted = $this->db->delete('dx_user_roles', [
            'user_id' => 'user-manager',
            'role_id' => 'role-manager',
        ]);

        $this->assertEquals(1, $deleted);

        $userRoles = $this->db->select(
            'SELECT r.role_name FROM dx_user_roles ur 
             JOIN dx_roles r ON ur.role_id = r.id 
             WHERE ur.user_id = ?',
            ['user-manager']
        );

        $this->assertEmpty($userRoles);
    }

    public function test_permission_check_for_specific_action(): void
    {
        // Check if user-admin has case:delete permission
        $hasPermission = $this->db->selectOne(
            'SELECT COUNT(*) as cnt
             FROM dx_user_roles ur
             JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
             JOIN dx_permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ? AND p.permission_key = ?',
            ['user-admin', 'case:delete']
        );

        $this->assertGreaterThan(0, $hasPermission['cnt']);
    }

    public function test_multiple_roles_aggregate_permissions(): void
    {
        // Assign multiple roles to a user
        $this->db->delete('dx_user_roles', ['user_id' => 'user-standard']);
        
        $this->db->insert('dx_user_roles', [
            'user_id' => 'user-standard',
            'role_id' => 'role-user',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);
        
        $this->db->insert('dx_user_roles', [
            'user_id' => 'user-standard',
            'role_id' => 'role-manager',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        $permissions = $this->db->select(
            'SELECT DISTINCT p.permission_key 
             FROM dx_user_roles ur
             JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
             JOIN dx_permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ?',
            ['user-standard']
        );

        // Should have permissions from both ROLE_USER and ROLE_MANAGER
        $permissionKeys = array_column($permissions, 'permission_key');
        $this->assertContains('case:read', $permissionKeys);
        $this->assertContains('case:create', $permissionKeys);
        $this->assertContains('case:update', $permissionKeys);
    }

    public function test_create_custom_role(): void
    {
        $roleId = 'role-custom-' . uniqid();
        
        $this->db->insert('dx_roles', [
            'id' => $roleId,
            'role_name' => 'ROLE_CUSTOM',
            'display_label' => 'Custom Role',
            'description' => 'Custom test role',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $role = $this->db->selectOne('SELECT * FROM dx_roles WHERE id = ?', [$roleId]);
        $this->assertNotNull($role);
        $this->assertEquals('ROLE_CUSTOM', $role['role_name']);
    }

    public function test_assign_permissions_to_custom_role(): void
    {
        $roleId = 'role-custom-' . uniqid();
        
        $this->db->insert('dx_roles', [
            'id' => $roleId,
            'role_name' => 'ROLE_VIEWER',
            'display_label' => 'Viewer',
            'description' => 'Read-only access',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Assign only read permission
        $this->db->insert('dx_role_permissions', [
            'role_id' => $roleId,
            'permission_id' => 'perm-2', // case:read
        ]);

        $permissions = $this->db->select(
            'SELECT p.permission_key FROM dx_role_permissions rp 
             JOIN dx_permissions p ON rp.permission_id = p.id 
             WHERE rp.role_id = ?',
            [$roleId]
        );

        $this->assertCount(1, $permissions);
        $this->assertEquals('case:read', $permissions[0]['permission_key']);
    }

    public function test_rbac_cascade_delete_on_user_deletion(): void
    {
        // Create a temporary user
        $userId = 'user-temp-' . uniqid();
        
        $this->db->insert('dx_users', [
            'id' => $userId,
            'email' => 'temp@test.com',
            'password_hash' => password_hash('temp', PASSWORD_BCRYPT),
            'full_name' => 'Temp User',
            'is_active' => 1,
            'failed_login_attempts' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', $userId),
        ]);

        $this->db->insert('dx_user_roles', [
            'user_id' => $userId,
            'role_id' => 'role-user',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        // Delete user (should cascade to user_roles)
        $this->db->delete('dx_users', ['id' => $userId]);

        $userRoles = $this->db->select(
            'SELECT * FROM dx_user_roles WHERE user_id = ?',
            [$userId]
        );

        $this->assertEmpty($userRoles);
    }
}
