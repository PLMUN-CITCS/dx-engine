<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DxEngine\Core\DBALWrapper;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$dbFile = __DIR__ . '/../storage/cache/phase1_test.sqlite';

if (!file_exists($dbFile)) {
    echo "DB_FILE_MISSING\n";
    exit(1);
}

$logger = new Logger('phase1-inspect');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/phase1_test.log', Logger::DEBUG));

$db = new DBALWrapper([
    'driver' => 'pdo_sqlite',
    'path' => $dbFile,
    'env' => 'testing',
], $logger);

$tables = $db->select("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name ASC");
echo "TABLE_COUNT=" . count($tables) . PHP_EOL;

foreach ($tables as $table) {
    $name = (string) $table['name'];
    echo "- {$name}" . PHP_EOL;
}

$versions = $db->select("SELECT version FROM dx_migrations ORDER BY version ASC");
echo "MIGRATION_ROWS=" . count($versions) . PHP_EOL;
