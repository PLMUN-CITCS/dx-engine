<?php

declare(strict_types=1);

namespace DxEngine\Tests\Feature;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\LayoutService;
use DxEngine\Tests\Integration\BaseIntegrationTestCase;

final class WorkDashboardTest extends BaseIntegrationTestCase
{
    public function test_dashboard_renders_personal_worklist_data_table_for_all_authenticated_users(): void
    {
        $payload = [
            'data' => [],
            'uiResources' => [
                [
                    'component_type' => 'data_table',
                    'key' => 'my_active_assignments',
                    'label' => 'My Active Assignments',
                    'required_permission' => null,
                ],
            ],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $service = new LayoutService($this->makeGuardWithPermissions([]));
        $pruned = $service->prunePayload($payload);

        $this->assertCount(1, $pruned['uiResources']);
        $this->assertSame('my_active_assignments', $pruned['uiResources'][0]['key']);
    }

    public function test_dashboard_renders_group_queue_data_table_for_user_with_worklist_claim_permission(): void
    {
        $payload = [
            'data' => [],
            'uiResources' => [
                [
                    'component_type' => 'data_table',
                    'key' => 'group_queue',
                    'label' => 'Group Queue',
                    'required_permission' => 'worklist:claim',
                ],
            ],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $service = new LayoutService($this->makeGuardWithPermissions(['worklist:claim']));
        $pruned = $service->prunePayload($payload);

        $this->assertCount(1, $pruned['uiResources']);
        $this->assertSame('group_queue', $pruned['uiResources'][0]['key']);
    }

    public function test_dashboard_omits_group_queue_data_table_for_role_viewer_user(): void
    {
        $payload = [
            'data' => [],
            'uiResources' => [
                [
                    'component_type' => 'data_table',
                    'key' => 'my_active_assignments',
                    'label' => 'My Active Assignments',
                    'required_permission' => null,
                ],
                [
                    'component_type' => 'data_table',
                    'key' => 'group_queue',
                    'label' => 'Group Queue',
                    'required_permission' => 'worklist:claim',
                ],
            ],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $service = new LayoutService($this->makeGuardWithPermissions([]));
        $pruned = $service->prunePayload($payload);

        $keys = array_map(
            static fn (array $component): string => (string) ($component['key'] ?? ''),
            $pruned['uiResources']
        );

        $this->assertContains('my_active_assignments', $keys);
        $this->assertNotContains('group_queue', $keys);
    }

    public function test_dashboard_claim_action_delegates_to_worklist_service_claim_assignment(): void
    {
        $jobId = 'dash-assignment-1';
        $this->db()->insert('dx_assignments', [
            'id' => $jobId,
            'case_id' => 'case-1',
            'assignment_type' => 'UserTask',
            'step_name' => 'Review',
            'status' => 'pending',
            'assigned_to_user' => null,
            'assigned_to_role' => 'ROLE_OPERATOR',
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->db()->update('dx_assignments', [
            'assigned_to_user' => 'user-1',
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $jobId,
            'status' => 'pending',
        ]);

        $this->assertSame(1, $updated);

        $row = $this->db()->selectOne(
            'SELECT assigned_to_user, status FROM dx_assignments WHERE id = ?',
            [$jobId]
        );

        $this->assertSame('user-1', $row['assigned_to_user']);
        $this->assertSame('active', $row['status']);
    }

    public function test_dashboard_polling_fetch_returns_refreshed_worklist_data_on_second_call(): void
    {
        $userId = 'dash-user-2';
        $this->db()->insert('dx_assignments', [
            'id' => 'poll-a1',
            'case_id' => 'case-2',
            'assignment_type' => 'UserTask',
            'step_name' => 'Step 1',
            'status' => 'active',
            'assigned_to_user' => $userId,
            'assigned_to_role' => null,
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => null,
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $first = $this->db()->select(
            'SELECT id FROM dx_assignments WHERE assigned_to_user = ? AND status = ?',
            [$userId, 'active']
        );
        $this->assertCount(1, $first);

        $this->db()->insert('dx_assignments', [
            'id' => 'poll-a2',
            'case_id' => 'case-3',
            'assignment_type' => 'UserTask',
            'step_name' => 'Step 2',
            'status' => 'active',
            'assigned_to_user' => $userId,
            'assigned_to_role' => null,
            'instructions' => null,
            'form_schema_key' => null,
            'deadline_at' => null,
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'completed_by' => null,
            'completion_data' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $second = $this->db()->select(
            'SELECT id FROM dx_assignments WHERE assigned_to_user = ? AND status = ?',
            [$userId, 'active']
        );

        $this->assertCount(2, $second);
    }

    private function makeGuardWithPermissions(array $permissions): GuardInterface
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthId')->willReturn('user-1');
        $user->method('getAuthPermissions')->willReturn($permissions);
        $user->method('getAuthRoles')->willReturn(['ROLE_VIEWER']);
        $user->method('getAuthEmail')->willReturn('user@example.com');
        $user->method('isActive')->willReturn(true);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($user);

        return $guard;
    }
}
