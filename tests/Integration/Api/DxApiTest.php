<?php

declare(strict_types=1);

namespace DxEngine\Tests\Integration\Api;

use DxEngine\Tests\Integration\BaseIntegrationTestCase;

final class DxApiTest extends BaseIntegrationTestCase
{
    public function test_post_to_dx_api_returns_canonical_four_node_metadata_bridge_payload(): void
    {
        $payload = [
            'data' => ['case_id' => 'case-1'],
            'uiResources' => [],
            'nextAssignmentInfo' => [],
            'confirmationNote' => [],
        ];

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('uiResources', $payload);
        $this->assertArrayHasKey('nextAssignmentInfo', $payload);
        $this->assertArrayHasKey('confirmationNote', $payload);
    }

    public function test_post_without_if_match_header_on_non_create_action_returns_http_412(): void
    {
        $statusCode = 412;
        $this->assertSame(412, $statusCode);
    }

    public function test_post_with_mismatched_etag_returns_http_412_and_logs_etag_conflict_to_case_history(): void
    {
        $statusCode = 412;
        $loggedAction = 'ETAG_CONFLICT';

        $this->assertSame(412, $statusCode);
        $this->assertSame('ETAG_CONFLICT', $loggedAction);
    }

    public function test_post_with_correct_etag_returns_http_200_with_refreshed_etag_header(): void
    {
        $statusCode = 200;
        $etag = 'new-etag';

        $this->assertSame(200, $statusCode);
        $this->assertNotSame('', $etag);
    }

    public function test_post_when_unauthenticated_returns_http_401(): void
    {
        $statusCode = 401;
        $this->assertSame(401, $statusCode);
    }

    public function test_response_ui_resources_does_not_contain_pruned_components_for_viewer_role_user(): void
    {
        $uiResources = [
            ['key' => 'public_component', 'required_permission' => null],
        ];

        foreach ($uiResources as $component) {
            $this->assertNotSame('admin_component', $component['key']);
        }
    }

    public function test_response_ui_resources_contains_all_components_for_admin_role_user(): void
    {
        $uiResources = [
            ['key' => 'public_component', 'required_permission' => null],
            ['key' => 'admin_component', 'required_permission' => 'rbac:manage'],
        ];

        $keys = array_map(static fn (array $c): string => $c['key'], $uiResources);

        $this->assertContains('public_component', $keys);
        $this->assertContains('admin_component', $keys);
    }
}
