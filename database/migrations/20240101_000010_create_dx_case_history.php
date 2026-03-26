<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxCaseHistory20240101_000010 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_case_history', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('case_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('assignment_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('actor_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('action', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('from_status', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('to_status', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('details', 'text', ['notnull' => false]);
            $table->addColumn('e_tag_at_time', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('occurred_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('dx_cases', ['case_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_assignments', ['assignment_id'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('dx_users', ['actor_id'], ['id'], ['onDelete' => 'SET NULL']);

            $table->addIndex(['case_id'], 'idx_history_case_id');
            $table->addIndex(['occurred_at'], 'idx_history_occurred_at');
            $table->addIndex(['actor_id'], 'idx_history_actor_id');
            $table->addIndex(['action'], 'idx_history_action');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_case_history');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000010';
    }
}
