<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxJobs20240101_000011 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_jobs', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('queue', 'string', ['length' => 100, 'notnull' => true, 'default' => 'default']);
            $table->addColumn('job_class', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('payload', 'text', ['notnull' => true]);
            $table->addColumn('status', 'string', ['length' => 20, 'notnull' => true]);
            $table->addColumn('attempts', 'smallint', ['notnull' => true, 'default' => 0]);
            $table->addColumn('max_attempts', 'smallint', ['notnull' => true, 'default' => 3]);
            $table->addColumn('priority', 'smallint', ['notnull' => true, 'default' => 5]);
            $table->addColumn('available_at', 'datetime', ['notnull' => true]);
            $table->addColumn('reserved_at', 'datetime', ['notnull' => false]);
            $table->addColumn('reserved_by', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('completed_at', 'datetime', ['notnull' => false]);
            $table->addColumn('failed_at', 'datetime', ['notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('error_trace', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['status', 'available_at'], 'idx_jobs_status_available');
            $table->addIndex(['queue', 'status'], 'idx_jobs_queue_status');
            $table->addIndex(['reserved_by'], 'idx_jobs_reserved_by');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_jobs');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000011';
    }
}
