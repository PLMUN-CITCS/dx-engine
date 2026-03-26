<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$files = glob(__DIR__ . '/../database/migrations/*.php') ?: [];
sort($files);

foreach ($files as $file) {
    require_once $file;
}

$all = get_declared_classes();
$count = 0;

foreach ($all as $className) {
    if (is_subclass_of($className, \DxEngine\Core\Migrations\MigrationInterface::class)) {
        $count++;
        echo $className . PHP_EOL;
    }
}

echo 'COUNT=' . $count . PHP_EOL;
