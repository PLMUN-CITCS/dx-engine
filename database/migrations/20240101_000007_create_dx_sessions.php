<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxSessions20240101_000007 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_sessions', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 128, 'notnull' => true]);
            $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('payload', 'text', ['notnull' => true]);
            $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => true]);
            $table->addColumn('user_agent', 'text', ['notnull' => false]);
            $table->addColumn('last_activity', 'integer', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('dx_users', ['user_id'], ['id'], ['onDelete' => 'SET NULL']);

            $table->addIndex(['user_id'], 'idx_sessions_user_id');
            $table->addIndex(['last_activity'], 'idx_sessions_last_activity');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_sessions');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000007';
    }
}
