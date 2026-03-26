<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use Throwable;

interface JobInterface
{
    public function handle(): void;

    public function failed(Throwable $exception): void;

    public function getQueue(): string;

    public function getMaxAttempts(): int;

    public function getBackoffSeconds(): int;
}
