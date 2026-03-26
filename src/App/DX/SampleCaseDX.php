<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ValidationException;

/**
 * SampleCaseDX
 *
 * Developer "Hello World" reference implementation for DXController lifecycle.
 * Stages:
 *  INTAKE -> REVIEW -> PENDING_APPROVAL -> RESOLVED
 */
class SampleCaseDX extends DXController
{
    /**
     * @var array<string, mixed>
     */
    private array $dirtyState = [];

    private string $stage = 'INTAKE';

    public function preProcess(): void
    {
        $this->dirtyState = $this->getDirtyState();

        $action = (string) ($this->requestData['action'] ?? 'load');
        if ($action === 'submit_intake') {
            $title = (string) ($this->dirtyState['case_title'] ?? '');
            if (trim($title) === '') {
                throw new ValidationException('Validation failed.', [
                    'case_title' => ['Case Title is required.'],
                ]);
            }
        }

        if (isset($this->dirtyState['stage']) && is_string($this->dirtyState['stage'])) {
            $this->stage = $this->dirtyState['stage'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFlow(): array
    {
        $caseId = $this->getCaseId() ?? 'new-case';
        $caseReference = (string) ($this->dirtyState['case_reference'] ?? 'CASE-DEMO-0001');
        $statusLabel = $this->stageToStatusLabel($this->stage);

        $this->setData([
            'case_id' => $caseId,
            'case_reference' => 'Case Reference: ' . $caseReference,
            'case_status_label' => 'Case Status: ' . $statusLabel,
            'stage_label' => $this->stageLabel($this->stage),
            'owner_display_name' => 'Owner: Demo Analyst',
            'sla_due_label' => 'SLA: Due in 2 days',
        ]);

        $this->setNextAssignmentInfo($this->buildNextAssignmentInfo($this->stage));
        $this->setConfirmationNote([
            'message' => $this->confirmationMessageFor($this->stage),
            'variant' => 'info',
            'action_required' => null,
        ]);

        return $this->buildUiResourcesForStage($this->stage);
    }

    public function postProcess(): void
    {
        // Reserved for side effects in real implementations:
        // - dispatching jobs
        // - emitting audit events
        // - webhook triggers
    }

    private function stageToStatusLabel(string $stage): string
    {
        return match ($stage) {
            'INTAKE' => 'Intake In Progress',
            'REVIEW' => 'Under Review',
            'PENDING_APPROVAL' => 'Pending Approval',
            'RESOLVED' => 'Resolved',
            default => 'Unknown Stage',
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'INTAKE' => 'Stage 1 of 4: Intake',
            'REVIEW' => 'Stage 2 of 4: Review',
            'PENDING_APPROVAL' => 'Stage 3 of 4: Pending Approval',
            'RESOLVED' => 'Stage 4 of 4: Resolved',
            default => 'Stage: Unknown',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNextAssignmentInfo(string $stage): array
    {
        $steps = [
            ['label' => 'Intake', 'key' => 'intake', 'status' => 'pending'],
            ['label' => 'Review', 'key' => 'review', 'status' => 'pending'],
            ['label' => 'Approval', 'key' => 'approval', 'status' => 'pending'],
            ['label' => 'Resolution', 'key' => 'resolution', 'status' => 'pending'],
        ];

        $index = match ($stage) {
            'INTAKE' => 0,
            'REVIEW' => 1,
            'PENDING_APPROVAL' => 2,
            'RESOLVED' => 3,
            default => 0,
        };

        for ($i = 0; $i < count($steps); $i++) {
            if ($i < $index) {
                $steps[$i]['status'] = 'completed';
            } elseif ($i === $index) {
                $steps[$i]['status'] = 'active';
            }
        }

        return [
            'steps' => $steps,
            'current_step_index' => $index,
            'is_final_step' => $index === 3,
            'next_action_label' => $index === 3 ? 'Close Case' : 'Continue',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUiResourcesForStage(string $stage): array
    {
        return match ($stage) {
            'INTAKE' => [
                [
                    'component_type' => 'section_header',
                    'key' => 'intake_header',
                    'label' => 'Case Intake',
                    'required_permission' => null,
                ],
                [
                    'component_type' => 'text_input',
                    'key' => 'case_title',
                    'label' => 'Case Title',
                    'required_permission' => 'case:update',
                    'validation' => ['required' => true, 'min_length' => 5],
                    'value' => $this->dirtyState['case_title'] ?? null,
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'submit_intake',
                    'label' => 'Submit Intake',
                    'action' => 'submit_intake',
                    'required_permission' => 'case:update',
                ],
            ],
            'REVIEW' => [
                [
                    'component_type' => 'section_header',
                    'key' => 'review_header',
                    'label' => 'Case Review',
                    'required_permission' => null,
                ],
                [
                    'component_type' => 'textarea',
                    'key' => 'review_notes',
                    'label' => 'Review Notes',
                    'required_permission' => 'case:update',
                    'validation' => ['required' => true, 'min_length' => 20],
                    'value' => $this->dirtyState['review_notes'] ?? null,
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'submit_review',
                    'label' => 'Submit Review',
                    'action' => 'submit_review',
                    'required_permission' => 'case:update',
                ],
            ],
            'PENDING_APPROVAL' => [
                [
                    'component_type' => 'section_header',
                    'key' => 'approval_header',
                    'label' => 'Pending Approval',
                    'required_permission' => null,
                ],
                [
                    'component_type' => 'display_text',
                    'key' => 'approval_info',
                    'label' => 'Approval Required: Manager decision pending.',
                    'required_permission' => 'case:approve',
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'approve_case',
                    'label' => 'Approve Case',
                    'action' => 'approve_case',
                    'required_permission' => 'case:approve',
                ],
            ],
            'RESOLVED' => [
                [
                    'component_type' => 'section_header',
                    'key' => 'resolved_header',
                    'label' => 'Case Resolved',
                    'required_permission' => null,
                ],
                [
                    'component_type' => 'display_text',
                    'key' => 'resolved_info',
                    'label' => 'Resolution Completed Successfully.',
                    'required_permission' => null,
                ],
            ],
            default => [],
        };
    }

    private function confirmationMessageFor(string $stage): ?string
    {
        return match ($stage) {
            'INTAKE' => 'Intake step loaded.',
            'REVIEW' => 'Review step ready.',
            'PENDING_APPROVAL' => 'Awaiting approval action.',
            'RESOLVED' => 'Case has been resolved.',
            default => null,
        };
    }
}
