<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxMigrations20240101_000001 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_migrations', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('version', 'string', ['length' => 20, 'notnull' => true]);
            $table->addColumn('migration_class', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('applied_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['version'], 'uidx_dx_migrations_version');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_migrations');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000001';
    }
}
