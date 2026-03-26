<?php

declare(strict_types=1);

namespace DxEngine\Core;

final class Autoloader
{
    /**
     * @var array<string, string>
     */
    private array $namespaces = [];

    public function __construct()
    {
        $srcRoot = dirname(__DIR__, 1);
        $this->addNamespace('DxEngine\\Core\\', $srcRoot . DIRECTORY_SEPARATOR . 'Core');
        $this->addNamespace('DxEngine\\App\\', $srcRoot . DIRECTORY_SEPARATOR . 'App');
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $fullyQualifiedClassName): void
    {
        $normalizedClass = str_replace('/', '\\', ltrim($fullyQualifiedClassName, '\\/'));

        foreach ($this->namespaces as $prefix => $baseDirectory) {
            if (strpos($normalizedClass, $prefix) !== 0) {
                continue;
            }

            $relativeClass = substr($normalizedClass, strlen($prefix));
            if ($relativeClass === false) {
                continue;
            }

            $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $filePath = rtrim($baseDirectory, '\\/') . DIRECTORY_SEPARATOR . $relativePath;

            if (is_file($filePath)) {
                require_once $filePath;
            }

            return;
        }
    }

    public function addNamespace(string $prefix, string $baseDirectory): static
    {
        $normalizedPrefix = trim(str_replace('/', '\\', $prefix), '\\') . '\\';
        $normalizedBaseDirectory = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $baseDirectory), '\\/');

        $this->namespaces[$normalizedPrefix] = $normalizedBaseDirectory;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getRegisteredNamespaces(): array
    {
        return $this->namespaces;
    }
}
