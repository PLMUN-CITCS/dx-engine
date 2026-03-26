<?php

declare(strict_types=1);

namespace DxEngine\Core;

abstract class DataModel
{
    protected DBALWrapper $db;

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    public function __construct(DBALWrapper $db)
    {
        $this->db = $db;
    }

    abstract protected function table(): string;

    /**
     * @return array<string, array{column: string, type: string}>
     */
    abstract protected function fieldMap(): array;

    public static function find(string|int $id): ?static
    {
        $instance = static::newInstance();
        $primaryKey = $instance->getPrimaryKey();
        $map = $instance->fieldMap();

        $column = $map[$primaryKey]['column'] ?? $primaryKey;

        $row = $instance->db->selectOne(
            sprintf('SELECT * FROM %s WHERE %s = :id', $instance->table(), $column),
            ['id' => $id]
        );

        if ($row === null) {
            return null;
        }

        return static::hydrate($row);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return array<int, static>
     */
    public static function findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $instance = static::newInstance();
        $fieldMap = $instance->fieldMap();

        $where = [];
        $params = [];

        foreach ($criteria as $property => $value) {
            $column = $fieldMap[$property]['column'] ?? $property;
            $paramKey = 'w_' . $property;
            $where[] = sprintf('%s = :%s', $column, $paramKey);
            $params[$paramKey] = $value;
        }

        $sql = sprintf('SELECT * FROM %s', $instance->table());

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if ($orderBy !== []) {
            $orderSegments = [];
            foreach ($orderBy as $property => $direction) {
                $column = $fieldMap[$property]['column'] ?? $property;
                $normalizedDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderSegments[] = sprintf('%s %s', $column, $normalizedDirection);
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderSegments);
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . (int) $offset;
        }

        $rows = $instance->db->select($sql, $params);

        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function findOneBy(array $criteria): ?static
    {
        $results = static::findAll($criteria, [], 1);

        return $results[0] ?? null;
    }

    public function save(): bool
    {
        $this->beforeSave();

        $primaryKey = $this->getPrimaryKey();
        $row = $this->toDatabaseRow();
        $primaryKeyColumn = $this->fieldMap()[$primaryKey]['column'] ?? $primaryKey;

        if (!isset($this->attributes[$primaryKey]) || $this->attributes[$primaryKey] === null || $this->attributes[$primaryKey] === '') {
            $insertData = $row;
            unset($insertData[$primaryKeyColumn]);

            $id = $this->db->insert($this->table(), $insertData);
            $this->attributes[$primaryKey] = $id;
        } else {
            $criteria = [$primaryKeyColumn => $this->attributes[$primaryKey]];
            $updateData = $row;
            unset($updateData[$primaryKeyColumn]);

            $this->db->update($this->table(), $updateData, $criteria);
        }

        $this->afterSave();

        return true;
    }

    public function delete(): bool
    {
        $primaryKey = $this->getPrimaryKey();

        if (!isset($this->attributes[$primaryKey])) {
            return false;
        }

        $primaryKeyColumn = $this->fieldMap()[$primaryKey]['column'] ?? $primaryKey;

        $affected = $this->db->delete($this->table(), [
            $primaryKeyColumn => $this->attributes[$primaryKey],
        ]);

        return $affected > 0;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        $fieldMap = $this->fieldMap();

        foreach ($attributes as $property => $value) {
            if (array_key_exists($property, $fieldMap)) {
                $this->attributes[$property] = $value;
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->fieldMap() as $property => $_meta) {
            $result[$property] = $this->attributes[$property] ?? null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabaseRow(): array
    {
        $row = [];

        foreach ($this->fieldMap() as $property => $meta) {
            $column = $meta['column'];
            $row[$column] = $this->attributes[$property] ?? null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): static
    {
        $instance = static::newInstance();
        $fieldMap = $instance->fieldMap();

        $attributes = [];
        foreach ($fieldMap as $property => $meta) {
            $column = $meta['column'];
            $attributes[$property] = $row[$column] ?? null;
        }

        $instance->fill($attributes);

        return $instance;
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }

    protected function beforeSave(): void
    {
    }

    protected function afterSave(): void
    {
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $fieldMap = $this->fieldMap();

        if (array_key_exists($name, $fieldMap)) {
            $this->attributes[$name] = $value;
        }
    }

    protected static function newInstance(): static
    {
        throw new \RuntimeException(sprintf(
            'Model %s must implement newInstance() to provide a DBALWrapper-backed instance.',
            static::class
        ));
    }
}
