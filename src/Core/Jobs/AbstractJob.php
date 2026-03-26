<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use Throwable;

abstract class AbstractJob implements JobInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function getMaxAttempts(): int
    {
        return 3;
    }

    public function getBackoffSeconds(): int
    {
        return 60;
    }

    public function failed(Throwable $exception): void
    {
        // Default no-op; subclasses may override.
    }
}
