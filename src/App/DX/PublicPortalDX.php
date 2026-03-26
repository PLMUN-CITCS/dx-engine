<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid;

class PublicPortalDX extends DXController
{
    private array $requestState = [];
    private ?string $caseId = null;
    private string $ipAddress = '0.0.0.0';
    private bool $authenticated = false;
    private int $hourlyLimit = 5;

    public function preProcess(): void
    {
        // Public portal explicitly bypasses AuthMiddleware session enforcement.
        // Only soft-auth context is checked for transition after challenge.
        $this->requestState = $this->getDirtyState();
        $this->caseId = $this->getCaseId();

        $this->ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $this->hourlyLimit = (int) ($_ENV['PUBLIC_PORTAL_HOURLY_LIMIT'] ?? 5);

        // Rate limiting for anonymous submissions per IP/hour.
        if ($this->isAnonymousSubmissionAction()) {
            $this->enforceAnonymousRateLimit();
        }

        $this->authenticated = isset($_SESSION['user_id']) && (string) $_SESSION['user_id'] !== '';
    }

    public function getFlow(): array
    {
        // First load: no case id => public-only intake form.
        if ($this->caseId === null || $this->caseId === '') {
            return $this->buildInitialIntakePayload();
        }

        $case = $this->db->selectOne(
            'SELECT * FROM dx_cases WHERE id = ?',
            [$this->caseId]
        );

        if ($case === null) {
            throw new ValidationException('Public case record not found.');
        }

        $status = (string) ($case['status'] ?? 'ANONYMOUS_INTAKE');

        // First submission path: create partial case and request auth.
        if ($status === 'ANONYMOUS_INTAKE' && !$this->authenticated) {
            $this->setData([
                'case_id' => (string) $case['id'],
                'case_reference' => (string) ($case['case_reference'] ?? ''),
                'case_status_label' => 'Case Status: Anonymous Intake',
                'owner_display_name' => 'Owner: Authentication Required',
                'sla_due_label' => 'SLA Due: Pending Authentication',
                'stage_label' => 'Stage: Intake Authentication',
            ]);

            $this->setNextAssignmentInfo([
                'steps' => [
                    ['label' => 'Intake', 'key' => 'intake', 'status' => 'completed'],
                    ['label' => 'Authenticate', 'key' => 'authenticate', 'status' => 'active'],
                    ['label' => 'Open', 'key' => 'open', 'status' => 'pending'],
                ],
                'current_step_index' => 1,
                'is_final_step' => false,
                'next_action_label' => 'Authenticate to Continue',
            ]);

            $this->setConfirmationNote([
                'message' => 'Intake submitted. Please authenticate to continue this case.',
                'variant' => 'warning',
                'action_required' => 'authenticate',
            ]);

            return [
                [
                    'component_type' => 'alert_banner',
                    'key' => 'auth_required_notice',
                    'variant' => 'warning',
                    'label' => 'Authentication required to continue your submission.',
                ],
                [
                    'component_type' => 'display_text',
                    'key' => 'auth_required_case_ref',
                    'label' => 'Case Reference: ' . (string) ($case['case_reference'] ?? ''),
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'auth_required_button',
                    'label' => 'Proceed to Authentication',
                    'action' => 'authenticate',
                ],
            ];
        }

        // Post-authentication transition: move ANONYMOUS_INTAKE => OPEN
        if ($status === 'ANONYMOUS_INTAKE' && $this->authenticated) {
            $this->db->update(
                'dx_cases',
                [
                    'status' => 'OPEN',
                    'stage' => 'INITIATION',
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                ['id' => $this->caseId]
            );

            $status = 'OPEN';
        }

        $this->setData([
            'case_id' => (string) $case['id'],
            'case_reference' => (string) ($case['case_reference'] ?? ''),
            'case_status_label' => 'Case Status: ' . $status,
            'owner_display_name' => 'Owner: Portal User',
            'sla_due_label' => 'SLA Due: Standard',
            'stage_label' => 'Stage: Initiation',
        ]);

        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Intake', 'key' => 'intake', 'status' => 'completed'],
                ['label' => 'Authenticate', 'key' => 'authenticate', 'status' => 'completed'],
                ['label' => 'Open', 'key' => 'open', 'status' => 'active'],
            ],
            'current_step_index' => 2,
            'is_final_step' => false,
            'next_action_label' => 'Continue Workflow',
        ]);

        $this->setConfirmationNote([
            'message' => 'Authentication verified. Your case has been moved to OPEN.',
            'variant' => 'success',
            'action_required' => null,
        ]);

        return [
            [
                'component_type' => 'section_header',
                'key' => 'portal_open_header',
                'label' => 'Public Portal Case Progress',
            ],
            [
                'component_type' => 'display_text',
                'key' => 'portal_open_case_ref',
                'label' => 'Case Reference: ' . (string) ($case['case_reference'] ?? ''),
            ],
            [
                'component_type' => 'display_text',
                'key' => 'portal_open_status',
                'label' => 'Current Status: OPEN',
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'portal_open_continue',
                'label' => 'Continue',
                'action' => 'continue',
            ],
        ];
    }

    public function postProcess(): void
    {
        if ($this->isAnonymousSubmissionAction() && ($this->caseId === null || $this->caseId === '')) {
            $newCaseId = $this->createAnonymousIntakeCase();
            $this->caseId = $newCaseId;
        }
    }

    private function buildInitialIntakePayload(): array
    {
        $this->setData([
            'case_id' => '',
            'case_reference' => 'Will be generated after submission',
            'case_status_label' => 'Case Status: New Intake',
            'owner_display_name' => 'Owner: Public User',
            'sla_due_label' => 'SLA Due: Pending',
            'stage_label' => 'Stage: Intake',
        ]);

        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Intake', 'key' => 'intake', 'status' => 'active'],
                ['label' => 'Authenticate', 'key' => 'authenticate', 'status' => 'pending'],
                ['label' => 'Open', 'key' => 'open', 'status' => 'pending'],
            ],
            'current_step_index' => 0,
            'is_final_step' => false,
            'next_action_label' => 'Submit Intake',
        ]);

        $this->setConfirmationNote([
            'message' => null,
            'variant' => null,
            'action_required' => null,
        ]);

        // Public components only (no required_permission or required_permission: public).
        return [
            [
                'component_type' => 'section_header',
                'key' => 'public_portal_header',
                'label' => 'Submit a New Case',
            ],
            [
                'component_type' => 'text_input',
                'key' => 'requester_name',
                'label' => 'Your Name',
                'placeholder' => 'Enter your full name',
                'validation' => ['required' => true, 'min_length' => 2],
                'required_permission' => 'public',
            ],
            [
                'component_type' => 'email_input',
                'key' => 'requester_email',
                'label' => 'Email Address',
                'placeholder' => 'you@example.com',
                'validation' => ['required' => true, 'email' => true],
                'required_permission' => 'public',
            ],
            [
                'component_type' => 'textarea',
                'key' => 'request_details',
                'label' => 'Request Details',
                'placeholder' => 'Describe your request',
                'validation' => ['required' => true, 'min_length' => 20],
                'required_permission' => 'public',
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'submit_intake',
                'label' => 'Submit Intake',
                'action' => 'submit_intake',
                'required_permission' => 'public',
            ],
        ];
    }

    private function createAnonymousIntakeCase(): string
    {
        $caseId = Uuid::uuid4()->toString();
        $referenceSuffix = strtoupper(substr(str_replace('-', '', $caseId), 0, 8));
        $caseReference = 'CASE-' . $referenceSuffix;
        $now = date('Y-m-d H:i:s');

        $caseData = [
            'requester_name' => (string) ($this->requestState['requester_name'] ?? ''),
            'requester_email' => (string) ($this->requestState['requester_email'] ?? ''),
            'request_details' => (string) ($this->requestState['request_details'] ?? ''),
            'submitted_ip' => $this->ipAddress,
        ];

        $this->db->insert('dx_cases', [
            'id' => $caseId,
            'case_type' => 'PublicPortalDX',
            'case_reference' => $caseReference,
            'status' => 'ANONYMOUS_INTAKE',
            'stage' => 'INTAKE',
            'current_assignment_id' => null,
            'owner_id' => null,
            'created_by' => 'anonymous',
            'updated_by' => null,
            'e_tag' => hash_hmac('sha256', $caseId . microtime(true), (string) ($_ENV['APP_KEY'] ?? 'dx-engine')),
            'locked_by' => null,
            'locked_at' => null,
            'resolved_at' => null,
            'sla_due_at' => null,
            'priority' => 3,
            'case_data' => json_encode($caseData, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->db->insert('dx_case_history', [
            'id' => Uuid::uuid4()->toString(),
            'case_id' => $caseId,
            'assignment_id' => null,
            'actor_id' => null,
            'action' => 'CASE_CREATED',
            'from_status' => null,
            'to_status' => 'ANONYMOUS_INTAKE',
            'details' => json_encode(['source' => 'public_portal'], JSON_THROW_ON_ERROR),
            'e_tag_at_time' => '',
            'occurred_at' => $now,
        ]);

        return $caseId;
    }

    private function enforceAnonymousRateLimit(): void
    {
        $windowStart = date('Y-m-d H:i:s', time() - 3600);

        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS submission_count
             FROM dx_cases
             WHERE case_type = ?
               AND created_at >= ?
               AND case_data LIKE ?',
            ['PublicPortalDX', $windowStart, '%"submitted_ip":"' . $this->ipAddress . '"%']
        );

        $submissionCount = (int) ($row['submission_count'] ?? 0);
        if ($submissionCount >= $this->hourlyLimit) {
            throw new ValidationException('Anonymous submission limit reached for this IP. Please try again later.');
        }
    }

    private function isAnonymousSubmissionAction(): bool
    {
        $action = isset($this->requestState['action']) ? (string) $this->requestState['action'] : '';
        return in_array($action, ['submit_intake', 'create_case'], true);
    }
}
