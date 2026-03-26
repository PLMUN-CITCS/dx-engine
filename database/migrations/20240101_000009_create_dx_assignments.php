<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxAssignments20240101_000009 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_assignments', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('case_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('assignment_type', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('step_name', 'string', ['length' => 150, 'notnull' => true]);
            $table->addColumn('status', 'string', ['length' => 50, 'notnull' => true]);
            $table->addColumn('assigned_to_user', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('assigned_to_role', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('instructions', 'text', ['notnull' => false]);
            $table->addColumn('form_schema_key', 'string', ['length' => 150, 'notnull' => false]);
            $table->addColumn('deadline_at', 'datetime', ['notnull' => false]);
            $table->addColumn('started_at', 'datetime', ['notnull' => false]);
            $table->addColumn('completed_at', 'datetime', ['notnull' => false]);
            $table->addColumn('completed_by', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('completion_data', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('dx_cases', ['case_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_users', ['assigned_to_user'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('dx_users', ['completed_by'], ['id'], ['onDelete' => 'SET NULL']);

            $table->addIndex(['case_id'], 'idx_assignments_case_id');
            $table->addIndex(['status'], 'idx_assignments_status');
            $table->addIndex(['assigned_to_user'], 'idx_assignments_assigned_to_user');
            $table->addIndex(['assigned_to_role'], 'idx_assignments_assigned_to_role');
        });

        $schema->execute();
        $this->addDeferredCurrentAssignmentForeignKey($db);
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_assignments');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000009';
    }

    private function addDeferredCurrentAssignmentForeignKey(DBALWrapper $db): void
    {
        $schemaManager = $db->getSchemaManager();
        $tableDetails = $schemaManager->introspectTable('dx_cases');

        if ($tableDetails->hasForeignKey('fk_dx_cases_current_assignment')) {
            return;
        }

        $schema = new SchemaBuilder($db);
        $schema->alterTable('dx_cases', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addForeignKeyConstraint(
                'dx_assignments',
                ['current_assignment_id'],
                ['id'],
                ['onDelete' => 'SET NULL'],
                'fk_dx_cases_current_assignment'
            );
        });
        $schema->execute();
    }
}
