<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;

class WorkDashboardDX extends DXController
{
    private string $userId = '';
    private array $userRoles = [];
    private ?string $requestedRole = null;

    public function preProcess(): void
    {
        $user = $this->getCurrentUser();
        $this->userId = (string) $user->getAuthId();
        $this->userRoles = $user->getAuthRoles();

        $dirtyState = $this->getDirtyState();
        $this->requestedRole = isset($dirtyState['queue_role']) ? (string) $dirtyState['queue_role'] : null;
    }

    public function getFlow(): array
    {
        $personal = $this->worklistService->getPersonalWorklist($this->userId);
        $summary = $this->worklistService->getAssignmentSummary($this->userId);

        $selectedRole = $this->resolveQueueRole();
        $groupQueue = $selectedRole !== null
            ? $this->worklistService->getGroupQueue($selectedRole)
            : [];

        $personalRows = array_map([$this, 'mapAssignmentRow'], $personal);
        $queueRows = array_map([$this, 'mapAssignmentRow'], $groupQueue);

        $uiResources = [
            [
                'component_type' => 'section_header',
                'key' => 'dashboard_my_work_header',
                'label' => 'My Active Assignments',
            ],
            [
                'component_type' => 'data_table',
                'key' => 'dashboard_my_work_table',
                'rows' => $personalRows,
            ],
            [
                'component_type' => 'section_header',
                'key' => 'dashboard_queue_header',
                'label' => 'Group Queue',
                'required_permission' => 'worklist:claim',
            ],
            [
                'component_type' => 'data_table',
                'key' => 'dashboard_queue_table',
                'rows' => $queueRows,
                'required_permission' => 'worklist:claim',
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'dashboard_claim_btn',
                'label' => 'Claim Assignment',
                'action' => 'claim_assignment',
                'required_permission' => 'worklist:claim',
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'dashboard_release_btn',
                'label' => 'Release Assignment',
                'action' => 'release_assignment',
            ],
            [
                'component_type' => 'badge',
                'key' => 'dashboard_badge_overdue',
                'label' => 'Overdue: ' . (string) ($summary['my_overdue'] ?? 0),
                'variant' => 'danger',
            ],
            [
                'component_type' => 'badge',
                'key' => 'dashboard_badge_due_today',
                'label' => 'Due Today: ' . (string) ($summary['my_due_today'] ?? 0),
                'variant' => 'warning',
            ],
            [
                'component_type' => 'badge',
                'key' => 'dashboard_badge_upcoming',
                'label' => 'Upcoming: ' . $this->computeUpcoming($summary),
                'variant' => 'info',
            ],
        ];

        $this->setData([
            'user_id' => $this->userId,
            'dashboard_title' => 'Work Dashboard',
            'my_active_count' => 'My Active: ' . count($personalRows),
            'my_overdue_count' => 'Overdue: ' . (string) ($summary['my_overdue'] ?? 0),
            'my_due_today_count' => 'Due Today: ' . (string) ($summary['my_due_today'] ?? 0),
            'my_upcoming_count' => 'Upcoming: ' . $this->computeUpcoming($summary),
            'selected_queue_role' => $selectedRole ?? 'N/A',
        ]);

        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'My Work', 'key' => 'my_work', 'status' => 'active'],
                ['label' => 'Group Queue', 'key' => 'group_queue', 'status' => 'pending'],
                ['label' => 'Execution', 'key' => 'execution', 'status' => 'pending'],
            ],
            'current_step_index' => 0,
            'is_final_step' => false,
            'next_action_label' => 'Refresh Dashboard',
        ]);

        $this->setConfirmationNote([
            'message' => 'Dashboard refreshed with latest assignment data.',
            'variant' => 'info',
            'action_required' => null,
        ]);

        return $uiResources;
    }

    public function postProcess(): void
    {
        // Dashboard is read-centric; no side effects required.
    }

    private function resolveQueueRole(): ?string
    {
        if ($this->requestedRole !== null && in_array($this->requestedRole, $this->userRoles, true)) {
            return $this->requestedRole;
        }

        return $this->userRoles[0] ?? null;
    }

    private function mapAssignmentRow(array $row): array
    {
        return [
            'Assignment ID' => (string) ($row['id'] ?? ''),
            'Case ID' => (string) ($row['case_id'] ?? ''),
            'Step Name' => (string) ($row['step_name'] ?? ''),
            'Status' => ucfirst((string) ($row['status'] ?? 'pending')),
            'Assigned To' => (string) (($row['assigned_to_user'] ?? '') ?: ($row['assigned_to_role'] ?? 'Unassigned')),
            'Deadline' => (string) (($row['deadline_at'] ?? '') ?: 'Not set'),
        ];
    }

    private function computeUpcoming(array $summary): string
    {
        $active = (int) ($summary['my_active'] ?? 0);
        $overdue = (int) ($summary['my_overdue'] ?? 0);
        $today = (int) ($summary['my_due_today'] ?? 0);

        return (string) max(0, $active - $overdue - $today);
    }
}
