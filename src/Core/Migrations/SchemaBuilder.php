<?php

declare(strict_types=1);

namespace DxEngine\Core\Migrations;

use Closure;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use DxEngine\Core\DBALWrapper;

final class SchemaBuilder
{
    private DBALWrapper $db;
    private Schema $fromSchema;
    private Schema $toSchema;

    public function __construct(DBALWrapper $db)
    {
        $this->db = $db;
        $this->fromSchema = $db->getSchemaManager()->introspectSchema();
        $this->toSchema = clone $this->fromSchema;
    }

    public function createTable(string $name, Closure $blueprint): void
    {
        if ($this->toSchema->hasTable($name)) {
            return;
        }

        $table = $this->toSchema->createTable($name);
        $blueprint($table);
    }

    public function dropTable(string $name): void
    {
        if (!$this->toSchema->hasTable($name)) {
            return;
        }

        $this->toSchema->dropTable($name);
    }

    public function alterTable(string $name, Closure $blueprint): void
    {
        if (!$this->toSchema->hasTable($name)) {
            return;
        }

        $table = $this->toSchema->getTable($name);
        $blueprint($table);
    }

    public function hasTable(string $name): bool
    {
        return $this->toSchema->hasTable($name);
    }

    public function hasColumn(string $tableName, string $columnName): bool
    {
        if (!$this->toSchema->hasTable($tableName)) {
            return false;
        }

        return $this->toSchema->getTable($tableName)->hasColumn($columnName);
    }

    /**
     * @param array<int, string> $columns
     */
    public function addIndex(string $tableName, array $columns, bool $unique = false, ?string $indexName = null): void
    {
        if (!$this->toSchema->hasTable($tableName)) {
            return;
        }

        $table = $this->toSchema->getTable($tableName);
        $name = $indexName ?? $this->buildIndexName($tableName, $columns, $unique);

        if ($unique) {
            if (!$table->hasIndex($name)) {
                $table->addUniqueIndex($columns, $name);
            }

            return;
        }

        if (!$table->hasIndex($name)) {
            $table->addIndex($columns, $name);
        }
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        if (!$this->toSchema->hasTable($tableName)) {
            return;
        }

        $table = $this->toSchema->getTable($tableName);
        if ($table->hasIndex($indexName)) {
            $table->dropIndex($indexName);
        }
    }

    public function execute(): void
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compareSchemas($this->fromSchema, $this->toSchema);

        $platform = $this->db->getPlatform();
        $sqlStatements = $schemaDiff->toSaveSql($platform);

        foreach ($sqlStatements as $sql) {
            $this->db->executeStatement($sql);
        }

        $this->fromSchema = $this->db->getSchemaManager()->introspectSchema();
        $this->toSchema = clone $this->fromSchema;
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildIndexName(string $tableName, array $columns, bool $unique): string
    {
        $prefix = $unique ? 'uidx_' : 'idx_';
        return strtolower($prefix . $tableName . '_' . implode('_', $columns));
    }
}
