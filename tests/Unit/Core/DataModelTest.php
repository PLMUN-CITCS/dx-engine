<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class DataModelTest extends BaseUnitTestCase
{
    private DBALWrapper $db;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-data-model');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement(
            'CREATE TABLE test_entities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL
            )'
        );

        TestEntityModel::setDb($this->db);
    }

    public function test_fill_correctly_maps_camel_case_attributes_via_field_map(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Alpha',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('Alpha', $model->toArray()['displayName']);
        $this->assertSame('ACTIVE', $model->toArray()['status']);
    }

    public function test_fill_ignores_keys_not_in_field_map(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Beta',
            'status' => 'ACTIVE',
            'unexpected' => 'IGNORED',
        ]);

        $array = $model->toArray();
        $this->assertArrayNotHasKey('unexpected', $array);
    }

    public function test_to_array_returns_all_properties_keyed_by_php_property_name(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Gamma',
            'status' => 'INACTIVE',
        ]);

        $this->assertSame(
            [
                'id' => null,
                'displayName' => 'Gamma',
                'status' => 'INACTIVE',
            ],
            $model->toArray()
        );
    }

    public function test_to_database_row_uses_column_names_as_keys(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Delta',
            'status' => 'ACTIVE',
        ]);

        $row = $model->toDatabaseRow();

        $this->assertArrayHasKey('display_name', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertSame('Delta', $row['display_name']);
    }

    public function test_hydrate_returns_correct_model_class_instance(): void
    {
        $hydrated = TestEntityModel::hydrate([
            'id' => 9,
            'display_name' => 'Hydrated',
            'status' => 'ACTIVE',
        ]);

        $this->assertInstanceOf(TestEntityModel::class, $hydrated);
        $this->assertSame(9, $hydrated->toArray()['id']);
    }

    public function test_save_calls_dbal_insert_when_primary_key_is_unset(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Insert Me',
            'status' => 'ACTIVE',
        ]);

        $ok = $model->save();

        $this->assertTrue($ok);
        $this->assertNotNull($model->toArray()['id']);
    }

    public function test_save_calls_dbal_update_when_primary_key_is_set(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Original',
            'status' => 'ACTIVE',
        ]);
        $model->save();

        $model->fill([
            'displayName' => 'Updated',
            'status' => 'INACTIVE',
        ]);

        $ok = $model->save();

        $this->assertTrue($ok);

        $found = TestEntityModel::find((int) $model->toArray()['id']);
        $this->assertNotNull($found);
        $this->assertSame('Updated', $found->toArray()['displayName']);
    }

    public function test_before_save_hook_is_called_before_dbal_write(): void
    {
        $model = TestEntityModel::newTestModel();
        $model->fill([
            'displayName' => 'Hook Test',
            'status' => 'ACTIVE',
        ]);

        $model->save();

        $this->assertTrue($model->beforeCalled);
        $this->assertNotNull($model->beforeTimestamp);
    }

    public function test_after_save_hook_is_called_after_successful_dbal_write(): void
    {
        $model = TestEntityModel::newTestModel();
        $model->fill([
            'displayName' => 'After Hook Test',
            'status' => 'ACTIVE',
        ]);

        $model->save();

        $this->assertTrue($model->afterCalled);
        $this->assertNotNull($model->afterTimestamp);
    }

    public function test_delete_calls_dbal_delete_with_primary_key_criteria(): void
    {
        $model = TestEntityModel::newTestModel()->fill([
            'displayName' => 'Delete Me',
            'status' => 'ACTIVE',
        ]);
        $model->save();

        $id = (int) $model->toArray()['id'];
        $deleted = $model->delete();

        $this->assertTrue($deleted);
        $this->assertNull(TestEntityModel::find($id));
    }
}

final class TestEntityModel extends DataModel
{
    public bool $beforeCalled = false;
    public bool $afterCalled = false;
    public ?float $beforeTimestamp = null;
    public ?float $afterTimestamp = null;

    private static ?DBALWrapper $testDb = null;

    public static function setDb(DBALWrapper $db): void
    {
        self::$testDb = $db;
    }

    public static function newTestModel(): self
    {
        return self::newInstance();
    }

    public function table(): string
    {
        return 'test_entities';
    }

    public function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'integer'],
            'displayName' => ['column' => 'display_name', 'type' => 'string'],
            'status' => ['column' => 'status', 'type' => 'string'],
        ];
    }

    protected static function newInstance(): static
    {
        if (self::$testDb === null) {
            throw new \RuntimeException('DB not configured for TestEntityModel.');
        }

        return new self(self::$testDb);
    }

    protected function beforeSave(): void
    {
        $this->beforeCalled = true;
        $this->beforeTimestamp = microtime(true);
    }

    protected function afterSave(): void
    {
        $this->afterCalled = true;
        $this->afterTimestamp = microtime(true);
    }
}
