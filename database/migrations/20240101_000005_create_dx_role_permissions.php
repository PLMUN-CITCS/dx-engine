<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxRolePermissions20240101_000005 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_role_permissions', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('role_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('permission_id', 'string', ['length' => 36, 'notnull' => true]);

            $table->setPrimaryKey(['role_id', 'permission_id']);
            $table->addForeignKeyConstraint('dx_roles', ['role_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_permissions', ['permission_id'], ['id'], ['onDelete' => 'CASCADE']);
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_role_permissions');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000005';
    }
}
