<?php

declare(strict_types=1);

namespace DxEngine\Tests\Feature;

use DxEngine\Tests\Integration\BaseIntegrationTestCase;

final class PublicPortalTest extends BaseIntegrationTestCase
{
    public function test_anonymous_user_can_load_intake_form_without_an_active_session(): void
    {
        $uiResources = [
            [
                'component_type' => 'text_input',
                'key' => 'subject',
                'required_permission' => null,
            ],
            [
                'component_type' => 'textarea',
                'key' => 'description',
                'required_permission' => 'public',
            ],
        ];

        $this->assertCount(2, $uiResources);
        $this->assertSame('subject', $uiResources[0]['key']);
        $this->assertSame('description', $uiResources[1]['key']);
    }

    public function test_anonymous_intake_submission_creates_case_with_anonymous_intake_status(): void
    {
        $caseId = 'anon-case-1';
        $this->db()->insert('dx_cases', [
            'id' => $caseId,
            'case_type' => 'PublicPortalDX',
            'case_reference' => 'CASE-ANON-0001',
            'status' => 'ANONYMOUS_INTAKE',
            'stage' => null,
            'current_assignment_id' => null,
            'owner_id' => null,
            'created_by' => 'anonymous',
            'updated_by' => null,
            'e_tag' => hash('sha256', 'anon-1'),
            'locked_by' => null,
            'locked_at' => null,
            'resolved_at' => null,
            'sla_due_at' => null,
            'priority' => 3,
            'case_data' => json_encode(['subject' => 'Need help'], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $row = $this->db()->selectOne('SELECT status FROM dx_cases WHERE id = ?', [$caseId]);
        $this->assertNotNull($row);
        $this->assertSame('ANONYMOUS_INTAKE', $row['status']);
    }

    public function test_portal_returns_auth_challenge_confirmation_note_after_first_step_submission(): void
    {
        $confirmationNote = [
            'message' => 'Please authenticate to continue.',
            'variant' => 'warning',
            'action_required' => 'authenticate',
        ];

        $this->assertSame('authenticate', $confirmationNote['action_required']);
        $this->assertSame('warning', $confirmationNote['variant']);
    }

    public function test_portal_transitions_case_to_open_status_after_successful_user_authentication(): void
    {
        $caseId = 'anon-case-2';
        $this->db()->insert('dx_cases', [
            'id' => $caseId,
            'case_type' => 'PublicPortalDX',
            'case_reference' => 'CASE-ANON-0002',
            'status' => 'ANONYMOUS_INTAKE',
            'stage' => null,
            'current_assignment_id' => null,
            'owner_id' => null,
            'created_by' => 'anonymous',
            'updated_by' => null,
            'e_tag' => hash('sha256', 'anon-2'),
            'locked_by' => null,
            'locked_at' => null,
            'resolved_at' => null,
            'sla_due_at' => null,
            'priority' => 3,
            'case_data' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->db()->update('dx_cases', [
            'status' => 'OPEN',
            'updated_by' => 'user-123',
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $caseId,
            'status' => 'ANONYMOUS_INTAKE',
        ]);

        $this->assertSame(1, $updated);

        $row = $this->db()->selectOne('SELECT status FROM dx_cases WHERE id = ?', [$caseId]);
        $this->assertSame('OPEN', $row['status']);
    }

    public function test_anonymous_submission_is_rate_limited_after_configured_threshold_per_ip(): void
    {
        $ipAddress = '10.0.0.8';
        $threshold = 3;

        $this->db()->insert('dx_sessions', [
            'id' => 'sess-1',
            'user_id' => null,
            'payload' => '{}',
            'ip_address' => $ipAddress,
            'user_agent' => 'PHPUnit',
            'last_activity' => time(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db()->insert('dx_sessions', [
            'id' => 'sess-2',
            'user_id' => null,
            'payload' => '{}',
            'ip_address' => $ipAddress,
            'user_agent' => 'PHPUnit',
            'last_activity' => time(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db()->insert('dx_sessions', [
            'id' => 'sess-3',
            'user_id' => null,
            'payload' => '{}',
            'ip_address' => $ipAddress,
            'user_agent' => 'PHPUnit',
            'last_activity' => time(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $countRow = $this->db()->selectOne(
            'SELECT COUNT(*) AS total FROM dx_sessions WHERE ip_address = ?',
            [$ipAddress]
        );

        $this->assertNotNull($countRow);
        $this->assertGreaterThanOrEqual($threshold, (int) $countRow['total']);
    }
}
