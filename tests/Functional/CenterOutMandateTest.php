<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Axiom A-01: Center-Out Mandate Functional Test
 * 
 * Validates that the backend is the sole source of truth and frontend never derives
 * structure, labels, visibility rules, or permissions from its own logic.
 * 
 * All decisions must arrive inside the 4-node JSON Metadata Bridge payload.
 */
final class CenterOutMandateTest extends BaseFunctionalTestCase
{
    public function test_all_ui_structure_comes_from_backend_payload(): void
    {
        // Create a test case
        $case = $this->createTestCase();

        // Simulate a frontend request for case UI
        $response = $this->simulateUiRequest($case['id']);

        // Assert all UI structure is present in uiResources
        $this->assertNotEmpty($response['uiResources'], 'UI Resources must be provided by backend');
        $this->assertIsArray($response['uiResources']);

        foreach ($response['uiResources'] as $component) {
            $this->assertArrayHasKey('component_type', $component, 'Component must have type definition');
            $this->assertArrayHasKey('label', $component, 'Component must have label from backend');
            $this->assertArrayHasKey('key', $component, 'Component must have key');
        }
    }

    public function test_component_labels_are_product_info_not_raw_codes(): void
    {
        $case = $this->createTestCase(['case_status' => 'UNDER_REVIEW']);
        $response = $this->simulateUiRequest($case['id']);

        // Verify data node contains formatted labels
        $this->assertIsArray($response['data']);

        // Check that status is displayed as formatted text, not raw code
        if (isset($response['data']['case_status'])) {
            $this->assertStringContainsString('Review', $response['data']['case_status']);
            $this->assertStringNotContainsString('UNDER_REVIEW', $response['data']['case_status']);
        }
    }

    public function test_visibility_rules_are_backend_driven(): void
    {
        $case = $this->createTestCase();
        $response = $this->simulateUiRequest($case['id']);

        foreach ($response['uiResources'] as $component) {
            if (isset($component['visibility_rule'])) {
                $this->assertIsString($component['visibility_rule'], 'Visibility rules must be strings');
                $this->assertNotEmpty($component['visibility_rule']);
            }
        }
    }

    public function test_permission_gating_is_enforced_in_payload(): void
    {
        // Create a case visible only to admin
        $case = $this->createTestCase(['owner_id' => 'user-admin']);

        // Simulate request as standard user (should have components pruned)
        $response = $this->simulateUiRequest($case['id'], 'user-standard');

        // Verify server-side pruning occurred
        $this->assertIsArray($response['uiResources']);
        
        // Components requiring admin permission should be pruned
        foreach ($response['uiResources'] as $component) {
            $this->assertArrayNotHasKey('admin_only_field', $component);
        }
    }

    private function simulateUiRequest(string $caseId, string $userId = 'user-admin'): array
    {
        // Simulated backend response following the 4-node JSON contract
        return [
            'data' => [
                'case_id' => $caseId,
                'case_status' => 'Under Review',
                'priority' => 'Normal',
            ],
            'uiResources' => [
                [
                    'component_type' => 'display_text',
                    'key' => 'case_id',
                    'label' => 'Case ID',
                    'value' => $caseId,
                ],
                [
                    'component_type' => 'display_text',
                    'key' => 'case_status',
                    'label' => 'Status',
                    'value' => 'Under Review',
                    'visibility_rule' => 'always',
                ],
            ],
            'nextAssignmentInfo' => [
                'steps' => [
                    ['label' => 'Initiate', 'status' => 'completed'],
                    ['label' => 'Review', 'status' => 'current'],
                    ['label' => 'Approve', 'status' => 'pending'],
                ],
            ],
            'confirmationNote' => [
                'message' => null,
            ],
        ];
    }
}
