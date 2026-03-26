<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use DxEngine\Core\DBALWrapper;
use Ramsey\Uuid\Uuid;

class JobDispatcher
{
    public function __construct(private readonly DBALWrapper $dbal)
    {
    }

    public function dispatch(JobInterface $job, int $delaySeconds = 0): string
    {
        if (((string) ($_ENV['QUEUE_DRIVER'] ?? 'database')) === 'sync') {
            $this->dispatchNow($job);
            return 'sync-executed';
        }

        $jobId = Uuid::uuid4()->toString();
        $availableAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));

        $payload = [];
        if ($job instanceof AbstractJob) {
            $payload = $job->getPayload();
        }

        $this->dbal->insert('dx_jobs', [
            'id' => $jobId,
            'queue' => $job->getQueue(),
            'job_class' => $job::class,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $job->getMaxAttempts(),
            'priority' => 5,
            'available_at' => $availableAt,
            'reserved_at' => null,
            'reserved_by' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'error_trace' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $jobId;
    }

    public function dispatchNow(JobInterface $job): void
    {
        $job->handle();
    }

    public function cancel(string $jobId): bool
    {
        $affected = $this->dbal->update(
            'dx_jobs',
            ['status' => 'cancelled'],
            ['id' => $jobId, 'status' => 'pending']
        );

        return $affected > 0;
    }
}
