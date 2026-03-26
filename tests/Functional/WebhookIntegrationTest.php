<?php

declare(strict_types=1);

namespace DxEngine\Tests\Functional;

/**
 * Webhook Integration Functional Test Suite
 * 
 * Validates webhook registration, dispatching, logging,
 * retry mechanisms, and failure handling.
 */
final class WebhookIntegrationTest extends BaseFunctionalTestCase
{
    public function test_register_webhook(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.created',
            'target_url' => 'https://example.com/webhooks/case-created',
            'http_method' => 'POST',
            'headers' => json_encode(['Authorization' => 'Bearer token123']),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $webhook = $this->db->selectOne('SELECT * FROM dx_webhooks WHERE id = ?', [$webhookId]);
        
        $this->assertNotNull($webhook);
        $this->assertEquals('case.created', $webhook['event_type']);
        $this->assertEquals('POST', $webhook['http_method']);
    }

    public function test_get_active_webhooks_for_event(): void
    {
        $this->db->insert('dx_webhooks', [
            'id' => 'webhook-active-1',
            'event_type' => 'case.updated',
            'target_url' => 'https://example.com/webhook-1',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->insert('dx_webhooks', [
            'id' => 'webhook-inactive',
            'event_type' => 'case.updated',
            'target_url' => 'https://example.com/webhook-2',
            'http_method' => 'POST',
            'is_active' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $activeWebhooks = $this->db->select(
            'SELECT * FROM dx_webhooks WHERE event_type = ? AND is_active = ?',
            ['case.updated', 1]
        );

        $this->assertCount(1, $activeWebhooks);
    }

    public function test_log_webhook_dispatch(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.completed',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $case = $this->createTestCase();

        $logId = 'log-' . uniqid();
        $this->db->insert('dx_webhook_logs', [
            'id' => $logId,
            'webhook_id' => $webhookId,
            'case_id' => $case['id'],
            'request_payload' => json_encode(['case_id' => $case['id'], 'status' => 'COMPLETED']),
            'response_status' => 200,
            'response_body' => json_encode(['success' => true]),
            'error_message' => null,
            'attempt_number' => 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
        ]);

        $log = $this->db->selectOne('SELECT * FROM dx_webhook_logs WHERE id = ?', [$logId]);
        
        $this->assertNotNull($log);
        $this->assertEquals($webhookId, $log['webhook_id']);
        $this->assertEquals(200, $log['response_status']);
        $this->assertEquals(1, $log['attempt_number']);
    }

    public function test_log_failed_webhook_dispatch(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.failed',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $case = $this->createTestCase();

        $logId = 'log-' . uniqid();
        $this->db->insert('dx_webhook_logs', [
            'id' => $logId,
            'webhook_id' => $webhookId,
            'case_id' => $case['id'],
            'request_payload' => json_encode(['case_id' => $case['id']]),
            'response_status' => 500,
            'response_body' => 'Internal Server Error',
            'error_message' => 'HTTP 500: Internal Server Error',
            'attempt_number' => 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
        ]);

        $log = $this->db->selectOne('SELECT * FROM dx_webhook_logs WHERE id = ?', [$logId]);
        
        $this->assertEquals(500, $log['response_status']);
        $this->assertNotNull($log['error_message']);
    }

    public function test_webhook_retry_attempts(): void
    {
        $webhookId = 'webhook-' . uniqid();
        $case = $this->createTestCase();

        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.retry',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Log multiple retry attempts
        for ($i = 1; $i <= 3; $i++) {
            $this->db->insert('dx_webhook_logs', [
                'id' => "log-retry-$i",
                'webhook_id' => $webhookId,
                'case_id' => $case['id'],
                'request_payload' => json_encode(['case_id' => $case['id']]),
                'response_status' => $i < 3 ? 503 : 200,
                'response_body' => $i < 3 ? 'Service Unavailable' : 'OK',
                'error_message' => $i < 3 ? 'Temporary failure' : null,
                'attempt_number' => $i,
                'dispatched_at' => date('Y-m-d H:i:s', time() + ($i * 60)),
            ]);
        }

        $logs = $this->db->select(
            'SELECT * FROM dx_webhook_logs WHERE webhook_id = ? AND case_id = ? ORDER BY attempt_number',
            [$webhookId, $case['id']]
        );

        $this->assertCount(3, $logs);
        $this->assertEquals(1, $logs[0]['attempt_number']);
        $this->assertEquals(3, $logs[2]['attempt_number']);
        $this->assertEquals(200, $logs[2]['response_status']);
    }

    public function test_disable_webhook(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.test',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Disable the webhook
        $this->db->update(
            'dx_webhooks',
            ['is_active' => 0],
            ['id' => $webhookId]
        );

        $webhook = $this->db->selectOne('SELECT * FROM dx_webhooks WHERE id = ?', [$webhookId]);
        $this->assertEquals(0, $webhook['is_active']);
    }

    public function test_webhook_with_custom_headers(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $headers = [
            'Authorization' => 'Bearer secret-token',
            'X-Custom-Header' => 'value',
            'Content-Type' => 'application/json',
        ];

        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.custom',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'headers' => json_encode($headers),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $webhook = $this->db->selectOne('SELECT * FROM dx_webhooks WHERE id = ?', [$webhookId]);
        
        $decodedHeaders = json_decode($webhook['headers'], true);
        $this->assertArrayHasKey('Authorization', $decodedHeaders);
        $this->assertEquals('Bearer secret-token', $decodedHeaders['Authorization']);
    }

    public function test_get_webhook_logs_for_case(): void
    {
        $webhookId = 'webhook-' . uniqid();
        $case = $this->createTestCase();

        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.log_test',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add logs
        for ($i = 1; $i <= 3; $i++) {
            $this->db->insert('dx_webhook_logs', [
                'id' => "log-case-$i",
                'webhook_id' => $webhookId,
                'case_id' => $case['id'],
                'request_payload' => json_encode(['event' => $i]),
                'response_status' => 200,
                'response_body' => 'OK',
                'attempt_number' => 1,
                'dispatched_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $logs = $this->db->select(
            'SELECT * FROM dx_webhook_logs WHERE case_id = ?',
            [$case['id']]
        );

        $this->assertCount(3, $logs);
    }

    public function test_cascade_delete_logs_on_webhook_deletion(): void
    {
        $webhookId = 'webhook-' . uniqid();
        
        $this->db->insert('dx_webhooks', [
            'id' => $webhookId,
            'event_type' => 'case.cascade',
            'target_url' => 'https://example.com/webhook',
            'http_method' => 'POST',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->insert('dx_webhook_logs', [
            'id' => 'log-cascade',
            'webhook_id' => $webhookId,
            'case_id' => null,
            'request_payload' => '{}',
            'response_status' => 200,
            'response_body' => 'OK',
            'attempt_number' => 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
        ]);

        // Delete webhook
        $this->db->delete('dx_webhooks', ['id' => $webhookId]);

        // Logs should be cascade deleted
        $logs = $this->db->select(
            'SELECT * FROM dx_webhook_logs WHERE webhook_id = ?',
            [$webhookId]
        );

        $this->assertEmpty($logs);
    }
}
