<?php

declare(strict_types=1);

namespace DxEngine\Core\Config;

final class ConfigRegistry
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }
}
