<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core\Jobs;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Jobs\WebhookDispatchJob;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class WebhookDispatchJobTest extends BaseUnitTestCase
{
    private DBALWrapper $db;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-webhook-job');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement('CREATE TABLE dx_webhooks (id TEXT PRIMARY KEY, name TEXT, url TEXT, event_type TEXT, secret_key TEXT, headers TEXT, is_active INTEGER, last_triggered_at TEXT, created_at TEXT, updated_at TEXT)');
        $this->db->executeStatement('CREATE TABLE dx_webhook_logs (id TEXT, webhook_id TEXT, case_id TEXT, job_id TEXT, http_status INTEGER NULL, response_body TEXT NULL, attempt_number INTEGER, duration_ms INTEGER NULL, attempted_at TEXT)');
    }

    public function test_handle_sends_post_request_to_the_registered_webhook_url(): void
    {
        $this->db->insert('dx_webhooks', [
            'id' => 'wh-1',
            'name' => 'Main',
            'url' => 'https://example.test/webhook',
            'event_type' => 'CASE_UPDATED',
            'secret_key' => '',
            'headers' => '{}',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('https://example.test/webhook', $this->arrayHasKey('headers'))
            ->willReturn(new Response(200, [], 'ok'));

        $job = new WebhookDispatchJob($this->db, $client);
        $job->setPayload([
            'webhook_id' => 'wh-1',
            'case_id' => 'case-1',
            'job_id' => 'job-1',
            'attempts' => 1,
        ]);

        $job->handle();

        $row = $this->db->selectOne('SELECT * FROM dx_webhook_logs WHERE webhook_id = ?', ['wh-1']);
        $this->assertNotNull($row);
    }

    public function test_handle_attaches_hmac_sha256_signature_header_when_secret_key_is_configured(): void
    {
        $this->db->insert('dx_webhooks', [
            'id' => 'wh-2',
            'name' => 'Signed',
            'url' => 'https://example.test/signed',
            'event_type' => 'CASE_UPDATED',
            'secret_key' => 'secret',
            'headers' => '{}',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://example.test/signed',
                $this->callback(static function (array $options): bool {
                    return isset($options['headers']['X-DX-Signature']) && $options['headers']['X-DX-Signature'] !== '';
                })
            )
            ->willReturn(new Response(200, [], 'ok'));

        $job = new WebhookDispatchJob($this->db, $client);
        $job->setPayload(['webhook_id' => 'wh-2', 'case_id' => 'case-2', 'job_id' => 'job-2', 'attempts' => 1]);

        $job->handle();
        $this->assertTrue(true);
    }

    public function test_handle_logs_success_record_to_webhook_logs_table_on_http_200_response(): void
    {
        $this->db->insert('dx_webhooks', [
            'id' => 'wh-3',
            'name' => 'Success',
            'url' => 'https://example.test/success',
            'event_type' => 'CASE_UPDATED',
            'secret_key' => '',
            'headers' => '{}',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $client = $this->createMock(Client::class);
        $client->method('post')->willReturn(new Response(200, [], 'accepted'));

        $job = new WebhookDispatchJob($this->db, $client);
        $job->setPayload(['webhook_id' => 'wh-3', 'case_id' => 'case-3', 'job_id' => 'job-3', 'attempts' => 1]);
        $job->handle();

        $row = $this->db->selectOne('SELECT http_status FROM dx_webhook_logs WHERE webhook_id = ?', ['wh-3']);
        $this->assertNotNull($row);
        $this->assertSame(200, (int) $row['http_status']);
    }

    public function test_handle_logs_failure_record_to_webhook_logs_on_guzzle_exception(): void
    {
        $this->db->insert('dx_webhooks', [
            'id' => 'wh-4',
            'name' => 'Fail',
            'url' => 'https://example.test/fail',
            'event_type' => 'CASE_UPDATED',
            'secret_key' => '',
            'headers' => '{}',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $client = $this->createMock(Client::class);
        $client->method('post')
            ->willThrowException(new RequestException('network error', new Request('POST', 'https://example.test/fail')));

        $job = new WebhookDispatchJob($this->db, $client);
        $job->setPayload(['webhook_id' => 'wh-4', 'case_id' => 'case-4', 'job_id' => 'job-4', 'attempts' => 1]);

        try {
            $job->handle();
        } catch (\Throwable) {
        }

        $row = $this->db->selectOne('SELECT response_body FROM dx_webhook_logs WHERE webhook_id = ?', ['wh-4']);
        $this->assertNotNull($row);
        $this->assertStringContainsString('network error', (string) $row['response_body']);
    }

    public function test_failed_hook_inserts_terminal_failure_record_to_webhook_logs(): void
    {
        $job = new WebhookDispatchJob($this->db);
        $job->setPayload(['webhook_id' => 'wh-terminal', 'case_id' => 'case-x', 'job_id' => 'job-x', 'attempts' => 3]);
        $job->failed(new \RuntimeException('terminal failure'));

        $row = $this->db->selectOne('SELECT * FROM dx_webhook_logs WHERE webhook_id = ?', ['wh-terminal']);
        $this->assertNotNull($row);
        $this->assertStringContainsString('terminal failure', (string) $row['response_body']);
    }

    public function test_get_backoff_seconds_implements_exponential_backoff_formula(): void
    {
        $job = new WebhookDispatchJob($this->db);
        $job->setPayload(['attempts' => 1]);
        $this->assertSame(60, $job->getBackoffSeconds());

        $job->setPayload(['attempts' => 2]);
        $this->assertSame(300, $job->getBackoffSeconds());

        $job->setPayload(['attempts' => 3]);
        $this->assertSame(1500, $job->getBackoffSeconds());
    }
}
