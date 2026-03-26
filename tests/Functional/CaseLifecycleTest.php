<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Case Lifecycle Functional Test Suite
 * 
 * Validates end-to-end case creation, assignment, status transitions,
 * history tracking, and work lifecycle management.
 */
final class CaseLifecycleTest extends BaseFunctionalTestCase
{
    public function test_create_new_case(): void
    {
        $caseData = $this->createTestCase([
            'case_type' => 'CUSTOMER_ORDER',
            'case_status' => 'NEW',
            'priority' => 'HIGH',
        ]);

        $case = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$caseData['id']]);
        
        $this->assertNotNull($case);
        $this->assertEquals('CUSTOMER_ORDER', $case['case_type']);
        $this->assertEquals('NEW', $case['case_status']);
        $this->assertEquals('HIGH', $case['priority']);
        $this->assertNotEmpty($case['e_tag']);
    }

    public function test_case_status_transition(): void
    {
        $case = $this->createTestCase(['case_status' => 'NEW']);
        
        // Transition: NEW -> IN_PROGRESS
        $this->transitionCaseStatus($case['id'], 'NEW', 'IN_PROGRESS', 'user-manager');
        
        $updated = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertEquals('IN_PROGRESS', $updated['case_status']);
    }

    public function test_case_history_records_status_changes(): void
    {
        $case = $this->createTestCase(['case_status' => 'NEW']);
        
        // Record status change in history
        $historyId = 'history-' . uniqid();
        $this->db->insert('dx_case_history', [
            'id' => $historyId,
            'case_id' => $case['id'],
            'assignment_id' => null,
            'actor_id' => 'user-admin',
            'action' => 'STATUS_CHANGE',
            'from_status' => 'NEW',
            'to_status' => 'IN_PROGRESS',
            'details' => json_encode(['reason' => 'Work started']),
            'e_tag_at_time' => $case['e_tag'],
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);

        $history = $this->db->selectOne('SELECT * FROM dx_case_history WHERE id = ?', [$historyId]);
        
        $this->assertNotNull($history);
        $this->assertEquals('STATUS_CHANGE', $history['action']);
        $this->assertEquals('NEW', $history['from_status']);
        $this->assertEquals('IN_PROGRESS', $history['to_status']);
        $this->assertEquals('user-admin', $history['actor_id']);
    }

    public function test_create_assignment_for_case(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id'], [
            'assignment_type' => 'WORK',
            'assignment_status' => 'OPEN',
            'assigned_to_user_id' => 'user-standard',
            'step_name' => 'Review Documents',
        ]);

        $saved = $this->db->selectOne('SELECT * FROM dx_assignments WHERE id = ?', [$assignment['id']]);
        
        $this->assertNotNull($saved);
        $this->assertEquals($case['id'], $saved['case_id']);
        $this->assertEquals('WORK', $saved['assignment_type']);
        $this->assertEquals('OPEN', $saved['assignment_status']);
        $this->assertEquals('Review Documents', $saved['step_name']);
    }

    public function test_complete_assignment(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id'], ['assignment_status' => 'OPEN']);

        // Complete the assignment
        $newEtag = hash('sha256', $assignment['id'] . time());
        $this->db->update(
            'dx_assignments',
            [
                'assignment_status' => 'COMPLETED',
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => $newEtag,
            ],
            ['id' => $assignment['id'], 'e_tag' => $assignment['e_tag']]
        );

        $updated = $this->db->selectOne('SELECT * FROM dx_assignments WHERE id = ?', [$assignment['id']]);
        $this->assertEquals('COMPLETED', $updated['assignment_status']);
    }

    public function test_reassign_assignment_to_different_user(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id'], [
            'assigned_to_user_id' => 'user-standard',
        ]);

        // Reassign to manager
        $newEtag = hash('sha256', $assignment['id'] . 'reassign' . time());
        $this->db->update(
            'dx_assignments',
            [
                'assigned_to_user_id' => 'user-manager',
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => $newEtag,
            ],
            ['id' => $assignment['id'], 'e_tag' => $assignment['e_tag']]
        );

        $updated = $this->db->selectOne('SELECT * FROM dx_assignments WHERE id = ?', [$assignment['id']]);
        $this->assertEquals('user-manager', $updated['assigned_to_user_id']);
    }

    public function test_assign_to_role_instead_of_user(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id'], [
            'assigned_to_user_id' => null,
            'assigned_to_role_id' => 'role-manager',
        ]);

        $saved = $this->db->selectOne('SELECT * FROM dx_assignments WHERE id = ?', [$assignment['id']]);
        
        $this->assertNull($saved['assigned_to_user_id']);
        $this->assertEquals('role-manager', $saved['assigned_to_role_id']);
    }

    public function test_get_all_assignments_for_case(): void
    {
        $case = $this->createTestCase();
        
        $this->createTestAssignment($case['id'], ['step_name' => 'Step 1']);
        $this->createTestAssignment($case['id'], ['step_name' => 'Step 2']);
        $this->createTestAssignment($case['id'], ['step_name' => 'Step 3']);

        $assignments = $this->db->select(
            'SELECT * FROM dx_assignments WHERE case_id = ? ORDER BY created_at',
            [$case['id']]
        );

        $this->assertCount(3, $assignments);
    }

    public function test_get_case_history(): void
    {
        $case = $this->createTestCase();

        // Add multiple history entries
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('dx_case_history', [
                'id' => 'history-' . $i,
                'case_id' => $case['id'],
                'assignment_id' => null,
                'actor_id' => 'user-admin',
                'action' => "ACTION_$i",
                'from_status' => 'STATUS_' . ($i - 1),
                'to_status' => 'STATUS_' . $i,
                'details' => json_encode(['step' => $i]),
                'e_tag_at_time' => $case['e_tag'],
                'occurred_at' => date('Y-m-d H:i:s', time() + $i),
            ]);
        }

        $history = $this->db->select(
            'SELECT * FROM dx_case_history WHERE case_id = ? ORDER BY occurred_at',
            [$case['id']]
        );

        $this->assertCount(5, $history);
    }

    public function test_case_ownership_transfer(): void
    {
        $case = $this->createTestCase(['owner_id' => 'user-admin']);

        $newEtag = hash('sha256', $case['id'] . 'transfer' . time());
        $this->db->update(
            'dx_cases',
            [
                'owner_id' => 'user-manager',
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => $newEtag,
            ],
            ['id' => $case['id'], 'e_tag' => $case['e_tag']]
        );

        $updated = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertEquals('user-manager', $updated['owner_id']);
    }

    public function test_cascade_delete_assignments_on_case_deletion(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id']);

        // Delete the case
        $this->db->delete('dx_cases', ['id' => $case['id']]);

        // Assignments should be cascade deleted
        $assignments = $this->db->select(
            'SELECT * FROM dx_assignments WHERE case_id = ?',
            [$case['id']]
        );

        $this->assertEmpty($assignments);
    }

    public function test_multiple_open_assignments_for_parallel_processing(): void
    {
        $case = $this->createTestCase();

        $this->createTestAssignment($case['id'], [
            'assignment_status' => 'OPEN',
            'step_name' => 'Credit Check',
            'assigned_to_user_id' => 'user-standard',
        ]);

        $this->createTestAssignment($case['id'], [
            'assignment_status' => 'OPEN',
            'step_name' => 'Inventory Check',
            'assigned_to_user_id' => 'user-manager',
        ]);

        $openAssignments = $this->db->select(
            'SELECT * FROM dx_assignments WHERE case_id = ? AND assignment_status = ?',
            [$case['id'], 'OPEN']
        );

        $this->assertCount(2, $openAssignments);
    }

    private function transitionCaseStatus(string $caseId, string $fromStatus, string $toStatus, string $actorId): void
    {
        $case = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$caseId]);
        
        $newEtag = hash('sha256', $caseId . $toStatus . time());
        
        $this->db->update(
            'dx_cases',
            [
                'case_status' => $toStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => $newEtag,
            ],
            ['id' => $caseId, 'e_tag' => $case['e_tag']]
        );

        // Record in history
        $this->db->insert('dx_case_history', [
            'id' => 'history-' . uniqid(),
            'case_id' => $caseId,
            'assignment_id' => null,
            'actor_id' => $actorId,
            'action' => 'STATUS_TRANSITION',
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'details' => json_encode(['transition' => "$fromStatus -> $toStatus"]),
            'e_tag_at_time' => $case['e_tag'],
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
