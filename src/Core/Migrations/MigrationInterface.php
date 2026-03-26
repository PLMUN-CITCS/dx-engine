<?php

declare(strict_types=1);

namespace DxEngine\Core\Migrations;

use DxEngine\Core\DBALWrapper;

interface MigrationInterface
{
    public function up(DBALWrapper $db): void;

    public function down(DBALWrapper $db): void;

    public function getVersion(): string;
}
