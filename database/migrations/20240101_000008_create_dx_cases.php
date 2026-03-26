<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxCases20240101_000008 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_cases', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('case_type', 'string', ['length' => 150, 'notnull' => true]);
            $table->addColumn('case_reference', 'string', ['length' => 50, 'notnull' => true]);
            $table->addColumn('status', 'string', ['length' => 50, 'notnull' => true]);
            $table->addColumn('stage', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('current_assignment_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('owner_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('created_by', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('updated_by', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('e_tag', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('locked_by', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('locked_at', 'datetime', ['notnull' => false]);
            $table->addColumn('resolved_at', 'datetime', ['notnull' => false]);
            $table->addColumn('sla_due_at', 'datetime', ['notnull' => false]);
            $table->addColumn('priority', 'smallint', ['notnull' => true, 'default' => 2]);
            $table->addColumn('case_data', 'text', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['case_reference'], 'uidx_dx_cases_case_reference');

            $table->addForeignKeyConstraint('dx_users', ['owner_id'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('dx_users', ['created_by'], ['id'], ['onDelete' => 'RESTRICT']);
            $table->addForeignKeyConstraint('dx_users', ['updated_by'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('dx_users', ['locked_by'], ['id'], ['onDelete' => 'SET NULL']);

            $table->addIndex(['status'], 'idx_cases_status');
            $table->addIndex(['case_type'], 'idx_cases_case_type');
            $table->addIndex(['owner_id'], 'idx_cases_owner_id');
            $table->addIndex(['sla_due_at'], 'idx_cases_sla_due_at');
            $table->addIndex(['priority'], 'idx_cases_priority');
            $table->addIndex(['case_reference'], 'idx_cases_case_reference');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_cases');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000008';
    }
}
