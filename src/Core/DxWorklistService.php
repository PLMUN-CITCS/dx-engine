<?php

declare(strict_types=1);

namespace DxEngine\Core;

use DxEngine\Core\Contracts\GuardInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class DxWorklistService
{
    public function __construct(
        private readonly DBALWrapper $dbal,
        private readonly GuardInterface $guard,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPersonalWorklist(string $userId, array $filters = []): array
    {
        $sql = 'SELECT a.*, c.case_reference, c.status AS case_status, c.priority
                FROM dx_assignments a
                INNER JOIN dx_cases c ON c.id = a.case_id
                WHERE a.status = ? AND a.assigned_to_user = ?';
        $params = ['active', $userId];

        if (!empty($filters['status'])) {
            $sql .= ' AND c.status = ?';
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['deadline_before'])) {
            $sql .= ' AND a.deadline_at <= ?';
            $params[] = (string) $filters['deadline_before'];
        }

        if (!empty($filters['priority'])) {
            $sql .= ' AND c.priority = ?';
            $params[] = (int) $filters['priority'];
        }

        $sql .= ' ORDER BY a.deadline_at ASC, c.priority ASC';
        return $this->dbal->select($sql, $params);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGroupQueue(string $roleName, array $filters = []): array
    {
        $sql = 'SELECT a.*, c.case_reference, c.status AS case_status, c.priority
                FROM dx_assignments a
                INNER JOIN dx_cases c ON c.id = a.case_id
                WHERE a.status = ? AND a.assigned_to_role = ?';
        $params = ['pending', $roleName];

        if (!empty($filters['case_status'])) {
            $sql .= ' AND c.status = ?';
            $params[] = (string) $filters['case_status'];
        }

        if (!empty($filters['deadline_before'])) {
            $sql .= ' AND a.deadline_at <= ?';
            $params[] = (string) $filters['deadline_before'];
        }

        if (!empty($filters['priority'])) {
            $sql .= ' AND c.priority = ?';
            $params[] = (int) $filters['priority'];
        }

        $sql .= ' ORDER BY c.priority ASC, a.created_at ASC';
        return $this->dbal->select($sql, $params);
    }

    public function claimAssignment(string $assignmentId, string $userId): bool
    {
        return $this->dbal->transactional(function () use ($assignmentId, $userId): bool {
            $assignment = $this->dbal->selectOne(
                'SELECT id, case_id, status, assigned_to_user
                 FROM dx_assignments
                 WHERE id = ?',
                [$assignmentId]
            );

            if ($assignment === null) {
                return false;
            }

            $currentlyAssignedTo = (string) ($assignment['assigned_to_user'] ?? '');
            $status = (string) ($assignment['status'] ?? '');

            if ($currentlyAssignedTo !== '' || $status !== 'pending') {
                return false;
            }

            $affected = $this->dbal->update(
                'dx_assignments',
                [
                    'assigned_to_user' => $userId,
                    'status' => 'active',
                    'started_at' => date('Y-m-d H:i:s'),
                ],
                ['id' => $assignmentId, 'status' => 'pending']
            );

            if ($affected < 1) {
                return false;
            }

            $this->logEvent(
                (string) ($assignment['case_id'] ?? ''),
                'ASSIGNMENT_CLAIMED',
                $userId,
                ['assignment_id' => $assignmentId],
                $assignmentId
            );

            return true;
        });
    }

    public function releaseAssignment(string $assignmentId, string $userId): bool
    {
        return $this->dbal->transactional(function () use ($assignmentId, $userId): bool {
            $assignment = $this->dbal->selectOne(
                'SELECT id, case_id, status, assigned_to_user
                 FROM dx_assignments
                 WHERE id = ?',
                [$assignmentId]
            );

            if ($assignment === null) {
                return false;
            }

            $currentAssignee = (string) ($assignment['assigned_to_user'] ?? '');
            if ($currentAssignee === '') {
                return false;
            }

            $user = $this->guard->user();
            $roles = $user?->getAuthRoles() ?? [];
            $isAdmin = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);

            if ($currentAssignee !== $userId && !$isAdmin) {
                return false;
            }

            $affected = $this->dbal->update(
                'dx_assignments',
                [
                    'assigned_to_user' => null,
                    'status' => 'pending',
                    'started_at' => null,
                ],
                ['id' => $assignmentId]
            );

            if ($affected < 1) {
                return false;
            }

            $this->logEvent(
                (string) ($assignment['case_id'] ?? ''),
                'ASSIGNMENT_RELEASED',
                $userId,
                ['assignment_id' => $assignmentId],
                $assignmentId
            );

            return true;
        });
    }

    /**
     * @param array<string, mixed> $details
     */
    public function logEvent(
        string $caseId,
        string $action,
        string $actorId,
        array $details = [],
        ?string $assignmentId = null
    ): void {
        try {
            $this->dbal->insert('dx_case_history', [
                'id' => uniqid('hist_', true),
                'case_id' => $caseId,
                'assignment_id' => $assignmentId,
                'actor_id' => $actorId,
                'action' => $action,
                'from_status' => null,
                'to_status' => null,
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
                'e_tag_at_time' => '',
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            $this->getLogger()->warning('Failed to append dx_case_history event', [
                'case_id' => $caseId,
                'action' => $action,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCaseHistory(string $caseId): array
    {
        return $this->dbal->select(
            'SELECT *
             FROM dx_case_history
             WHERE case_id = ?
             ORDER BY occurred_at DESC',
            [$caseId]
        );
    }

    /**
     * @return array<string, int>
     */
    public function getAssignmentSummary(string $userId): array
    {
        $activeRow = $this->dbal->selectOne(
            'SELECT COUNT(*) AS cnt
             FROM dx_assignments
             WHERE assigned_to_user = ? AND status = ?',
            [$userId, 'active']
        );

        $overdueRow = $this->dbal->selectOne(
            'SELECT COUNT(*) AS cnt
             FROM dx_assignments
             WHERE assigned_to_user = ?
               AND status = ?
               AND deadline_at IS NOT NULL
               AND deadline_at < ?',
            [$userId, 'active', date('Y-m-d H:i:s')]
        );

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $dueTodayRow = $this->dbal->selectOne(
            'SELECT COUNT(*) AS cnt
             FROM dx_assignments
             WHERE assigned_to_user = ?
               AND status = ?
               AND deadline_at BETWEEN ? AND ?',
            [$userId, 'active', $todayStart, $todayEnd]
        );

        return [
            'my_active' => (int) ($activeRow['cnt'] ?? 0),
            'my_overdue' => (int) ($overdueRow['cnt'] ?? 0),
            'my_due_today' => (int) ($dueTodayRow['cnt'] ?? 0),
        ];
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
