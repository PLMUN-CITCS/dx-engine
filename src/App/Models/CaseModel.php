<?php

declare(strict_types=1);

namespace DxEngine\App\Models;

use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;

final class CaseModel extends DataModel
{
    public function __construct(DBALWrapper $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'dx_cases';
    }

    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'caseType' => ['column' => 'case_type', 'type' => 'string'],
            'caseReference' => ['column' => 'case_reference', 'type' => 'string'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'stage' => ['column' => 'stage', 'type' => 'string'],
            'currentAssignmentId' => ['column' => 'current_assignment_id', 'type' => 'string'],
            'ownerId' => ['column' => 'owner_id', 'type' => 'string'],
            'createdBy' => ['column' => 'created_by', 'type' => 'string'],
            'updatedBy' => ['column' => 'updated_by', 'type' => 'string'],
            'eTag' => ['column' => 'e_tag', 'type' => 'string'],
            'lockedBy' => ['column' => 'locked_by', 'type' => 'string'],
            'lockedAt' => ['column' => 'locked_at', 'type' => 'datetime'],
            'resolvedAt' => ['column' => 'resolved_at', 'type' => 'datetime'],
            'slaDueAt' => ['column' => 'sla_due_at', 'type' => 'datetime'],
            'priority' => ['column' => 'priority', 'type' => 'integer'],
            'caseData' => ['column' => 'case_data', 'type' => 'json'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
            'updatedAt' => ['column' => 'updated_at', 'type' => 'datetime'],
        ];
    }

    public function findByReference(string $caseRef): ?static
    {
        return static::findOneBy(['caseReference' => $caseRef]);
    }

    /**
     * @param array<int, string> $statuses
     * @return array<int, static>
     */
    public function findByCaseType(string $caseType, array $statuses = []): array
    {
        if ($statuses === []) {
            return static::findAll(['caseType' => $caseType]);
        }

        $statusPlaceholders = [];
        $params = ['caseType' => $caseType];

        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $statusPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = 'SELECT * FROM dx_cases WHERE case_type = :caseType AND status IN (' . implode(',', $statusPlaceholders) . ')';
        $rows = $this->db->select($sql, $params);

        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    /**
     * @return array<int, static>
     */
    public function findByOwner(string $userId): array
    {
        return static::findAll(['ownerId' => $userId]);
    }

    public function updateETag(string $caseId, string $newETag): bool
    {
        $affected = $this->db->update('dx_cases', ['e_tag' => $newETag], ['id' => $caseId]);

        return $affected > 0;
    }

    public function lockCase(string $caseId, string $userId): bool
    {
        return $this->db->transactional(function () use ($caseId, $userId): bool {
            $row = $this->db->selectOne(
                'SELECT locked_by FROM dx_cases WHERE id = :id',
                ['id' => $caseId]
            );

            if ($row === null) {
                return false;
            }

            $lockedBy = $row['locked_by'] ?? null;
            if ($lockedBy !== null && $lockedBy !== '' && $lockedBy !== $userId) {
                return false;
            }

            $affected = $this->db->update('dx_cases', [
                'locked_by' => $userId,
                'locked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $caseId,
            ]);

            return $affected > 0;
        });
    }

    public function unlockCase(string $caseId): bool
    {
        $affected = $this->db->update('dx_cases', [
            'locked_by' => null,
            'locked_at' => null,
        ], [
            'id' => $caseId,
        ]);

        return $affected > 0;
    }

    protected static function newInstance(): static
    {
        throw new \RuntimeException('CaseModel::newInstance() requires a DBALWrapper-backed factory binding.');
    }
}
