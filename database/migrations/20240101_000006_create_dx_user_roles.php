<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxUserRoles20240101_000006 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_user_roles', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('role_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('context_type', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('context_id', 'string', ['length' => 150, 'notnull' => false]);
            $table->addColumn('granted_by', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('granted_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['user_id', 'role_id', 'context_type', 'context_id']);
            $table->addForeignKeyConstraint('dx_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_roles', ['role_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_users', ['granted_by'], ['id'], ['onDelete' => 'SET NULL']);

            $table->addIndex(['user_id'], 'idx_user_roles_user_id');
            $table->addIndex(['role_id'], 'idx_user_roles_role_id');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_user_roles');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000006';
    }
}
