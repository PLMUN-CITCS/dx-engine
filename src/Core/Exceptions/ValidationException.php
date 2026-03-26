<?php

declare(strict_types=1);

namespace DxEngine\Core\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /**
     * @param array<int|string, mixed> $errors
     */
    public function __construct(
        string $message = 'Validation failed.',
        private readonly array $errors = [],
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
