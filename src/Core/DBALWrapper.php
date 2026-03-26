<?php

declare(strict_types=1);

namespace DxEngine\Core;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use DxEngine\Core\Exceptions\DatabaseException;
use Psr\Log\LoggerInterface;

final class DBALWrapper
{
    private Connection $connection;
    private LoggerInterface $logger;
    private bool $isTestingEnvironment;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->isTestingEnvironment = (($config['env'] ?? ($_ENV['APP_ENV'] ?? 'production')) === 'testing');

        try {
            $this->connection = DriverManager::getConnection($config);
        } catch (DbalException $exception) {
            $this->logDatabaseError('Connection initialization failed', null, [], $exception);
            throw new DatabaseException('Database connection failed.', 0, $exception);
        }
    }

    public function select(string $sql, array $params = [], array $types = []): array
    {
        $this->logQuery($sql, $params);

        try {
            return $this->connection->fetchAllAssociative($sql, $params, $types);
        } catch (DbalException $exception) {
            $this->logDatabaseError('SELECT failed', $sql, $params, $exception);
            throw new DatabaseException('Failed to execute SELECT query.', 0, $exception);
        }
    }

    public function selectOne(string $sql, array $params = [], array $types = []): ?array
    {
        $this->logQuery($sql, $params);

        try {
            $row = $this->connection->fetchAssociative($sql, $params, $types);
            return $row !== false ? $row : null;
        } catch (DbalException $exception) {
            $this->logDatabaseError('SELECT ONE failed', $sql, $params, $exception);
            throw new DatabaseException('Failed to execute SELECT ONE query.', 0, $exception);
        }
    }

    public function insert(string $table, array $data, array $types = []): string|int
    {
        $this->logQuery('INSERT into ' . $table, $data);

        try {
            $this->connection->insert($table, $data, $types);
            return $this->connection->lastInsertId();
        } catch (DbalException $exception) {
            $this->logDatabaseError('INSERT failed', 'INSERT INTO ' . $table, $data, $exception);
            throw new DatabaseException('Failed to insert row.', 0, $exception);
        }
    }

    public function update(string $table, array $data, array $criteria, array $types = []): int
    {
        $this->logQuery('UPDATE ' . $table, ['data' => $data, 'criteria' => $criteria]);

        try {
            return $this->connection->update($table, $data, $criteria, $types);
        } catch (DbalException $exception) {
            $this->logDatabaseError('UPDATE failed', 'UPDATE ' . $table, ['data' => $data, 'criteria' => $criteria], $exception);
            throw new DatabaseException('Failed to update row(s).', 0, $exception);
        }
    }

    public function delete(string $table, array $criteria, array $types = []): int
    {
        $this->logQuery('DELETE ' . $table, $criteria);

        try {
            return $this->connection->delete($table, $criteria, $types);
        } catch (DbalException $exception) {
            $this->logDatabaseError('DELETE failed', 'DELETE FROM ' . $table, $criteria, $exception);
            throw new DatabaseException('Failed to delete row(s).', 0, $exception);
        }
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $this->logQuery($sql, $params);

        try {
            return $this->connection->executeStatement($sql, $params, $types);
        } catch (DbalException $exception) {
            $this->logDatabaseError('DML execution failed', $sql, $params, $exception);
            throw new DatabaseException('Failed to execute statement.', 0, $exception);
        }
    }

    public function beginTransaction(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (DbalException $exception) {
            $this->logDatabaseError('Begin transaction failed', null, [], $exception);
            throw new DatabaseException('Failed to begin transaction.', 0, $exception);
        }
    }

    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (DbalException $exception) {
            $this->logDatabaseError('Commit failed', null, [], $exception);
            throw new DatabaseException('Failed to commit transaction.', 0, $exception);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->connection->rollBack();
        } catch (DbalException $exception) {
            $this->logDatabaseError('Rollback failed', null, [], $exception);
            throw new DatabaseException('Failed to rollback transaction.', 0, $exception);
        }
    }

    public function transactional(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->rollBack();
            }

            throw $throwable;
        }
    }

    public function getSchemaManager(): AbstractSchemaManager
    {
        try {
            return $this->connection->createSchemaManager();
        } catch (DbalException $exception) {
            $this->logDatabaseError('Schema manager retrieval failed', null, [], $exception);
            throw new DatabaseException('Failed to retrieve schema manager.', 0, $exception);
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->connection->getDatabasePlatform()->quoteIdentifier($identifier);
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    /**
     * @internal Escape hatch for framework internals.
     */
    public function getConnection(): Connection
    {
        if (!$this->isTestingEnvironment) {
            $this->logger->warning('DBALWrapper::getConnection() escape hatch was called.', [
                'class' => self::class,
            ]);
        }

        return $this->connection;
    }

    private function logQuery(string $sql, array $params): void
    {
        $this->logger->debug('Executing SQL statement.', [
            'sql' => $sql,
            'params' => $this->redactSensitiveData($params),
        ]);
    }

    private function logDatabaseError(string $message, ?string $sql, array $params, \Throwable $throwable): void
    {
        $this->logger->error($message, [
            'sql' => $sql,
            'params' => $this->redactSensitiveData($params),
            'exception' => $throwable::class,
            'error' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }

    private function redactSensitiveData(array $params): array
    {
        $sensitiveKeys = ['password', 'password_hash', 'secret_key'];

        $redact = function (array $data) use (&$redact, $sensitiveKeys): array {
            $result = [];

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = $redact($value);
                    continue;
                }

                if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                    $result[$key] = '***REDACTED***';
                    continue;
                }

                $result[$key] = $value;
            }

            return $result;
        };

        return $redact($params);
    }
}
