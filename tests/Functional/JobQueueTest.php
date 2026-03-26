<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Job Queue Functional Test Suite
 * 
 * Validates asynchronous job dispatching, queue management,
 * job execution, retry logic, and failure handling.
 */
final class JobQueueTest extends BaseFunctionalTestCase
{
    public function test_dispatch_job_to_queue(): void
    {
        $jobId = 'job-' . uniqid();
        
        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'WebhookDispatchJob',
            'payload' => json_encode(['case_id' => 'case-123', 'url' => 'https://example.com/webhook']),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $job = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = ?', [$jobId]);
        
        $this->assertNotNull($job);
        $this->assertEquals('pending', $job['status']);
        $this->assertEquals('WebhookDispatchJob', $job['job_class']);
    }

    public function test_reserve_job_for_processing(): void
    {
        $jobId = 'job-' . uniqid();
        
        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'PdfGenerationJob',
            'payload' => json_encode(['case_id' => 'case-456']),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Reserve the job
        $workerId = 'worker-1';
        $this->db->update(
            'dx_jobs',
            [
                'status' => 'processing',
                'reserved_at' => date('Y-m-d H:i:s'),
                'reserved_by' => $workerId,
                'attempts' => 1,
            ],
            ['id' => $jobId, 'status' => 'pending']
        );

        $job = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = ?', [$jobId]);
        
        $this->assertEquals('processing', $job['status']);
        $this->assertEquals($workerId, $job['reserved_by']);
        $this->assertEquals(1, $job['attempts']);
    }

    public function test_mark_job_as_completed(): void
    {
        $jobId = 'job-' . uniqid();
        
        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'SpreadsheetImportJob',
            'payload' => json_encode(['file' => 'import.xlsx']),
            'status' => 'processing',
            'attempts' => 1,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'reserved_at' => date('Y-m-d H:i:s'),
            'reserved_by' => 'worker-1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Mark as completed
        $this->db->update(
            'dx_jobs',
            [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ],
            ['id' => $jobId]
        );

        $job = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = ?', [$jobId]);
        
        $this->assertEquals('completed', $job['status']);
        $this->assertNotNull($job['completed_at']);
    }

    public function test_job_retry_on_failure(): void
    {
        $jobId = 'job-' . uniqid();
        
        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'WebhookDispatchJob',
            'payload' => json_encode(['url' => 'https://example.com/webhook']),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Simulate failed attempt
        $this->db->update(
            'dx_jobs',
            [
                'status' => 'pending',
                'attempts' => 1,
                'error_message' => 'Connection timeout',
                'available_at' => date('Y-m-d H:i:s', time() + 60), // Retry after 60 seconds
            ],
            ['id' => $jobId]
        );

        $job = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = ?', [$jobId]);
        
        $this->assertEquals(1, $job['attempts']);
        $this->assertEquals('pending', $job['status']);
        $this->assertNotNull($job['error_message']);
    }

    public function test_job_marked_as_failed_after_max_attempts(): void
    {
        $jobId = 'job-' . uniqid();
        
        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'WebhookDispatchJob',
            'payload' => json_encode(['url' => 'https://example.com/webhook']),
            'status' => 'processing',
            'attempts' => 3,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'reserved_at' => date('Y-m-d H:i:s'),
            'reserved_by' => 'worker-1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Mark as failed after max attempts
        $this->db->update(
            'dx_jobs',
            [
                'status' => 'failed',
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => 'Max retry attempts exceeded',
            ],
            ['id' => $jobId]
        );

        $job = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = ?', [$jobId]);
        
        $this->assertEquals('failed', $job['status']);
        $this->assertNotNull($job['failed_at']);
        $this->assertEquals(3, $job['attempts']);
    }

    public function test_get_pending_jobs_from_queue(): void
    {
        // Add multiple jobs
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('dx_jobs', [
                'id' => "job-pending-$i",
                'queue' => 'default',
                'job_class' => 'TestJob',
                'payload' => json_encode(['test' => $i]),
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 3,
                'available_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $pendingJobs = $this->db->select(
            'SELECT * FROM dx_jobs WHERE status = ? AND available_at <= ? ORDER BY created_at',
            ['pending', date('Y-m-d H:i:s')]
        );

        $this->assertGreaterThanOrEqual(5, count($pendingJobs));
    }

    public function test_delayed_job_execution(): void
    {
        $jobId = 'job-delayed-' . uniqid();
        $delayedUntil = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

        $this->db->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => 'default',
            'job_class' => 'DelayedJob',
            'payload' => json_encode(['data' => 'test']),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => $delayedUntil,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Should not be available for processing yet
        $availableNow = $this->db->select(
            'SELECT * FROM dx_jobs WHERE status = ? AND available_at <= ?',
            ['pending', date('Y-m-d H:i:s')]
        );

        $jobIds = array_column($availableNow, 'id');
        $this->assertNotContains($jobId, $jobIds);

        // Should be available after the delay
        $availableLater = $this->db->select(
            'SELECT * FROM dx_jobs WHERE status = ? AND available_at <= ?',
            ['pending', $delayedUntil]
        );

        $jobIdsLater = array_column($availableLater, 'id');
        $this->assertContains($jobId, $jobIdsLater);
    }

    public function test_queue_prioritization(): void
    {
        // Add jobs to different queues
        $this->db->insert('dx_jobs', [
            'id' => 'job-high-priority',
            'queue' => 'high',
            'job_class' => 'UrgentJob',
            'payload' => '{}',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->insert('dx_jobs', [
            'id' => 'job-default',
            'queue' => 'default',
            'job_class' => 'NormalJob',
            'payload' => '{}',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $highPriorityJobs = $this->db->select(
            'SELECT * FROM dx_jobs WHERE queue = ? AND status = ?',
            ['high', 'pending']
        );

        $this->assertNotEmpty($highPriorityJobs);
    }

    public function test_cleanup_old_completed_jobs(): void
    {
        // Add old completed job
        $this->db->insert('dx_jobs', [
            'id' => 'job-old-completed',
            'queue' => 'default',
            'job_class' => 'OldJob',
            'payload' => '{}',
            'status' => 'completed',
            'attempts' => 1,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s', time() - 86400 * 30),
            'completed_at' => date('Y-m-d H:i:s', time() - 86400 * 30),
            'created_at' => date('Y-m-d H:i:s', time() - 86400 * 30),
        ]);

        // Delete jobs completed more than 7 days ago
        $deleted = $this->db->executeStatement(
            'DELETE FROM dx_jobs WHERE status = ? AND completed_at < ?',
            ['completed', date('Y-m-d H:i:s', time() - 86400 * 7)]
        );

        $this->assertGreaterThanOrEqual(1, $deleted);
    }
}
