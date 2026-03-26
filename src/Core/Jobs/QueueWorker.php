<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use DxEngine\App\Models\JobModel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class QueueWorker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly JobModel $jobModel,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function work(string $queue = 'default', int $sleepSeconds = 5): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function (): void {
                $this->shouldStop = true;
            });
        }

        while (!$this->shouldStop) {
            $processed = $this->processNext($queue);
            if (!$processed) {
                sleep(max(1, $sleepSeconds));
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    public function processNext(string $queue): bool
    {
        $job = $this->claimJob($queue);
        if ($job === null) {
            return false;
        }

        $this->executeJob($job);
        return true;
    }

    public function claimJob(string $queue): ?JobModel
    {
        $workerId = (string) ($_ENV['QUEUE_WORKER_ID'] ?? gethostname() ?: 'worker-node');
        return $this->jobModel->claimNextPending($queue, $workerId);
    }

    public function executeJob(JobModel $jobRecord): void
    {
        $jobClass = (string) ($jobRecord->jobClass ?? '');
        $payloadValue = $jobRecord->payload ?? [];
        if (is_string($payloadValue)) {
            $decoded = json_decode($payloadValue, true);
            $payload = is_array($decoded) ? $decoded : [];
        } elseif (is_array($payloadValue)) {
            $payload = $payloadValue;
        } else {
            $payload = [];
        }

        if (!class_exists($jobClass)) {
            $this->handleJobFailure($jobRecord, new \RuntimeException('Job class not found: ' . $jobClass));
            return;
        }

        $job = new $jobClass();
        if (!$job instanceof JobInterface) {
            $this->handleJobFailure($jobRecord, new \RuntimeException('Invalid job type: ' . $jobClass));
            return;
        }

        if ($job instanceof AbstractJob) {
            $job->setPayload($payload);
        }

        try {
            $job->handle();
            $this->jobModel->markCompleted((string) $jobRecord->id);
            return;
        } catch (Throwable $e) {
            $attempts = (int) ($jobRecord->attempts ?? 0) + 1;
            $maxAttempts = (int) ($jobRecord->maxAttempts ?? $job->getMaxAttempts());

            if ($attempts >= $maxAttempts) {
                $this->handleJobFailure($jobRecord, $e);
                return;
            }

            $nextAvailableAt = date('Y-m-d H:i:s', time() + $job->getBackoffSeconds());
            $this->jobModel->markRetryPending((string) $jobRecord->id, $attempts, $nextAvailableAt);
        }
    }

    public function handleJobFailure(JobModel $jobRecord, Throwable $e): void
    {
        $this->jobModel->markFailed(
            (string) $jobRecord->id,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $jobClass = (string) ($jobRecord->jobClass ?? '');
        if (class_exists($jobClass)) {
            $job = new $jobClass();
            if ($job instanceof JobInterface) {
                $job->failed($e);
            }
        }

        $this->getLogger()->error('Queue job failed', [
            'job_id' => (string) ($jobRecord->id ?? ''),
            'job_class' => $jobClass,
            'error' => $e->getMessage(),
        ]);
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
