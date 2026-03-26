<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\DxWorklistService;
use DxEngine\Core\Exceptions\ValidationException;

class WorkLifeCycleDX extends DXController
{
    private const STAGE_FLOW = [
        'INITIATION',
        'IN_PROGRESS',
        'PENDING_APPROVAL',
        'RESOLVED',
    ];

    private array $requestData = [];
    private array $caseRecord = [];
    private array $activeAssignments = [];
    private array $allAssignments = [];
    private ?string $lastAction = null;
    private string $caseId = '';
    private bool $isCreate = false;
    private array $pendingAuditEvents = [];

    public function preProcess(): void
    {
        $this->requestData = $this->getDirtyState();
        $this->caseId = (string) ($this->getCaseId() ?? '');
        $this->lastAction = isset($this->requestData['action']) ? (string) $this->requestData['action'] : null;

        if ($this->caseId === '') {
            $this->isCreate = true;
            return;
        }

        $case = $this->db->selectOne(
            'SELECT * FROM dx_cases WHERE id = ?',
            [$this->caseId]
        );

        if ($case === null) {
            throw new ValidationException('Case record was not found.');
        }

        $this->caseRecord = $case;
        $this->allAssignments = $this->db->select(
            'SELECT * FROM dx_assignments WHERE case_id = ? ORDER BY created_at ASC',
            [$this->caseId]
        );

        $this->activeAssignments = array_values(array_filter(
            $this->allAssignments,
            static fn (array $assignment): bool => in_array((string) ($assignment['status'] ?? ''), ['active', 'pending'], true)
        ));

        $this->enforceStageGating();
    }

    public function getFlow(): array
    {
        if ($this->isCreate) {
            return $this->buildCreateFlow();
        }

        $stage = (string) ($this->caseRecord['stage'] ?? self::STAGE_FLOW[0]);
        $status = (string) ($this->caseRecord['status'] ?? 'OPEN');
        $caseReference = (string) ($this->caseRecord['case_reference'] ?? 'N/A');
        $priority = (int) ($this->caseRecord['priority'] ?? 3);
        $slaDueAt = (string) ($this->caseRecord['sla_due_at'] ?? '');

        $activeRows = array_map(function (array $assignment): array {
            return [
                'Step' => (string) ($assignment['step_name'] ?? 'Unnamed Step'),
                'Type' => (string) ($assignment['assignment_type'] ?? 'UserTask'),
                'Status' => ucfirst((string) ($assignment['status'] ?? 'pending')),
                'Assignee' => (string) ($assignment['assigned_to_user'] ?: $assignment['assigned_to_role'] ?: 'Unassigned'),
                'Deadline' => (string) (($assignment['deadline_at'] ?? '') ?: 'Not set'),
            ];
        }, $this->activeAssignments);

        $historyRows = $this->db->select(
            'SELECT action, from_status, to_status, occurred_at
             FROM dx_case_history
             WHERE case_id = ?
             ORDER BY occurred_at DESC
             LIMIT 15',
            [$this->caseId]
        );

        $historyRows = array_map(static function (array $row): array {
            return [
                'Event' => (string) ($row['action'] ?? ''),
                'From' => (string) (($row['from_status'] ?? '') ?: '-'),
                'To' => (string) (($row['to_status'] ?? '') ?: '-'),
                'When' => (string) ($row['occurred_at'] ?? ''),
            ];
        }, $historyRows);

        $parallelAssignmentCount = count(array_filter(
            $this->allAssignments,
            static fn (array $a): bool =>
                in_array((string) ($a['status'] ?? ''), ['pending', 'active'], true)
                && (string) ($a['stage'] ?? '') === $stage
        ));

        $uiResources = [
            [
                'component_type' => 'section_header',
                'key' => 'wlc_header',
                'label' => 'Work Life Cycle Manager',
            ],
            [
                'component_type' => 'display_text',
                'key' => 'wlc_case_reference',
                'label' => 'Case Reference: ' . $caseReference,
            ],
            [
                'component_type' => 'badge',
                'key' => 'wlc_stage_badge',
                'label' => 'Stage: ' . str_replace('_', ' ', $stage),
                'variant' => 'primary',
            ],
            [
                'component_type' => 'badge',
                'key' => 'wlc_status_badge',
                'label' => 'Status: ' . $status,
                'variant' => 'info',
            ],
            [
                'component_type' => 'display_text',
                'key' => 'wlc_priority',
                'label' => 'Priority: P' . $priority,
            ],
            [
                'component_type' => 'display_text',
                'key' => 'wlc_sla',
                'label' => 'SLA Due: ' . ($slaDueAt !== '' ? $slaDueAt : 'Not set'),
            ],
            [
                'component_type' => 'display_text',
                'key' => 'wlc_parallel_note',
                'label' => 'Parallel Assignments in Current Stage: ' . $parallelAssignmentCount,
            ],
            [
                'component_type' => 'section_header',
                'key' => 'wlc_active_assignments_header',
                'label' => 'Current Stage Assignments',
            ],
            [
                'component_type' => 'data_table',
                'key' => 'wlc_active_assignments_table',
                'rows' => $activeRows,
            ],
            [
                'component_type' => 'section_header',
                'key' => 'wlc_history_header',
                'label' => 'Recent Audit Trail',
            ],
            [
                'component_type' => 'data_table',
                'key' => 'wlc_history_table',
                'rows' => $historyRows,
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'wlc_refresh',
                'label' => 'Refresh Case',
                'action' => 'refresh',
            ],
        ];

        $this->setData([
            'case_id' => $this->caseId,
            'case_reference' => $caseReference,
            'case_status_label' => 'Case Status: ' . $status,
            'stage_label' => 'Current Stage: ' . str_replace('_', ' ', $stage),
            'sla_due_label' => 'SLA Due: ' . ($slaDueAt !== '' ? $slaDueAt : 'Not set'),
            'owner_display_name' => 'Owner: ' . ((string) ($this->caseRecord['owner_id'] ?? 'Unassigned')),
            'parallel_assignment_label' => 'Parallel Assignments Open: ' . $parallelAssignmentCount,
        ]);

        $this->setNextAssignmentInfo($this->buildNextAssignmentInfo($stage));
        $this->setConfirmationNote([
            'message' => $this->buildConfirmationNote($stage),
            'variant' => 'info',
            'action_required' => null,
        ]);

        return $uiResources;
    }

    public function postProcess(): void
    {
        if ($this->isCreate) {
            return;
        }

        if ($this->lastAction === 'refresh' || $this->lastAction === null) {
            return;
        }

        foreach ($this->pendingAuditEvents as $event) {
            $this->worklistService->logEvent(
                $this->caseId,
                (string) ($event['action'] ?? 'STAGE_VIEWED'),
                (string) $this->getCurrentUser()->getAuthId(),
                $event['details'] ?? [],
                $event['assignment_id'] ?? null
            );
        }
    }

    private function buildCreateFlow(): array
    {
        $this->setData([
            'case_id' => '',
            'case_reference' => 'Case will be assigned after submission',
            'case_status_label' => 'Case Status: Draft',
            'stage_label' => 'Current Stage: Initiation',
            'owner_display_name' => 'Owner: Unassigned',
            'sla_due_label' => 'SLA Due: Not set',
        ]);

        $this->setNextAssignmentInfo([
            'steps' => $this->buildStepStatuses('INITIATION'),
            'current_step_index' => 0,
            'is_final_step' => false,
            'next_action_label' => 'Initiate Case',
        ]);

        $this->setConfirmationNote([
            'message' => 'Prepare case details and submit to initiate lifecycle.',
            'variant' => 'info',
            'action_required' => null,
        ]);

        return [
            [
                'component_type' => 'section_header',
                'key' => 'wlc_create_header',
                'label' => 'Start Work Lifecycle',
            ],
            [
                'component_type' => 'display_text',
                'key' => 'wlc_create_info',
                'label' => 'Create a new case to begin INITIATION stage.',
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'wlc_create_action',
                'label' => 'Initiate Case',
                'action' => 'create_case',
            ],
        ];
    }

    private function enforceStageGating(): void
    {
        $currentStage = (string) ($this->caseRecord['stage'] ?? self::STAGE_FLOW[0]);
        $currentIndex = array_search($currentStage, self::STAGE_FLOW, true);

        if ($currentIndex === false || $currentIndex === 0) {
            return;
        }

        $previousStage = self::STAGE_FLOW[$currentIndex - 1];

        $previousStageOpen = array_filter(
            $this->allAssignments,
            static fn (array $assignment): bool =>
                (string) ($assignment['stage'] ?? '') === $previousStage
                && in_array((string) ($assignment['status'] ?? ''), ['pending', 'active'], true)
        );

        if ($previousStageOpen !== []) {
            throw new ValidationException(
                sprintf(
                    'Stage gate violation: %s cannot start until %s assignments are completed.',
                    $currentStage,
                    $previousStage
                )
            );
        }
    }

    private function buildNextAssignmentInfo(string $stage): array
    {
        $stageIndex = array_search($stage, self::STAGE_FLOW, true);
        $stageIndex = $stageIndex === false ? 0 : $stageIndex;

        return [
            'steps' => $this->buildStepStatuses($stage),
            'current_step_index' => $stageIndex,
            'is_final_step' => $stage === 'RESOLVED',
            'next_action_label' => $stage === 'RESOLVED' ? 'Lifecycle Completed' : 'Advance Workflow',
        ];
    }

    private function buildStepStatuses(string $currentStage): array
    {
        $currentIndex = array_search($currentStage, self::STAGE_FLOW, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = [];
        foreach (self::STAGE_FLOW as $idx => $stage) {
            $status = 'pending';
            if ($idx < $currentIndex) {
                $status = 'completed';
            } elseif ($idx === $currentIndex) {
                $status = 'active';
            }

            $steps[] = [
                'label' => str_replace('_', ' ', $stage),
                'key' => strtolower($stage),
                'status' => $status,
            ];
        }

        return $steps;
    }

    private function buildConfirmationNote(string $stage): string
    {
        return match ($stage) {
            'INITIATION' => 'Initiation stage active. Complete intake tasks to move forward.',
            'IN_PROGRESS' => 'Case is in progress. Parallel assignments may run concurrently.',
            'PENDING_APPROVAL' => 'Awaiting approval completion before resolution can begin.',
            'RESOLVED' => 'Case has reached final stage and is fully resolved.',
            default => 'Workflow status refreshed.',
        };
    }
}
