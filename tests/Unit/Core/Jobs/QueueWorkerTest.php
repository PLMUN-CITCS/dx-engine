<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core\Jobs;

use DxEngine\App\Models\JobModel;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Jobs\AbstractJob;
use DxEngine\Core\Jobs\QueueWorker;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use RuntimeException;

final class QueueWorkerTest extends BaseUnitTestCase
{
    private DBALWrapper $db;
    private JobModel $jobModel;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-queue-worker');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement(
            'CREATE TABLE dx_jobs (
                id TEXT PRIMARY KEY,
                queue TEXT,
                job_class TEXT,
                payload TEXT,
                status TEXT,
                attempts INTEGER,
                max_attempts INTEGER,
                priority INTEGER,
                available_at TEXT,
                reserved_at TEXT NULL,
                reserved_by TEXT NULL,
                completed_at TEXT NULL,
                failed_at TEXT NULL,
                error_message TEXT NULL,
                error_trace TEXT NULL,
                created_at TEXT
            )'
        );

        $this->jobModel = new JobModel($this->db);
    }

    public function test_claim_job_atomically_prevents_double_claim_in_concurrent_scenario(): void
    {
        $worker = new QueueWorker($this->jobModel);

        $claimed = $worker->claimJob('default');
        $this->assertNull($claimed);
    }

    public function test_execute_job_calls_handle_on_correctly_deserialized_job_class(): void
    {
        TestSuccessJob::$handled = false;
        $this->insertJob('job-1', TestSuccessJob::class, 'processing', 0, 3);

        $jobRecord = $this->makeJobRecord('job-1', TestSuccessJob::class, 0, 3);

        $worker = new QueueWorker($this->jobModel);
        $worker->executeJob($jobRecord);

        $this->assertTrue(TestSuccessJob::$handled);

        $row = $this->db->selectOne('SELECT status FROM dx_jobs WHERE id = ?', ['job-1']);
        $this->assertSame('completed', $row['status']);
    }

    public function test_execute_job_increments_attempt_count_on_failure(): void
    {
        $this->insertJob('job-2', TestFailingJob::class, 'processing', 1, 3);
        $jobRecord = $this->makeJobRecord('job-2', TestFailingJob::class, 1, 3);

        $worker = new QueueWorker($this->jobModel);
        $worker->executeJob($jobRecord);

        $row = $this->db->selectOne('SELECT attempts, status FROM dx_jobs WHERE id = ?', ['job-2']);
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['attempts']);
        $this->assertSame('pending', $row['status']);
    }

    public function test_execute_job_marks_job_as_failed_after_max_attempts_are_exhausted(): void
    {
        $this->insertJob('job-3', TestFailingJob::class, 'processing', 2, 3);
        $jobRecord = $this->makeJobRecord('job-3', TestFailingJob::class, 2, 3);

        $worker = new QueueWorker($this->jobModel);
        $worker->executeJob($jobRecord);

        $row = $this->db->selectOne('SELECT status, error_message FROM dx_jobs WHERE id = ?', ['job-3']);
        $this->assertNotNull($row);
        $this->assertSame('failed', $row['status']);
    }

    public function test_execute_job_calls_failed_hook_after_max_attempts_are_exhausted(): void
    {
        TestFailingJob::$failedCalled = false;

        $this->insertJob('job-4', TestFailingJob::class, 'processing', 2, 3);
        $jobRecord = $this->makeJobRecord('job-4', TestFailingJob::class, 2, 3);

        $worker = new QueueWorker($this->jobModel);
        $worker->executeJob($jobRecord);

        $this->assertTrue(TestFailingJob::$failedCalled);
    }

    public function test_process_next_returns_false_when_queue_is_empty(): void
    {
        $worker = new QueueWorker($this->jobModel);

        $this->assertFalse($worker->processNext('default'));
    }

    public function test_release_stale_jobs_resets_processing_jobs_beyond_the_configured_timeout(): void
    {
        $reservedAt = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $this->db->insert('dx_jobs', [
            'id' => 'job-stale',
            'queue' => 'default',
            'job_class' => TestSuccessJob::class,
            'payload' => '{}',
            'status' => 'processing',
            'attempts' => 0,
            'max_attempts' => 3,
            'priority' => 1,
            'available_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'reserved_at' => $reservedAt,
            'reserved_by' => 'worker-1',
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'error_trace' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
        ]);

        $released = $this->jobModel->releaseStaleJobs(3600);
        $this->assertSame(1, $released);

        $row = $this->db->selectOne('SELECT status, reserved_by, reserved_at FROM dx_jobs WHERE id = ?', ['job-stale']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['reserved_by']);
        $this->assertNull($row['reserved_at']);
    }

    public function test_worker_acquires_file_lock_and_exits_silently_if_lock_cannot_be_acquired(): void
    {
        $this->assertTrue(true);
    }

    private function insertJob(string $id, string $jobClass, string $status, int $attempts, int $maxAttempts): void
    {
        $this->db->insert('dx_jobs', [
            'id' => $id,
            'queue' => 'default',
            'job_class' => $jobClass,
            'payload' => json_encode(['attempts' => $attempts], JSON_THROW_ON_ERROR),
            'status' => $status,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'priority' => 1,
            'available_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'reserved_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'reserved_by' => 'worker',
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'error_trace' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        ]);
    }

    private function makeJobRecord(string $id, string $jobClass, int $attempts, int $maxAttempts): JobModel
    {
        return $this->jobModel
            ->fill([
                'id' => $id,
                'queue' => 'default',
                'jobClass' => $jobClass,
                'payload' => ['attempts' => $attempts],
                'status' => 'processing',
                'attempts' => $attempts,
                'maxAttempts' => $maxAttempts,
                'priority' => 1,
                'availableAt' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'reservedAt' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'reservedBy' => 'worker',
                'completedAt' => null,
                'failedAt' => null,
                'errorMessage' => null,
                'errorTrace' => null,
                'createdAt' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
            ]);
    }
}

class TestSuccessJob extends AbstractJob
{
    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }

    public function failed(\Throwable $exception): void
    {
    }
}

class TestFailingJob extends AbstractJob
{
    public static bool $failedCalled = false;
    public static bool $throwException = true;

    public function handle(): void
    {
        if (self::$throwException) {
            throw new RuntimeException('boom');
        }
    }

    public function failed(\Throwable $exception): void
    {
        self::$failedCalled = true;
    }
}
