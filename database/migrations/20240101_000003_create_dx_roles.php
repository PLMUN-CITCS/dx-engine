<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxRoles20240101_000003 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_roles', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('display_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('is_system', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name'], 'uidx_dx_roles_name');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_roles');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000003';
    }
}
