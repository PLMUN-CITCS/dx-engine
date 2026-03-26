<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\DxWorklistService;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class DxWorklistServiceTest extends BaseUnitTestCase
{
    private DBALWrapper $db;
    private GuardInterface $guard;
    private DxWorklistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-worklist');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement(
            'CREATE TABLE dx_cases (
                id TEXT PRIMARY KEY,
                case_reference TEXT,
                status TEXT,
                priority INTEGER
            )'
        );

        $this->db->executeStatement(
            'CREATE TABLE dx_assignments (
                id TEXT PRIMARY KEY,
                case_id TEXT,
                assignment_type TEXT,
                step_name TEXT,
                status TEXT,
                assigned_to_user TEXT NULL,
                assigned_to_role TEXT NULL,
                instructions TEXT NULL,
                form_schema_key TEXT NULL,
                deadline_at TEXT NULL,
                started_at TEXT NULL,
                completed_at TEXT NULL,
                completed_by TEXT NULL,
                completion_data TEXT NULL,
                created_at TEXT
            )'
        );

        $this->db->executeStatement(
            'CREATE TABLE dx_case_history (
                id TEXT,
                case_id TEXT,
                assignment_id TEXT NULL,
                actor_id TEXT NULL,
                action TEXT,
                from_status TEXT NULL,
                to_status TEXT NULL,
                details TEXT NULL,
                e_tag_at_time TEXT NULL,
                occurred_at TEXT
            )'
        );

        $this->db->insert('dx_cases', [
            'id' => 'case-1',
            'case_reference' => 'CASE-00001',
            'status' => 'OPEN',
            'priority' => 2,
        ]);

        $this->db->insert('dx_cases', [
            'id' => 'case-2',
            'case_reference' => 'CASE-00002',
            'status' => 'OPEN',
            'priority' => 1,
        ]);

        $this->seedAssignments();

        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthRoles')->willReturn(['ROLE_OPERATOR']);

        $this->guard = $this->createMock(GuardInterface::class);
        $this->guard->method('user')->willReturn($user);

        $this->service = new DxWorklistService($this->db, $this->guard);
    }

    public function test_claim_assignment_atomically_sets_assigned_user_and_active_status(): void
    {
        $ok = $this->service->claimAssignment('asg-pending-1', 'user-1');

        $this->assertTrue($ok);

        $row = $this->db->selectOne('SELECT assigned_to_user, status, started_at FROM dx_assignments WHERE id = ?', ['asg-pending-1']);
        $this->assertNotNull($row);
        $this->assertSame('user-1', $row['assigned_to_user']);
        $this->assertSame('active', $row['status']);
        $this->assertNotNull($row['started_at']);
    }

    public function test_claim_assignment_returns_false_when_assignment_is_already_claimed_by_another_user(): void
    {
        $ok = $this->service->claimAssignment('asg-active-other', 'user-1');

        $this->assertFalse($ok);
    }

    public function test_release_assignment_resets_to_pending_status_and_clears_assigned_user(): void
    {
        $ok = $this->service->releaseAssignment('asg-active-self', 'user-1');

        $this->assertTrue($ok);

        $row = $this->db->selectOne('SELECT assigned_to_user, status, started_at FROM dx_assignments WHERE id = ?', ['asg-active-self']);
        $this->assertNotNull($row);
        $this->assertNull($row['assigned_to_user']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['started_at']);
    }

    public function test_release_assignment_returns_false_when_called_by_non_claimant_without_admin_role(): void
    {
        $ok = $this->service->releaseAssignment('asg-active-other', 'user-1');

        $this->assertFalse($ok);
    }

    public function test_release_assignment_succeeds_when_called_by_admin_regardless_of_claimant(): void
    {
        $admin = $this->createMock(AuthenticatableInterface::class);
        $admin->method('getAuthRoles')->willReturn(['ROLE_ADMIN']);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($admin);

        $service = new DxWorklistService($this->db, $guard);

        $ok = $service->releaseAssignment('asg-active-other', 'user-1');

        $this->assertTrue($ok);

        $row = $this->db->selectOne('SELECT assigned_to_user, status FROM dx_assignments WHERE id = ?', ['asg-active-other']);
        $this->assertNotNull($row);
        $this->assertNull($row['assigned_to_user']);
        $this->assertSame('pending', $row['status']);
    }

    public function test_get_personal_worklist_returns_only_active_assignments_for_the_specified_user(): void
    {
        $rows = $this->service->getPersonalWorklist('user-1');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('active', $row['status']);
            $this->assertSame('user-1', $row['assigned_to_user']);
        }
    }

    public function test_get_group_queue_returns_only_pending_assignments_for_specified_role(): void
    {
        $rows = $this->service->getGroupQueue('ROLE_OPERATOR');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('pending', $row['status']);
            $this->assertSame('ROLE_OPERATOR', $row['assigned_to_role']);
        }
    }

    public function test_log_event_inserts_immutable_record_with_correct_fields_to_case_history(): void
    {
        $this->service->logEvent('case-1', 'ASSIGNMENT_CLAIMED', 'user-1', ['k' => 'v'], 'asg-pending-1');

        $row = $this->db->selectOne(
            'SELECT case_id, action, actor_id, assignment_id FROM dx_case_history WHERE case_id = ? AND action = ?',
            ['case-1', 'ASSIGNMENT_CLAIMED']
        );

        $this->assertNotNull($row);
        $this->assertSame('case-1', $row['case_id']);
        $this->assertSame('ASSIGNMENT_CLAIMED', $row['action']);
        $this->assertSame('user-1', $row['actor_id']);
        $this->assertSame('asg-pending-1', $row['assignment_id']);
    }

    public function test_get_assignment_summary_returns_product_info_formatted_counts(): void
    {
        $summary = $this->service->getAssignmentSummary('user-1');

        $this->assertArrayHasKey('my_active', $summary);
        $this->assertArrayHasKey('my_overdue', $summary);
        $this->assertArrayHasKey('my_due_today', $summary);

        $this->assertIsInt($summary['my_active']);
        $this->assertIsInt($summary['my_overdue']);
        $this->assertIsInt($summary['my_due_today']);
    }

    private function seedAssignments(): void
    {
        $now = date('Y-m-d H:i:s');
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $today = date('Y-m-d 12:00:00');

        $this->db->insert('dx_assignments', [
            'id' => 'asg-pending-1',
            'case_id' => 'case-1',
            'assignment_type' => 'UserTask',
            'step_name' => 'Review',
            'status' => 'pending',
            'assigned_to_user' => null,
            'assigned_to_role' => 'ROLE_OPERATOR',
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => $today,
            'started_at' => null,
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => $now,
        ]);

        $this->db->insert('dx_assignments', [
            'id' => 'asg-active-self',
            'case_id' => 'case-1',
            'assignment_type' => 'UserTask',
            'step_name' => 'Approve',
            'status' => 'active',
            'assigned_to_user' => 'user-1',
            'assigned_to_role' => 'ROLE_OPERATOR',
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => $yesterday,
            'started_at' => $now,
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => $now,
        ]);

        $this->db->insert('dx_assignments', [
            'id' => 'asg-active-other',
            'case_id' => 'case-2',
            'assignment_type' => 'UserTask',
            'step_name' => 'Validate',
            'status' => 'active',
            'assigned_to_user' => 'user-2',
            'assigned_to_role' => 'ROLE_OPERATOR',
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => $today,
            'started_at' => $now,
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => $now,
        ]);
    }
}
