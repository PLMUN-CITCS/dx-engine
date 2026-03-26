<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core\Jobs;

use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Jobs\PdfGenerationJob;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class PdfGenerationJobTest extends BaseUnitTestCase
{
    private DBALWrapper $db;
    private string $templateRelativePath;
    private string $templateAbsolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-pdf-job');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement('CREATE TABLE dx_cases (id TEXT PRIMARY KEY, case_reference TEXT, case_data TEXT, updated_at TEXT NULL)');
        $this->db->insert('dx_cases', [
            'id' => 'case-1',
            'case_reference' => 'CASE-00001',
            'case_data' => json_encode(['customer_name' => 'Jane Doe', 'amount' => '150.00'], JSON_THROW_ON_ERROR),
            'updated_at' => null,
        ]);

        $this->templateRelativePath = 'layouts/test_pdf_template.html';
        $this->templateAbsolutePath = dirname(__DIR__, 4) . '/templates/' . $this->templateRelativePath;
        file_put_contents($this->templateAbsolutePath, '<html><body><h1>{{ customer_name }}</h1><p>{{ amount }}</p></body></html>');
    }

    protected function tearDown(): void
    {
        if (is_file($this->templateAbsolutePath)) {
            @unlink($this->templateAbsolutePath);
        }

        $exportDir = dirname(__DIR__, 4) . '/storage/exports/';
        foreach (glob($exportDir . 'CASE-00001_*.pdf') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_handle_replaces_all_placeholder_tokens_in_html_template_with_case_data_values(): void
    {
        $job = new PdfGenerationJob($this->db);
        $job->setPayload([
            'case_id' => 'case-1',
            'template_path' => $this->templateRelativePath,
        ]);

        $job->handle();

        $row = $this->db->selectOne('SELECT case_data FROM dx_cases WHERE id = ?', ['case-1']);
        $this->assertNotNull($row);

        $data = json_decode((string) $row['case_data'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('generated_pdf_path', $data);
    }

    public function test_handle_outputs_pdf_file_to_correct_path_in_export_storage(): void
    {
        $job = new PdfGenerationJob($this->db);
        $job->setPayload([
            'case_id' => 'case-1',
            'template_path' => $this->templateRelativePath,
        ]);

        $job->handle();

        $row = $this->db->selectOne('SELECT case_data FROM dx_cases WHERE id = ?', ['case-1']);
        $data = json_decode((string) $row['case_data'], true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('generated_pdf_path', $data);
        $this->assertFileExists(dirname(__DIR__, 4) . '/' . $data['generated_pdf_path']);
    }

    public function test_handle_updates_case_data_record_with_generated_pdf_file_path(): void
    {
        $job = new PdfGenerationJob($this->db);
        $job->setPayload([
            'case_id' => 'case-1',
            'template_path' => $this->templateRelativePath,
        ]);

        $job->handle();

        $row = $this->db->selectOne('SELECT case_data FROM dx_cases WHERE id = ?', ['case-1']);
        $this->assertNotNull($row);

        $data = json_decode((string) $row['case_data'], true);
        $this->assertArrayHasKey('generated_pdf_path', $data);
        $this->assertStringContainsString('storage/exports/', (string) $data['generated_pdf_path']);
    }

    public function test_handle_throws_when_template_file_does_not_exist(): void
    {
        $job = new PdfGenerationJob($this->db);
        $job->setPayload([
            'case_id' => 'case-1',
            'template_path' => 'layouts/does_not_exist.html',
        ]);

        $this->expectException(\RuntimeException::class);
        $job->handle();
    }
}
