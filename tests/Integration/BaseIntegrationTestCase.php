<?php

declare(strict_types=1);

namespace DxEngine\Tests\Integration;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationRunner;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

abstract class BaseIntegrationTestCase extends TestCase
{
    protected static ?DBALWrapper $db = null;
    protected static ?MigrationRunner $migrationRunner = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$db !== null) {
            return;
        }

        $config = [
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ];

        $logger = new Logger('test-integration');
        $logger->pushHandler(new NullHandler());

        self::$db = new DBALWrapper($config, $logger);

        $migrationDirectory = (string) dirname(__DIR__, 2) . '/database/migrations';
        self::$migrationRunner = new MigrationRunner(self::$db, $migrationDirectory);
        self::$migrationRunner->migrate();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$db?->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (self::$db !== null) {
            try {
                self::$db->rollBack();
            } catch (\Throwable) {
                // Ignore rollback errors if transaction already closed by a test.
            }
        }

        parent::tearDown();
    }

    protected function db(): DBALWrapper
    {
        if (self::$db === null) {
            throw new \RuntimeException('DBALWrapper has not been initialized.');
        }

        return self::$db;
    }
}
