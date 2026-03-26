<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxPermissions20240101_000004 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_permissions', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('key', 'string', ['length' => 150, 'notnull' => true]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('category', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['key'], 'uidx_dx_permissions_key');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_permissions');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000004';
    }
}
