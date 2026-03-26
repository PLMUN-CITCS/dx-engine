<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\Migrations\SchemaBuilder;

final class CreateDxUsers20240101_000002 implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);

        $schema->createTable('dx_users', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('username', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('email', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('password_hash', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('display_name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 20, 'notnull' => true, 'default' => 'active']);
            $table->addColumn('last_login_at', 'datetime', ['notnull' => false]);
            $table->addColumn('password_changed_at', 'datetime', ['notnull' => false]);
            $table->addColumn('failed_login_count', 'smallint', ['notnull' => true, 'default' => 0]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['username'], 'uidx_dx_users_username');
            $table->addUniqueIndex(['email'], 'uidx_dx_users_email');
            $table->addIndex(['email'], 'idx_users_email');
            $table->addIndex(['status'], 'idx_users_status');
            $table->addIndex(['username'], 'idx_users_username');
        });

        $schema->execute();
    }

    public function down(DBALWrapper $db): void
    {
        $schema = new SchemaBuilder($db);
        $schema->dropTable('dx_users');
        $schema->execute();
    }

    public function getVersion(): string
    {
        return '20240101_000002';
    }
}
