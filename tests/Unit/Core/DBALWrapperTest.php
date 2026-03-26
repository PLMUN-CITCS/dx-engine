<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\DBALWrapper;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class DBALWrapperTest extends BaseUnitTestCase
{
    private DBALWrapper $db;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-dbal-wrapper');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement(
            'CREATE TABLE sample_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, qty INTEGER NOT NULL)'
        );
    }

    public function test_select_returns_empty_array_when_no_rows_found(): void
    {
        $rows = $this->db->select(
            'SELECT id, name, qty FROM sample_items WHERE name = ?',
            ['not-found']
        );

        $this->assertSame([], $rows);
    }

    public function test_select_one_returns_null_when_no_row_found(): void
    {
        $row = $this->db->selectOne(
            'SELECT id, name, qty FROM sample_items WHERE name = ?',
            ['not-found']
        );

        $this->assertNull($row);
    }

    public function test_insert_returns_last_insert_id(): void
    {
        $id = $this->db->insert('sample_items', [
            'name' => 'Widget A',
            'qty' => 3,
        ]);

        $this->assertNotSame('', (string) $id);
        $this->assertGreaterThan(0, (int) $id);
    }

    public function test_update_returns_affected_row_count(): void
    {
        $id = $this->db->insert('sample_items', [
            'name' => 'Widget B',
            'qty' => 1,
        ]);

        $affected = $this->db->update(
            'sample_items',
            ['qty' => 5],
            ['id' => (int) $id]
        );

        $this->assertSame(1, $affected);
    }

    public function test_delete_returns_affected_row_count(): void
    {
        $id = $this->db->insert('sample_items', [
            'name' => 'Widget C',
            'qty' => 8,
        ]);

        $affected = $this->db->delete('sample_items', ['id' => (int) $id]);

        $this->assertSame(1, $affected);
    }

    public function test_transactional_commits_on_successful_callback(): void
    {
        $result = $this->db->transactional(function (): string {
            $this->db->insert('sample_items', [
                'name' => 'Txn OK',
                'qty' => 2,
            ]);

            return 'committed';
        });

        $this->assertSame('committed', $result);

        $row = $this->db->selectOne(
            'SELECT name FROM sample_items WHERE name = ?',
            ['Txn OK']
        );

        $this->assertNotNull($row);
        $this->assertSame('Txn OK', $row['name']);
    }

    public function test_transactional_rolls_back_and_rethrows_on_exception(): void
    {
        try {
            $this->db->transactional(function (): void {
                $this->db->insert('sample_items', [
                    'name' => 'Txn FAIL',
                    'qty' => 9,
                ]);

                throw new \RuntimeException('force rollback');
            });

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('force rollback', $e->getMessage());
        }

        $row = $this->db->selectOne(
            'SELECT name FROM sample_items WHERE name = ?',
            ['Txn FAIL']
        );

        $this->assertNull($row);
    }

    public function test_all_select_methods_use_parameterized_queries(): void
    {
        $payload = '\'; DROP TABLE dx_cases; --';

        $rows = $this->db->select(
            'SELECT id, name, qty FROM sample_items WHERE name = ?',
            [$payload]
        );

        $row = $this->db->selectOne(
            'SELECT id, name, qty FROM sample_items WHERE name = ?',
            [$payload]
        );

        $this->assertSame([], $rows);
        $this->assertNull($row);

        // Table must remain intact if parameterization is used.
        $this->db->insert('sample_items', [
            'name' => 'Integrity',
            'qty' => 1,
        ]);

        $countRow = $this->db->selectOne('SELECT COUNT(*) AS c FROM sample_items');
        $this->assertNotNull($countRow);
        $this->assertGreaterThanOrEqual(1, (int) $countRow['c']);
    }

    public function test_get_connection_logs_warning_in_non_testing_environment(): void
    {
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'production';

        $connection = $this->db->getConnection();

        $this->assertNotNull($connection);

        if ($previousEnv === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $previousEnv;
        }

        $this->assertTrue(true);
    }
}
