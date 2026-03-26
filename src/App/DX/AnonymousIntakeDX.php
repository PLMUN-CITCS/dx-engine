<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ValidationException;
use DxEngine\Core\Middleware\RateLimitMiddleware;

/**
 * AnonymousIntakeDX
 *
 * Specialized DX controller for unauthenticated intake flow.
 * Returns public-only components and creates a partial case on first submit.
 */
class AnonymousIntakeDX extends DXController
{
    /**
     * @var array<string, mixed>
     */
    private array $dirtyState = [];

    private bool $submitted = false;

    private bool $authenticated = false;

    public function preProcess(): void
    {
        $this->dirtyState = $this->getDirtyState();

        // Enforce self-managed rate limiting; do not use AuthMiddleware here.
        $rateLimit = new RateLimitMiddleware(20, 60);
        $request = [
            'headers' => function_exists('getallheaders') ? (getallheaders() ?: []) : [],
            'server' => $_SERVER,
        ];
        $rateLimit->handle($request, static fn (array $_request): bool => true);

        $action = (string) ($this->requestData['action'] ?? 'load');
        if ($action === 'submit_intake') {
            $this->submitted = true;

            $subject = (string) ($this->dirtyState['subject'] ?? '');
            $description = (string) ($this->dirtyState['description'] ?? '');
            if (trim($subject) === '' || trim($description) === '') {
                throw new ValidationException('Validation failed.', [
                    'subject' => ['Subject is required.'],
                    'description' => ['Description is required.'],
                ]);
            }
        }

        $this->authenticated = (bool) ($this->requestData['authenticated'] ?? false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFlow(): array
    {
        if ($this->submitted && !$this->authenticated) {
            $partialCaseId = $this->createAnonymousIntakeCase();

            $this->setData([
                'case_id' => $partialCaseId,
                'case_status_label' => 'Case Status: ANONYMOUS_INTAKE',
                'stage_label' => 'Stage 1 of 1: Anonymous Intake',
            ]);

            $this->setNextAssignmentInfo([
                'steps' => [
                    ['label' => 'Intake', 'key' => 'intake', 'status' => 'completed'],
                    ['label' => 'Authenticate', 'key' => 'authenticate', 'status' => 'active'],
                ],
                'current_step_index' => 1,
                'is_final_step' => false,
                'next_action_label' => 'Authenticate',
            ]);

            $this->setConfirmationNote([
                'message' => 'Intake submitted. Authentication is required to continue.',
                'variant' => 'warning',
                'action_required' => 'authenticate',
            ]);

            return [
                [
                    'component_type' => 'alert_banner',
                    'key' => 'auth_required_banner',
                    'label' => 'Authentication required to continue your case.',
                    'variant' => 'warning',
                    'required_permission' => 'public',
                ],
            ];
        }

        if ($this->authenticated) {
            $this->setData([
                'case_status_label' => 'Case Status: OPEN',
                'stage_label' => 'Stage 1 of 2: Authenticated Processing',
            ]);

            $this->setNextAssignmentInfo([
                'steps' => [
                    ['label' => 'Intake', 'key' => 'intake', 'status' => 'completed'],
                    ['label' => 'Authenticated', 'key' => 'authenticated', 'status' => 'active'],
                ],
                'current_step_index' => 1,
                'is_final_step' => false,
                'next_action_label' => 'Continue',
            ]);

            $this->setConfirmationNote([
                'message' => 'Authentication successful. Your case is now OPEN.',
                'variant' => 'success',
                'action_required' => null,
            ]);

            return [
                [
                    'component_type' => 'section_header',
                    'key' => 'auth_step_header',
                    'label' => 'Continue Case Processing',
                    'required_permission' => 'public',
                ],
                [
                    'component_type' => 'display_text',
                    'key' => 'auth_step_info',
                    'label' => 'You can now proceed with the next workflow step.',
                    'required_permission' => 'public',
                ],
            ];
        }

        $this->setData([
            'case_status_label' => 'Case Status: Intake Draft',
            'stage_label' => 'Stage 1 of 1: Anonymous Intake',
        ]);

        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Intake', 'key' => 'intake', 'status' => 'active'],
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

        return [
            [
                'component_type' => 'section_header',
                'key' => 'anonymous_intake_header',
                'label' => 'Initiate Case',
                'required_permission' => 'public',
            ],
            [
                'component_type' => 'text_input',
                'key' => 'subject',
                'label' => 'Subject',
                'required_permission' => 'public',
                'validation' => ['required' => true, 'min_length' => 5],
                'value' => $this->dirtyState['subject'] ?? null,
            ],
            [
                'component_type' => 'textarea',
                'key' => 'description',
                'label' => 'Description',
                'required_permission' => 'public',
                'validation' => ['required' => true, 'min_length' => 15],
                'value' => $this->dirtyState['description'] ?? null,
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

    public function postProcess(): void
    {
        // Side effects (webhooks/jobs) can be attached here in future phases.
    }

    private function createAnonymousIntakeCase(): string
    {
        $caseId = 'anon_' . bin2hex(random_bytes(8));
        $payload = [
            'subject' => (string) ($this->dirtyState['subject'] ?? ''),
            'description' => (string) ($this->dirtyState['description'] ?? ''),
            'anonymous' => true,
        ];

        $this->dbal->insert('dx_cases', [
            'id' => $caseId,
            'case_type' => 'AnonymousIntakeDX',
            'case_reference' => 'CASE-ANON-' . strtoupper(substr($caseId, -6)),
            'status' => 'ANONYMOUS_INTAKE',
            'stage' => 'INTAKE',
            'current_assignment_id' => null,
            'owner_id' => null,
            'created_by' => 'anonymous',
            'updated_by' => null,
            'e_tag' => hash('sha256', $caseId . microtime(true)),
            'locked_by' => null,
            'locked_at' => null,
            'resolved_at' => null,
            'sla_due_at' => null,
            'priority' => 3,
            'case_data' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $caseId;
    }
}
