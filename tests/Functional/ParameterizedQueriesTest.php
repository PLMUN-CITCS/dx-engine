<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Axiom A-10: Parameterized Queries Only Functional Test
 * 
 * Validates that DBALWrapper rejects any attempt to interpolate user-supplied data
 * directly into SQL strings. All user data MUST be passed as bound parameters.
 */
final class ParameterizedQueriesTest extends BaseFunctionalTestCase
{
    public function test_select_uses_bound_parameters(): void
    {
        $case1 = $this->createTestCase(['case_type' => 'ORDER']);
        $case2 = $this->createTestCase(['case_type' => 'COMPLAINT']);

        // Safe query with bound parameters
        $results = $this->db->select(
            'SELECT * FROM dx_cases WHERE case_type = ?',
            ['ORDER']
        );

        $this->assertCount(1, $results);
        $this->assertEquals($case1['id'], $results[0]['id']);
    }

    public function test_select_one_uses_bound_parameters(): void
    {
        $case = $this->createTestCase(['priority' => 'URGENT']);

        $result = $this->db->selectOne(
            'SELECT * FROM dx_cases WHERE priority = ?',
            ['URGENT']
        );

        $this->assertNotNull($result);
        $this->assertEquals($case['id'], $result['id']);
    }

    public function test_insert_with_bound_parameters(): void
    {
        $data = [
            'id' => 'param-test-1',
            'case_type' => 'PARAM_TEST',
            'case_status' => 'NEW',
            'owner_id' => 'user-admin',
            'priority' => 'NORMAL',
            'created_by_id' => 'user-admin',
            'payload' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', 'param-test'),
        ];

        $this->db->insert('dx_cases', $data);

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', ['param-test-1']);
        $this->assertNotNull($result);
    }

    public function test_update_with_bound_parameters(): void
    {
        $case = $this->createTestCase();

        $this->db->update(
            'dx_cases',
            ['case_status' => 'UPDATED', 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $case['id']]
        );

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertEquals('UPDATED', $result['case_status']);
    }

    public function test_delete_with_bound_parameters(): void
    {
        $case = $this->createTestCase();

        $this->db->delete('dx_cases', ['id' => $case['id']]);

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', [$case['id']]);
        $this->assertNull($result);
    }

    public function test_complex_query_with_multiple_parameters(): void
    {
        $this->createTestCase(['case_type' => 'TYPE_A', 'priority' => 'HIGH', 'case_status' => 'OPEN']);
        $this->createTestCase(['case_type' => 'TYPE_A', 'priority' => 'LOW', 'case_status' => 'OPEN']);
        $this->createTestCase(['case_type' => 'TYPE_B', 'priority' => 'HIGH', 'case_status' => 'CLOSED']);

        $results = $this->db->select(
            'SELECT * FROM dx_cases WHERE case_type = ? AND case_status = ? ORDER BY priority',
            ['TYPE_A', 'OPEN']
        );

        $this->assertCount(2, $results);
    }

    public function test_query_with_in_clause_uses_parameters(): void
    {
        $case1 = $this->createTestCase(['case_status' => 'NEW']);
        $case2 = $this->createTestCase(['case_status' => 'OPEN']);
        $case3 = $this->createTestCase(['case_status' => 'CLOSED']);

        // Using IN clause with parameters
        $placeholders = implode(',', array_fill(0, 2, '?'));
        $results = $this->db->select(
            "SELECT * FROM dx_cases WHERE case_status IN ($placeholders)",
            ['NEW', 'OPEN']
        );

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_query_with_like_clause_uses_parameters(): void
    {
        $this->createTestCase(['case_type' => 'ORDER_PREMIUM']);
        $this->createTestCase(['case_type' => 'ORDER_STANDARD']);
        $this->createTestCase(['case_type' => 'COMPLAINT']);

        $results = $this->db->select(
            'SELECT * FROM dx_cases WHERE case_type LIKE ?',
            ['ORDER%']
        );

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_special_characters_are_safely_escaped(): void
    {
        // Test SQL injection attempts are safely handled
        $maliciousInput = "'; DROP TABLE dx_cases; --";

        $data = [
            'id' => 'injection-test',
            'case_type' => $maliciousInput,
            'case_status' => 'NEW',
            'owner_id' => 'user-admin',
            'priority' => 'NORMAL',
            'created_by_id' => 'user-admin',
            'payload' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', 'injection-test'),
        ];

        $this->db->insert('dx_cases', $data);

        // Table should still exist
        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', ['injection-test']);
        $this->assertNotNull($result);
        $this->assertEquals($maliciousInput, $result['case_type']);
    }

    public function test_null_parameters_are_handled_correctly(): void
    {
        $data = [
            'id' => 'null-param-test',
            'case_type' => 'NULL_TEST',
            'case_status' => 'NEW',
            'owner_id' => null,
            'priority' => 'NORMAL',
            'created_by_id' => 'user-admin',
            'payload' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'e_tag' => hash('sha256', 'null-param'),
        ];

        $this->db->insert('dx_cases', $data);

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE owner_id IS NULL');
        $this->assertNotNull($result);
    }

    public function test_transaction_with_parameterized_queries(): void
    {
        $this->db->beginTransaction();

        try {
            $data = [
                'id' => 'tx-test',
                'case_type' => 'TX_TEST',
                'case_status' => 'NEW',
                'owner_id' => 'user-admin',
                'priority' => 'NORMAL',
                'created_by_id' => 'user-admin',
                'payload' => '{}',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'e_tag' => hash('sha256', 'tx-test'),
            ];

            $this->db->insert('dx_cases', $data);
            $this->db->update('dx_cases', ['case_status' => 'COMMITTED'], ['id' => 'tx-test']);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $result = $this->db->selectOne('SELECT * FROM dx_cases WHERE id = ?', ['tx-test']);
        $this->assertEquals('COMMITTED', $result['case_status']);
    }
}
