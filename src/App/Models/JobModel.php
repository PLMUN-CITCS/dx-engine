<?php

declare(strict_types=1);

namespace DxEngine\App\Models;

use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;

final class JobModel extends DataModel
{
    public function __construct(DBALWrapper $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'dx_jobs';
    }

    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'queue' => ['column' => 'queue', 'type' => 'string'],
            'jobClass' => ['column' => 'job_class', 'type' => 'string'],
            'payload' => ['column' => 'payload', 'type' => 'json'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'attempts' => ['column' => 'attempts', 'type' => 'integer'],
            'maxAttempts' => ['column' => 'max_attempts', 'type' => 'integer'],
            'priority' => ['column' => 'priority', 'type' => 'integer'],
            'availableAt' => ['column' => 'available_at', 'type' => 'datetime'],
            'reservedAt' => ['column' => 'reserved_at', 'type' => 'datetime'],
            'reservedBy' => ['column' => 'reserved_by', 'type' => 'string'],
            'completedAt' => ['column' => 'completed_at', 'type' => 'datetime'],
            'failedAt' => ['column' => 'failed_at', 'type' => 'datetime'],
            'errorMessage' => ['column' => 'error_message', 'type' => 'string'],
            'errorTrace' => ['column' => 'error_trace', 'type' => 'string'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
        ];
    }

    public function claimNextPending(string $queue, string $workerId): ?static
    {
        return $this->db->transactional(function () use ($queue, $workerId): ?static {
            $row = $this->db->selectOne(
                'SELECT * FROM dx_jobs
                 WHERE queue = :queue
                   AND status = :status
                   AND available_at <= :now
                 ORDER BY priority ASC, available_at ASC, created_at ASC
                 LIMIT 1',
                [
                    'queue' => $queue,
                    'status' => 'pending',
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]
            );

            if ($row === null) {
                return null;
            }

            $jobId = (string) $row['id'];

            $updated = $this->db->update('dx_jobs', [
                'status' => 'processing',
                'reserved_by' => $workerId,
                'reserved_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $jobId,
                'status' => 'pending',
            ]);

            if ($updated === 0) {
                return null;
            }

            $fresh = $this->db->selectOne('SELECT * FROM dx_jobs WHERE id = :id', ['id' => $jobId]);

            if ($fresh === null) {
                return null;
            }

            return static::hydrate($fresh);
        });
    }

    public function markProcessing(string $jobId, string $workerId): bool
    {
        $affected = $this->db->update('dx_jobs', [
            'status' => 'processing',
            'reserved_by' => $workerId,
            'reserved_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'id' => $jobId,
        ]);

        return $affected > 0;
    }

    public function markCompleted(string $jobId): bool
    {
        $affected = $this->db->update('dx_jobs', [
            'status' => 'completed',
            'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'id' => $jobId,
        ]);

        return $affected > 0;
    }

    public function markFailed(string $jobId, string $errorMessage, string $trace): bool
    {
        $affected = $this->db->update('dx_jobs', [
            'status' => 'failed',
            'failed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'error_message' => $errorMessage,
            'error_trace' => $trace,
        ], [
            'id' => $jobId,
        ]);

        return $affected > 0;
    }

    public function markRetryPending(string $jobId, int $attempts, string $nextAvailableAt): bool
    {
        $affected = $this->db->update('dx_jobs', [
            'attempts' => $attempts,
            'status' => 'pending',
            'available_at' => $nextAvailableAt,
            'reserved_at' => null,
            'reserved_by' => null,
        ], [
            'id' => $jobId,
        ]);

        return $affected > 0;
    }

    public function releaseStaleJobs(int $timeoutSeconds): int
    {
        $threshold = (new \DateTimeImmutable('-' . $timeoutSeconds . ' seconds'))->format('Y-m-d H:i:s');

        return $this->db->executeStatement(
            'UPDATE dx_jobs
             SET status = :pendingStatus, reserved_by = NULL, reserved_at = NULL
             WHERE status = :processingStatus AND reserved_at IS NOT NULL AND reserved_at < :threshold',
            [
                'pendingStatus' => 'pending',
                'processingStatus' => 'processing',
                'threshold' => $threshold,
            ]
        );
    }

    protected static function newInstance(): static
    {
        throw new \RuntimeException('JobModel::newInstance() requires a DBALWrapper-backed factory binding.');
    }
}
