<?php

declare(strict_types=1);

namespace DxEngine\App\Models;

use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;

final class AssignmentModel extends DataModel
{
    public function __construct(DBALWrapper $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'dx_assignments';
    }

    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'caseId' => ['column' => 'case_id', 'type' => 'string'],
            'assignmentType' => ['column' => 'assignment_type', 'type' => 'string'],
            'stepName' => ['column' => 'step_name', 'type' => 'string'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'assignedToUser' => ['column' => 'assigned_to_user', 'type' => 'string'],
            'assignedToRole' => ['column' => 'assigned_to_role', 'type' => 'string'],
            'instructions' => ['column' => 'instructions', 'type' => 'string'],
            'formSchemaKey' => ['column' => 'form_schema_key', 'type' => 'string'],
            'deadlineAt' => ['column' => 'deadline_at', 'type' => 'datetime'],
            'startedAt' => ['column' => 'started_at', 'type' => 'datetime'],
            'completedAt' => ['column' => 'completed_at', 'type' => 'datetime'],
            'completedBy' => ['column' => 'completed_by', 'type' => 'string'],
            'completionData' => ['column' => 'completion_data', 'type' => 'json'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
        ];
    }

    public function findActiveByCase(string $caseId): ?static
    {
        $rows = $this->db->select(
            'SELECT * FROM dx_assignments WHERE case_id = :caseId AND status = :status ORDER BY created_at DESC',
            [
                'caseId' => $caseId,
                'status' => 'active',
            ]
        );

        if ($rows === []) {
            return null;
        }

        return static::hydrate($rows[0]);
    }

    /**
     * @return array<int, static>
     */
    public function findByAssignee(string $userId, string $status = 'active'): array
    {
        $rows = $this->db->select(
            'SELECT * FROM dx_assignments WHERE assigned_to_user = :userId AND status = :status ORDER BY created_at DESC',
            [
                'userId' => $userId,
                'status' => $status,
            ]
        );

        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    /**
     * @return array<int, static>
     */
    public function findByRole(string $roleName, string $status = 'pending'): array
    {
        $rows = $this->db->select(
            'SELECT * FROM dx_assignments WHERE assigned_to_role = :roleName AND status = :status ORDER BY created_at DESC',
            [
                'roleName' => $roleName,
                'status' => $status,
            ]
        );

        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $completionData
     */
    public function completeAssignment(string $assignmentId, string $userId, array $completionData): bool
    {
        return $this->db->transactional(function () use ($assignmentId, $userId, $completionData): bool {
            $affected = $this->db->update('dx_assignments', [
                'status' => 'completed',
                'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'completed_by' => $userId,
                'completion_data' => json_encode($completionData, JSON_THROW_ON_ERROR),
            ], [
                'id' => $assignmentId,
            ]);

            return $affected > 0;
        });
    }

    protected static function newInstance(): static
    {
        throw new \RuntimeException('AssignmentModel::newInstance() requires a DBALWrapper-backed factory binding.');
    }
}
