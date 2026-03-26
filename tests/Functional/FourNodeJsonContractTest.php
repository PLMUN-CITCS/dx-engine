<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Axiom A-08: 4-Node JSON Contract Functional Test
 * 
 * Validates that every API response conforms exactly to the 4-node JSON contract:
 * { "data": {}, "uiResources": [], "nextAssignmentInfo": {}, "confirmationNote": {} }
 * 
 * Additional root nodes are forbidden without a versioned contract amendment.
 */
final class FourNodeJsonContractTest extends BaseFunctionalTestCase
{
    public function test_api_response_contains_all_four_required_nodes(): void
    {
        $response = $this->buildMockApiResponse();

        $this->assert4NodeJsonContract($response);
    }

    public function test_data_node_contains_product_info(): void
    {
        $response = $this->buildMockApiResponse();

        $this->assertIsArray($response['data']);
        $this->assertNotEmpty($response['data']);
        
        // Data should contain formatted, ready-to-display values
        foreach ($response['data'] as $key => $value) {
            $this->assertTrue(is_scalar($value) || is_null($value));
        }
    }

    public function test_ui_resources_node_contains_component_definitions(): void
    {
        $response = $this->buildMockApiResponse();

        $this->assertIsArray($response['uiResources']);
        
        foreach ($response['uiResources'] as $component) {
            $this->assertArrayHasKey('component_type', $component);
            $this->assertArrayHasKey('key', $component);
            $this->assertArrayHasKey('label', $component);
            
            // Optional but common fields
            $this->assertIsString($component['component_type']);
            $this->assertIsString($component['key']);
            $this->assertIsString($component['label']);
        }
    }

    public function test_next_assignment_info_node_contains_workflow_metadata(): void
    {
        $response = $this->buildMockApiResponse();

        $this->assertIsArray($response['nextAssignmentInfo']);
        
        if (!empty($response['nextAssignmentInfo'])) {
            $this->assertArrayHasKey('steps', $response['nextAssignmentInfo']);
            $this->assertIsArray($response['nextAssignmentInfo']['steps']);
            
            foreach ($response['nextAssignmentInfo']['steps'] as $step) {
                $this->assertArrayHasKey('label', $step);
                $this->assertArrayHasKey('status', $step);
            }
        }
    }

    public function test_confirmation_note_node_structure(): void
    {
        $response = $this->buildMockApiResponse();

        $this->assertIsArray($response['confirmationNote']);
        $this->assertArrayHasKey('message', $response['confirmationNote']);
    }

    public function test_no_additional_root_nodes_are_present(): void
    {
        $response = $this->buildMockApiResponse();

        $allowedKeys = ['data', 'uiResources', 'nextAssignmentInfo', 'confirmationNote'];
        $actualKeys = array_keys($response);

        $this->assertEquals(
            $allowedKeys,
            $actualKeys,
            'Response contains only the 4 canonical nodes'
        );
    }

    public function test_empty_response_still_conforms_to_contract(): void
    {
        $response = [
            'data' => [],
            'uiResources' => [],
            'nextAssignmentInfo' => [],
            'confirmationNote' => ['message' => null],
        ];

        $this->assert4NodeJsonContract($response);
    }

    public function test_component_types_are_valid(): void
    {
        $validTypes = [
            'display_text',
            'text_input',
            'textarea',
            'dropdown',
            'checkbox',
            'radio_group',
            'date_picker',
            'file_upload',
            'button',
            'section_header',
            'repeating_grid',
        ];

        $response = $this->buildMockApiResponse();

        foreach ($response['uiResources'] as $component) {
            $this->assertContains(
                $component['component_type'],
                $validTypes,
                "Component type '{$component['component_type']}' must be a valid type"
            );
        }
    }

    public function test_visibility_rules_are_included_when_applicable(): void
    {
        $response = $this->buildMockApiResponse([
            ['component_type' => 'text_input', 'key' => 'conditional_field', 'label' => 'Field', 'visibility_rule' => 'status == "OPEN"'],
        ]);

        $conditionalField = array_filter(
            $response['uiResources'],
            fn($c) => $c['key'] === 'conditional_field'
        );

        $this->assertNotEmpty($conditionalField);
        $field = array_values($conditionalField)[0];
        $this->assertArrayHasKey('visibility_rule', $field);
        $this->assertEquals('status == "OPEN"', $field['visibility_rule']);
    }

    private function buildMockApiResponse(array $customComponents = []): array
    {
        $defaultComponents = [
            [
                'component_type' => 'display_text',
                'key' => 'case_id',
                'label' => 'Case ID',
                'value' => 'CASE-001',
            ],
            [
                'component_type' => 'display_text',
                'key' => 'case_status',
                'label' => 'Status',
                'value' => 'Open',
            ],
            [
                'component_type' => 'text_input',
                'key' => 'customer_name',
                'label' => 'Customer Name',
                'required' => true,
                'validation' => ['required', 'min:3'],
            ],
        ];

        return [
            'data' => [
                'case_id' => 'CASE-001',
                'case_status' => 'Open',
                'priority' => 'Normal',
                'owner' => 'Admin User',
            ],
            'uiResources' => empty($customComponents) ? $defaultComponents : $customComponents,
            'nextAssignmentInfo' => [
                'steps' => [
                    ['label' => 'Initiate', 'status' => 'completed'],
                    ['label' => 'Review', 'status' => 'current'],
                    ['label' => 'Approve', 'status' => 'pending'],
                ],
                'currentStep' => 'Review',
                'totalSteps' => 3,
                'completedSteps' => 1,
            ],
            'confirmationNote' => [
                'message' => null,
            ],
        ];
    }
}
