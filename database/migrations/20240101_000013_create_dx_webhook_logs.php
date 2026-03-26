<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxWebhookLogs20240101_000013 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_webhook_logs', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('webhook_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('case_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('job_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('http_status', 'smallint', ['notnull' => false]);
            $table->addColumn('response_body', 'text', ['notnull' => false]);
            $table->addColumn('attempt_number', 'smallint', ['notnull' => true]);
            $table->addColumn('duration_ms', 'integer', ['notnull' => false]);
            $table->addColumn('attempted_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('dx_webhooks', ['webhook_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('dx_cases', ['case_id'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('dx_jobs', ['job_id'], ['id'], ['onDelete' => 'SET NULL']);
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_webhook_logs');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000013';
    }
}
