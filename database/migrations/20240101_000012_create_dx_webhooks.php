<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxWebhooks20240101_000012 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_webhooks', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 150, 'notnull' => true]);
            $table->addColumn('url', 'text', ['notnull' => true]);
            $table->addColumn('event_type', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('secret_key', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('headers', 'text', ['notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['notnull' => true, 'default' => true]);
            $table->addColumn('last_triggered_at', 'datetime', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['event_type'], 'idx_webhooks_event_type');
            $table->addIndex(['is_active'], 'idx_webhooks_is_active');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_webhooks');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000012';
    }
}
