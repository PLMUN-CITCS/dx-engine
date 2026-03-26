<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

use DxEngine\Core\DBALWrapper;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Base Functional Test Suite
 * 
 * Provides foundational setup for comprehensive end-to-end functional testing
 * of the DX Engine framework, covering all architectural axioms and phase objectives.
 * 
 * @package DxEngine\Tests\Functional
 */
abstract class BaseFunctionalTestCase extends TestCase
{
    protected DBALWrapper $db;
    protected Logger $logger;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger('functional-test');
        $this->logger->pushHandler(new NullHandler());

        $this->config = $this->loadTestConfig();
        $this->db = $this->createDatabaseConnection();
        $this->db->executeStatement('PRAGMA foreign_keys = ON');
        $this->runMigrations();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        parent::tearDown();
    }

    protected function loadTestConfig(): array
    {
        return [
            'app' => [
                'env' => 'testing',
                'debug' => true,
                'key' => hash('sha256', 'test-secret-key'),
            ],
            'database' => [
                'driver' => 'pdo_sqlite',
                'path' => ':memory:',
                'memory' => true,
            ],
            'security' => [
                'etag_algo' => 'sha256',
                'bcrypt_cost' => 10,
                'session_regenerate_on_login' => true,
                'max_failed_login_attempts' => 5,
            ],
        ];
    }

    protected function createDatabaseConnection(): DBALWrapper
    {
        return new DBALWrapper($this->config['database'], $this->logger);
    }

    protected function runMigrations(): void
    {
        $this->createMigrationsTables();
        $this->createUsersTables();
        $this->createRbacTables();
        $this->createCasesTables();
        $this->createJobsTables();
        $this->createWebhooksTables();
    }

    protected function createMigrationsTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration_name TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at TEXT NOT NULL
            )
        ");
    }

    protected function createUsersTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_users (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                last_login_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                e_tag TEXT NOT NULL
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_sessions (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                payload TEXT,
                last_activity TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES dx_users(id) ON DELETE CASCADE
            )
        ");
    }

    protected function createRbacTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_roles (
                id TEXT PRIMARY KEY,
                role_name TEXT NOT NULL UNIQUE,
                display_label TEXT NOT NULL,
                description TEXT,
                created_at TEXT NOT NULL
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_permissions (
                id TEXT PRIMARY KEY,
                permission_key TEXT NOT NULL UNIQUE,
                display_label TEXT NOT NULL,
                category TEXT,
                created_at TEXT NOT NULL
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_role_permissions (
                role_id TEXT NOT NULL,
                permission_id TEXT NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES dx_roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES dx_permissions(id) ON DELETE CASCADE
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_user_roles (
                user_id TEXT NOT NULL,
                role_id TEXT NOT NULL,
                assigned_at TEXT NOT NULL,
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES dx_users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES dx_roles(id) ON DELETE CASCADE
            )
        ");
    }

    protected function createCasesTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_cases (
                id TEXT PRIMARY KEY,
                case_type TEXT NOT NULL,
                case_status TEXT NOT NULL,
                owner_id TEXT,
                priority TEXT,
                created_by_id TEXT,
                payload TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                e_tag TEXT NOT NULL,
                FOREIGN KEY (owner_id) REFERENCES dx_users(id),
                FOREIGN KEY (created_by_id) REFERENCES dx_users(id)
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_assignments (
                id TEXT PRIMARY KEY,
                case_id TEXT NOT NULL,
                assignment_type TEXT NOT NULL,
                assignment_status TEXT NOT NULL,
                assigned_to_user_id TEXT,
                assigned_to_role_id TEXT,
                step_name TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                e_tag TEXT NOT NULL,
                FOREIGN KEY (case_id) REFERENCES dx_cases(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to_user_id) REFERENCES dx_users(id),
                FOREIGN KEY (assigned_to_role_id) REFERENCES dx_roles(id)
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_case_history (
                id TEXT PRIMARY KEY,
                case_id TEXT NOT NULL,
                assignment_id TEXT,
                actor_id TEXT,
                action TEXT NOT NULL,
                from_status TEXT,
                to_status TEXT,
                details TEXT,
                e_tag_at_time TEXT,
                occurred_at TEXT NOT NULL,
                FOREIGN KEY (case_id) REFERENCES dx_cases(id) ON DELETE CASCADE,
                FOREIGN KEY (assignment_id) REFERENCES dx_assignments(id),
                FOREIGN KEY (actor_id) REFERENCES dx_users(id)
            )
        ");
    }

    protected function createJobsTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_jobs (
                id TEXT PRIMARY KEY,
                queue TEXT NOT NULL DEFAULT 'default',
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                available_at TEXT NOT NULL,
                reserved_at TEXT,
                reserved_by TEXT,
                completed_at TEXT,
                failed_at TEXT,
                error_message TEXT,
                created_at TEXT NOT NULL
            )
        ");
    }

    protected function createWebhooksTables(): void
    {
        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_webhooks (
                id TEXT PRIMARY KEY,
                event_type TEXT NOT NULL,
                target_url TEXT NOT NULL,
                http_method TEXT NOT NULL DEFAULT 'POST',
                headers TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )
        ");

        $this->db->executeStatement("
            CREATE TABLE IF NOT EXISTS dx_webhook_logs (
                id TEXT PRIMARY KEY,
                webhook_id TEXT NOT NULL,
                case_id TEXT,
                request_payload TEXT,
                response_status INTEGER,
                response_body TEXT,
                error_message TEXT,
                attempt_number INTEGER NOT NULL DEFAULT 1,
                dispatched_at TEXT NOT NULL,
                FOREIGN KEY (webhook_id) REFERENCES dx_webhooks(id) ON DELETE CASCADE,
                FOREIGN KEY (case_id) REFERENCES dx_cases(id)
            )
        ");
    }

    protected function seedTestData(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedTestUsers();
    }

    protected function seedRolesAndPermissions(): void
    {
        $roles = [
            ['id' => 'role-admin', 'role_name' => 'ROLE_ADMIN', 'display_label' => 'Administrator', 'description' => 'System administrator', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'role-manager', 'role_name' => 'ROLE_MANAGER', 'display_label' => 'Manager', 'description' => 'Case manager', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'role-user', 'role_name' => 'ROLE_USER', 'display_label' => 'User', 'description' => 'Standard user', 'created_at' => date('Y-m-d H:i:s')],
        ];

        foreach ($roles as $role) {
            $this->db->insert('dx_roles', $role);
        }

        $permissions = [
            ['id' => 'perm-1', 'permission_key' => 'case:create', 'display_label' => 'Create Cases', 'category' => 'cases', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'perm-2', 'permission_key' => 'case:read', 'display_label' => 'Read Cases', 'category' => 'cases', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'perm-3', 'permission_key' => 'case:update', 'display_label' => 'Update Cases', 'category' => 'cases', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'perm-4', 'permission_key' => 'case:delete', 'display_label' => 'Delete Cases', 'category' => 'cases', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 'perm-5', 'permission_key' => 'rbac:admin', 'display_label' => 'RBAC Administration', 'category' => 'admin', 'created_at' => date('Y-m-d H:i:s')],
        ];

        foreach ($permissions as $permission) {
            $this->db->insert('dx_permissions', $permission);
        }

        $rolePermissions = [
            ['role_id' => 'role-admin', 'permission_id' => 'perm-1'],
            ['role_id' => 'role-admin', 'permission_id' => 'perm-2'],
            ['role_id' => 'role-admin', 'permission_id' => 'perm-3'],
            ['role_id' => 'role-admin', 'permission_id' => 'perm-4'],
            ['role_id' => 'role-admin', 'permission_id' => 'perm-5'],
            ['role_id' => 'role-manager', 'permission_id' => 'perm-1'],
            ['role_id' => 'role-manager', 'permission_id' => 'perm-2'],
            ['role_id' => 'role-manager', 'permission_id' => 'perm-3'],
            ['role_id' => 'role-user', 'permission_id' => 'perm-2'],
        ];

        foreach ($rolePermissions as $rp) {
            $this->db->insert('dx_role_permissions', $rp);
        }
    }

    protected function seedTestUsers(): void
    {
        $users = [
            [
                'id' => 'user-admin',
                'email' => 'admin@test.com',
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 10]),
                'full_name' => 'Admin User',
                'is_active' => 1,
                'failed_login_attempts' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => hash('sha256', 'user-admin' . time())
            ],
            [
                'id' => 'user-manager',
                'email' => 'manager@test.com',
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 10]),
                'full_name' => 'Manager User',
                'is_active' => 1,
                'failed_login_attempts' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => hash('sha256', 'user-manager' . time())
            ],
            [
                'id' => 'user-standard',
                'email' => 'user@test.com',
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 10]),
                'full_name' => 'Standard User',
                'is_active' => 1,
                'failed_login_attempts' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => hash('sha256', 'user-standard' . time())
            ],
        ];

        foreach ($users as $user) {
            $this->db->insert('dx_users', $user);
        }

        $userRoles = [
            ['user_id' => 'user-admin', 'role_id' => 'role-admin', 'assigned_at' => date('Y-m-d H:i:s')],
            ['user_id' => 'user-manager', 'role_id' => 'role-manager', 'assigned_at' => date('Y-m-d H:i:s')],
            ['user_id' => 'user-standard', 'role_id' => 'role-user', 'assigned_at' => date('Y-m-d H:i:s')],
        ];

        foreach ($userRoles as $ur) {
            $this->db->insert('dx_user_roles', $ur);
        }
    }

    protected function cleanupDatabase(): void
    {
        // Tables are in-memory, will be destroyed automatically
    }

    protected function createTestCase(array $data = []): array
    {
        $defaults = [
            'id' => 'case-' . uniqid(),
            'case_type' => 'TEST_CASE',
            'case_status' => 'NEW',
            'owner_id' => 'user-admin',
            'priority' => 'NORMAL',
            'created_by_id' => 'user-admin',
            'payload' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', uniqid())
        ];

        $caseData = array_merge($defaults, $data);
        $this->db->insert('dx_cases', $caseData);

        return $caseData;
    }

    protected function createTestAssignment(string $caseId, array $data = []): array
    {
        $defaults = [
            'id' => 'assignment-' . uniqid(),
            'case_id' => $caseId,
            'assignment_type' => 'WORK',
            'assignment_status' => 'OPEN',
            'assigned_to_user_id' => 'user-standard',
            'step_name' => 'Review',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', uniqid())
        ];

        $assignmentData = array_merge($defaults, $data);
        $this->db->insert('dx_assignments', $assignmentData);

        return $assignmentData;
    }

    protected function assert4NodeJsonContract(array $response): void
    {
        $this->assertArrayHasKey('data', $response, '4-Node JSON: Missing "data" node');
        $this->assertArrayHasKey('uiResources', $response, '4-Node JSON: Missing "uiResources" node');
        $this->assertArrayHasKey('nextAssignmentInfo', $response, '4-Node JSON: Missing "nextAssignmentInfo" node');
        $this->assertArrayHasKey('confirmationNote', $response, '4-Node JSON: Missing "confirmationNote" node');

        $allowedKeys = ['data', 'uiResources', 'nextAssignmentInfo', 'confirmationNote'];
        $extraKeys = array_diff(array_keys($response), $allowedKeys);
        $this->assertEmpty($extraKeys, '4-Node JSON: Unexpected root nodes found: ' . implode(', ', $extraKeys));
    }
}

