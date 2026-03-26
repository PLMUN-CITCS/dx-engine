<?php

declare(strict_types=1);

namespace DxEngine\Core\Migrations;

use DxEngine\Core\DBALWrapper;

final class MigrationRunner
{
    private DBALWrapper $db;
    private string $migrationsPath;

    public function __construct(DBALWrapper $db, ?string $migrationsPath = null)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();

        foreach ($this->getPendingMigrations() as $migration) {
            $migration->up($this->db);

            $this->db->insert('dx_migrations', [
                'version' => $migration->getVersion(),
                'migration_class' => $migration::class,
                'applied_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function rollback(int $steps = 1): void
    {
        $this->ensureMigrationsTable();

        $applied = array_reverse($this->getAppliedMigrationRecords());
        $toRollback = array_slice($applied, 0, $steps);

        $available = $this->indexMigrationsByVersion($this->discoverMigrationFiles());

        foreach ($toRollback as $record) {
            $version = (string) $record['version'];

            if (!isset($available[$version])) {
                continue;
            }

            $migration = $available[$version];
            $migration->down($this->db);

            $this->db->delete('dx_migrations', ['version' => $version]);
        }
    }

    public function reset(): void
    {
        $this->ensureMigrationsTable();

        $applied = array_reverse($this->getAppliedMigrationRecords());
        $available = $this->indexMigrationsByVersion($this->discoverMigrationFiles());

        foreach ($applied as $record) {
            $version = (string) $record['version'];

            if (!isset($available[$version])) {
                continue;
            }

            $migration = $available[$version];
            $migration->down($this->db);

            $this->db->delete('dx_migrations', ['version' => $version]);
        }
    }

    /**
     * @return array<int, array{version: string, migration_class: string, applied: bool, applied_at: ?string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $migrations = $this->discoverMigrationFiles();
        $appliedRecords = $this->getAppliedMigrationRecords();

        $appliedMap = [];
        foreach ($appliedRecords as $record) {
            $appliedMap[(string) $record['version']] = $record;
        }

        $status = [];
        foreach ($migrations as $migration) {
            $version = $migration->getVersion();
            $record = $appliedMap[$version] ?? null;

            $status[] = [
                'version' => $version,
                'migration_class' => $migration::class,
                'applied' => $record !== null,
                'applied_at' => $record['applied_at'] ?? null,
            ];
        }

        return $status;
    }

    public function ensureMigrationsTable(): void
    {
        $schemaBuilder = new SchemaBuilder($this->db);

        if ($schemaBuilder->hasTable('dx_migrations')) {
            return;
        }

        $schemaBuilder->createTable('dx_migrations', function (\Doctrine\DBAL\Schema\Table $table): void {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('version', 'string', ['length' => 20]);
            $table->addColumn('migration_class', 'string', ['length' => 255]);
            $table->addColumn('applied_at', 'datetime');
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['version'], 'uidx_dx_migrations_version');
        });

        $schemaBuilder->execute();
    }

    /**
     * @return array<int, MigrationInterface>
     */
    public function getPendingMigrations(): array
    {
        $this->ensureMigrationsTable();

        $discovered = $this->discoverMigrationFiles();
        $applied = $this->getAppliedMigrations();
        $appliedSet = array_fill_keys($applied, true);

        return array_values(array_filter(
            $discovered,
            static fn(MigrationInterface $migration): bool => !isset($appliedSet[$migration->getVersion()])
        ));
    }

    /**
     * @return array<int, string>
     */
    public function getAppliedMigrations(): array
    {
        $this->ensureMigrationsTable();

        $rows = $this->db->select('SELECT version FROM dx_migrations ORDER BY version ASC');

        return array_map(static fn(array $row): string => (string) $row['version'], $rows);
    }

    /**
     * @return array<int, MigrationInterface>
     */
    public function discoverMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $migrations = [];

        foreach ($files as $file) {
            $declaredBefore = get_declared_classes();
            require_once $file;
            $declaredAfter = get_declared_classes();

            $newClasses = array_values(array_diff($declaredAfter, $declaredBefore));

            foreach ($newClasses as $className) {
                if (!is_subclass_of($className, MigrationInterface::class)) {
                    continue;
                }

                $instance = new $className();
                $migrations[] = $instance;
            }
        }

        usort(
            $migrations,
            static fn(MigrationInterface $a, MigrationInterface $b): int => strcmp($a->getVersion(), $b->getVersion())
        );

        return $migrations;
    }

    /**
     * @return array<int, array{version: string, migration_class: string, applied_at: string}>
     */
    private function getAppliedMigrationRecords(): array
    {
        return $this->db->select(
            'SELECT version, migration_class, applied_at FROM dx_migrations ORDER BY version ASC'
        );
    }

    /**
     * @param array<int, MigrationInterface> $migrations
     * @return array<string, MigrationInterface>
     */
    private function indexMigrationsByVersion(array $migrations): array
    {
        $indexed = [];

        foreach ($migrations as $migration) {
            $indexed[$migration->getVersion()] = $migration;
        }

        return $indexed;
    }
}
