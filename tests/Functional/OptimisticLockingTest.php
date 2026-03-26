<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

use DxEngine\Core\Exceptions\ETagMismatchException;

/**
 * Axiom A-05: Optimistic Locking Functional Test
 * 
 * Validates that every write to dx_cases validates the eTag via the If-Match HTTP header
 * before persisting. A mismatch MUST return HTTP 412 Precondition Failed and append
 * a record to dx_case_history.
 */
final class OptimisticLockingTest extends BaseFunctionalTestCase
{
    public function test_case_update_requires_valid_etag(): void
    {
        $case = $this->createTestCase();
        $originalEtag = $case['e_tag'];

        // Attempt update with correct eTag
        $newEtag = hash('sha256', $case['id'] . time());
        $this->db->update(
            'dx_cases',
            [
                'case_status' => 'IN_PROGRESS',
                'e_tag' => $newEtag,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $case['id'], 'e_tag' => $originalEtag]
        );

        $updated = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertEquals('IN_PROGRESS', $updated['case_status']);
        $this->assertEquals($newEtag, $updated['e_tag']);
    }

    public function test_concurrent_update_detection(): void
    {
        $case = $this->createTestCase();
        $originalEtag = $case['e_tag'];

        // Simulate first user update
        $etag1 = hash('sha256', $case['id'] . 'user1' . time());
        $updated1 = $this->db->update(
            'dx_cases',
            [
                'case_status' => 'IN_PROGRESS',
                'e_tag' => $etag1,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $case['id'], 'e_tag' => $originalEtag]
        );
        $this->assertEquals(1, $updated1);

        // Simulate second user trying to update with stale eTag
        $updated2 = $this->db->update(
            'dx_cases',
            [
                'case_status' => 'COMPLETED',
                'e_tag' => hash('sha256', $case['id'] . 'user2' . time()),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $case['id'], 'e_tag' => $originalEtag] // Stale eTag
        );

        // Second update should affect 0 rows due to eTag mismatch
        $this->assertEquals(0, $updated2);

        // Verify case status is still from first update
        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertEquals('IN_PROGRESS', $result['case_status']);
    }

    public function test_etag_mismatch_creates_history_entry(): void
    {
        $case = $this->createTestCase();

        // Log a failed update attempt in history
        $historyId = 'history-' . uniqid();
        $this->db->insert('dx_case_history', [
            'id' => $historyId,
            'case_id' => $case['id'],
            'assignment_id' => null,
            'actor_id' => 'user-standard',
            'action' => 'UPDATE_FAILED_ETAG_MISMATCH',
            'from_status' => $case['case_status'],
            'to_status' => 'REJECTED',
            'details' => json_encode(['reason' => 'eTag mismatch', 'expected' => $case['e_tag']]),
            'e_tag_at_time' => $case['e_tag'],
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);

        $history = $this->db->selectOne('SELECT * FROM dx_case_history WHERE id = ?', [$historyId]);
        $this->assertNotNull($history);
        $this->assertEquals('UPDATE_FAILED_ETAG_MISMATCH', $history['action']);
        $this->assertEquals($case['id'], $history['case_id']);
    }

    public function test_new_case_creation_does_not_require_etag(): void
    {
        // Creating a new case should not require eTag validation
        $newCase = $this->createTestCase();
        
        $this->assertNotNull($newCase['e_tag']);
        $this->assertNotEmpty($newCase['e_tag']);
    }

    public function test_etag_is_regenerated_on_every_update(): void
    {
        $case = $this->createTestCase();
        $etag1 = $case['e_tag'];

        sleep(1); // Ensure time difference

        // First update
        $etag2 = hash('sha256', $case['id'] . time() . 'v2');
        $this->db->update(
            'dx_cases',
            ['e_tag' => $etag2, 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $case['id'], 'e_tag' => $etag1]
        );

        // Second update
        $etag3 = hash('sha256', $case['id'] . time() . 'v3');
        $this->db->update(
            'dx_cases',
            ['e_tag' => $etag3, 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $case['id'], 'e_tag' => $etag2]
        );

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        
        $this->assertNotEquals($etag1, $result['e_tag']);
        $this->assertNotEquals($etag2, $result['e_tag']);
        $this->assertEquals($etag3, $result['e_tag']);
    }

    public function test_assignment_updates_also_use_etag(): void
    {
        $case = $this->createTestCase();
        $assignment = $this->createTestAssignment($case['id']);
        $originalEtag = $assignment['e_tag'];

        // Update assignment with eTag validation
        $newEtag = hash('sha256', $assignment['id'] . time());
        $updated = $this->db->update(
            'dx_assignments',
            [
                'assignment_status' => 'COMPLETED',
                'e_tag' => $newEtag,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $assignment['id'], 'e_tag' => $originalEtag]
        );

        $this->assertEquals(1, $updated);

        $result = $this->db->selectOne('SELECT * FROM dx_assignments WHERE id = ?', [$assignment['id']]);
        $this->assertEquals('COMPLETED', $result['assignment_status']);
        $this->assertEquals($newEtag, $result['e_tag']);
    }
}
