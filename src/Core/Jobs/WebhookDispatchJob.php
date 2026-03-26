<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use DxEngine\Core\DBALWrapper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class WebhookDispatchJob extends AbstractJob
{
    private int $attempts = 1;

    public function __construct(
        private readonly ?DBALWrapper $dbal = null,
        private readonly ?Client $httpClient = null
    ) {
    }

    public function handle(): void
    {
        if ($this->dbal === null) {
            throw new \RuntimeException('DBALWrapper is required for WebhookDispatchJob.');
        }

        $webhookId = (string) ($this->payload['webhook_id'] ?? '');
        if ($webhookId === '') {
            throw new \InvalidArgumentException('Missing webhook_id in payload.');
        }

        $webhook = $this->dbal->selectOne(
            'SELECT * FROM dx_webhooks WHERE id = ?',
            [$webhookId]
        );

        if ($webhook === null) {
            throw new \RuntimeException('Webhook not found for id: ' . $webhookId);
        }

        $body = [
            'event_type' => (string) ($this->payload['event_type'] ?? $webhook['event_type'] ?? 'unknown'),
            'case_id' => (string) ($this->payload['case_id'] ?? ''),
            'case_reference' => (string) ($this->payload['case_reference'] ?? ''),
            'case_data' => $this->payload['case_data'] ?? [],
            'occurred_at' => (string) ($this->payload['occurred_at'] ?? date('c')),
        ];

        $headers = ['Content-Type' => 'application/json'];
        $secretKey = (string) ($webhook['secret_key'] ?? '');
        $serializedBody = json_encode($body, JSON_THROW_ON_ERROR);

        if ($secretKey !== '') {
            $headers['X-DX-Signature'] = hash_hmac('sha256', $serializedBody, $secretKey);
        }

        $start = microtime(true);
        $statusCode = null;
        $responseSnippet = null;

        $client = $this->httpClient ?? new Client();

        try {
            $response = $client->post((string) $webhook['url'], [
                'headers' => $headers,
                'body' => $serializedBody,
                'timeout' => (float) ($_ENV['WEBHOOK_TIMEOUT_SECONDS'] ?? 10),
            ]);

            $statusCode = $response->getStatusCode();
            $responseSnippet = substr((string) $response->getBody(), 0, 2000);
        } catch (GuzzleException $e) {
            $responseSnippet = substr($e->getMessage(), 0, 2000);
            throw $e;
        } finally {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->dbal->insert('dx_webhook_logs', [
                'id' => uniqid('whlog_', true),
                'webhook_id' => $webhookId,
                'case_id' => (string) ($this->payload['case_id'] ?? ''),
                'job_id' => (string) ($this->payload['job_id'] ?? ''),
                'http_status' => $statusCode,
                'response_body' => $responseSnippet,
                'attempt_number' => (int) ($this->payload['attempts'] ?? 1),
                'duration_ms' => $durationMs,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->dbal === null) {
            return;
        }

        $this->dbal->insert('dx_webhook_logs', [
            'id' => uniqid('whlog_terminal_', true),
            'webhook_id' => (string) ($this->payload['webhook_id'] ?? ''),
            'case_id' => (string) ($this->payload['case_id'] ?? ''),
            'job_id' => (string) ($this->payload['job_id'] ?? ''),
            'http_status' => null,
            'response_body' => substr($exception->getMessage(), 0, 2000),
            'attempt_number' => (int) ($this->payload['attempts'] ?? 1),
            'duration_ms' => null,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getBackoffSeconds(): int
    {
        $attempts = max(1, (int) ($this->payload['attempts'] ?? $this->attempts));
        return 60 * (5 ** ($attempts - 1));
    }
}
