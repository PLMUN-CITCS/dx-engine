<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationRunner;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$dbConfig = [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/../storage/cache/phase1_test.sqlite',
    'env' => 'testing',
];

$logger = new Logger('phase1-test');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/phase1_test.log', Logger::DEBUG));

$db = new DBALWrapper($dbConfig, $logger);
$runner = new MigrationRunner($db, __DIR__ . '/../database/migrations');

echo "== MIGRATE ==\n";
$runner->migrate();
print_r($runner->status());

echo "\n== ROLLBACK 2 ==\n";
$runner->rollback(2);
print_r($runner->status());

echo "\n== MIGRATE AGAIN ==\n";
$runner->migrate();
print_r($runner->status());

echo "\n== RESET ==\n";
$runner->reset();
print_r($runner->status());

echo "\n== FINAL MIGRATE ==\n";
$runner->migrate();
print_r($runner->status());
