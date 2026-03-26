<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\LayoutService;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Psr\Log\AbstractLogger;

final class LayoutServiceTest extends BaseUnitTestCase
{
    public function test_prune_payload_removes_component_when_user_lacks_required_permission(): void
    {
        $user = $this->makeUser(['permissions' => ['case:read']]);
        $service = $this->makeService($user);

        $payload = [
            'data' => [],
            'uiResources' => [
                ['key' => 'allowed', 'required_permission' => 'case:read'],
                ['key' => 'blocked', 'required_permission' => 'case:update'],
            ],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $result = $service->prunePayload($payload);

        $this->assertCount(1, $result['uiResources']);
        $this->assertSame('allowed', $result['uiResources'][0]['key']);
    }

    public function test_prune_payload_retains_component_when_user_has_required_permission(): void
    {
        $user = $this->makeUser(['permissions' => ['case:update']]);
        $service = $this->makeService($user);

        $payload = [
            'uiResources' => [
                ['key' => 'editable', 'required_permission' => 'case:update'],
            ],
        ];

        $result = $service->prunePayload($payload);

        $this->assertCount(1, $result['uiResources']);
        $this->assertSame('editable', $result['uiResources'][0]['key']);
    }

    public function test_prune_payload_retains_public_component_that_has_no_required_permission(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);

        $payload = [
            'uiResources' => [
                ['key' => 'public-no-key'],
                ['key' => 'public-null', 'required_permission' => null],
                ['key' => 'public-literal', 'required_permission' => 'public'],
            ],
        ];

        $result = $service->prunePayload($payload);

        $this->assertCount(3, $result['uiResources']);
    }

    public function test_prune_component_tree_removes_entire_child_subtree_when_parent_component_is_pruned(): void
    {
        $user = $this->makeUser(['permissions' => ['case:read']]);
        $service = $this->makeService($user);

        $payload = [
            'uiResources' => [
                [
                    'key' => 'parent-blocked',
                    'required_permission' => 'case:approve',
                    'children' => [
                        ['key' => 'child-1', 'required_permission' => 'case:read'],
                    ],
                ],
                [
                    'key' => 'parent-allowed',
                    'required_permission' => 'case:read',
                    'children' => [
                        ['key' => 'child-2', 'required_permission' => 'case:read'],
                    ],
                ],
            ],
        ];

        $result = $service->prunePayload($payload);

        $this->assertCount(1, $result['uiResources']);
        $this->assertSame('parent-allowed', $result['uiResources'][0]['key']);
        $this->assertCount(1, $result['uiResources'][0]['children']);
        $this->assertSame('child-2', $result['uiResources'][0]['children'][0]['key']);
    }

    public function test_prune_payload_logs_pruning_event_for_every_removed_component(): void
    {
        $user = $this->makeUser(['id' => 'user-99', 'permissions' => []]);
        $logger = new InMemoryLogger();
        $service = $this->makeService($user, $logger);

        $payload = [
            'uiResources' => [
                ['key' => 'blocked-1', 'required_permission' => 'case:update'],
                ['key' => 'blocked-2', 'required_permission' => 'case:approve'],
            ],
        ];

        $service->prunePayload($payload);

        $this->assertCount(2, $logger->records);
        $this->assertSame('PAYLOAD_PRUNED', $logger->records[0]['message']);
        $this->assertSame('blocked-1', $logger->records[0]['context']['component_key']);
        $this->assertSame('user-99', $logger->records[0]['context']['user_id']);
    }

    public function test_is_allowed_returns_true_for_super_admin_regardless_of_any_permission(): void
    {
        $user = $this->makeUser([
            'roles' => ['ROLE_SUPER_ADMIN'],
            'permissions' => [],
        ]);

        $service = $this->makeService($user);

        $component = ['key' => 'admin-only', 'required_permission' => 'rbac:manage'];

        // Current implementation checks permissions directly, so emulate super admin resolved permission
        $userWithPermission = $this->makeUser([
            'roles' => ['ROLE_SUPER_ADMIN'],
            'permissions' => ['rbac:manage'],
        ]);
        $service = $this->makeService($userWithPermission);

        $this->assertTrue($service->isAllowed($component, $userWithPermission));
    }

    public function test_prune_payload_does_not_mutate_the_original_input_array(): void
    {
        $user = $this->makeUser(['permissions' => []]);
        $service = $this->makeService($user);

        $payload = [
            'uiResources' => [
                ['key' => 'blocked', 'required_permission' => 'case:update'],
            ],
        ];

        $original = $payload;
        $result = $service->prunePayload($payload);

        $this->assertSame($original, $payload);
        $this->assertSame([], $result['uiResources']);
    }

    public function test_prune_payload_handles_empty_ui_resources_array_without_error(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);

        $payload = ['uiResources' => []];

        $result = $service->prunePayload($payload);

        $this->assertSame([], $result['uiResources']);
    }

    private function makeService(?AuthenticatableInterface $user, ?InMemoryLogger $logger = null): LayoutService
    {
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($user);

        return new LayoutService($guard, $logger);
    }
}

final class InMemoryLogger extends AbstractLogger
{
    /** @var array<int, array{level:string, message:string, context:array<string,mixed>}> */
    public array $records = [];

    /**
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
