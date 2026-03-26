<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core\Jobs;

use DxEngine\Core\Jobs\SpreadsheetImportJob;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpreadsheetImportJobTest extends BaseUnitTestCase
{
    private string $tmpFile;
    private string $jobId;
    private string $resultPath;

    protected function setUp(): void
    {
        parent::setUp();

        TestImportModel::$savedRows = [];
        TestImportModel::$failOnEmail = null;

        $this->tmpFile = dirname(__DIR__, 4) . '/storage/cache/test_import_' . uniqid() . '.xlsx';
        $this->jobId = 'job_' . uniqid();
        $this->resultPath = dirname(__DIR__, 4) . '/storage/exports/' . $this->jobId . '_import_result.json';

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setCellValue('A1', 'email');
        $ws->setCellValue('B1', 'display_name');
        $ws->setCellValue('A2', 'john@example.com');
        $ws->setCellValue('B2', 'John');
        $ws->setCellValue('A3', 'jane@example.com');
        $ws->setCellValue('B3', 'Jane');

        $writer = new Xlsx($sheet);
        $writer->save($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
        if (is_file($this->resultPath)) {
            @unlink($this->resultPath);
        }

        parent::tearDown();
    }

    public function test_handle_correctly_maps_spreadsheet_columns_to_model_fields_via_column_map(): void
    {
        $job = new SpreadsheetImportJob();
        $job->setPayload([
            'file_path' => $this->tmpFile,
            'target_model' => TestImportModel::class,
            'column_map' => ['A' => 'email', 'B' => 'display_name'],
            'has_header_row' => true,
            'job_id' => $this->jobId,
        ]);

        $job->handle();

        $this->assertCount(2, TestImportModel::$savedRows);
        $this->assertSame('john@example.com', TestImportModel::$savedRows[0]['email']);
        $this->assertSame('John', TestImportModel::$savedRows[0]['display_name']);
    }

    public function test_handle_skips_header_row_when_has_header_row_flag_is_true(): void
    {
        $job = new SpreadsheetImportJob();
        $job->setPayload([
            'file_path' => $this->tmpFile,
            'target_model' => TestImportModel::class,
            'column_map' => ['A' => 'email', 'B' => 'display_name'],
            'has_header_row' => true,
            'job_id' => $this->jobId,
        ]);

        $job->handle();

        $this->assertSame('john@example.com', TestImportModel::$savedRows[0]['email']);
    }

    public function test_handle_writes_json_summary_file_to_export_storage_path(): void
    {
        $job = new SpreadsheetImportJob();
        $job->setPayload([
            'file_path' => $this->tmpFile,
            'target_model' => TestImportModel::class,
            'column_map' => ['A' => 'email', 'B' => 'display_name'],
            'has_header_row' => true,
            'job_id' => $this->jobId,
        ]);

        $job->handle();

        $this->assertFileExists($this->resultPath);
        $content = json_decode((string) file_get_contents($this->resultPath), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('total_rows', $content);
    }

    public function test_handle_continues_processing_remaining_rows_on_per_row_validation_failure(): void
    {
        TestImportModel::$failOnEmail = 'john@example.com';

        $job = new SpreadsheetImportJob();
        $job->setPayload([
            'file_path' => $this->tmpFile,
            'target_model' => TestImportModel::class,
            'column_map' => ['A' => 'email', 'B' => 'display_name'],
            'has_header_row' => true,
            'job_id' => $this->jobId,
        ]);

        $job->handle();

        $this->assertCount(1, TestImportModel::$savedRows);
        $this->assertSame('jane@example.com', TestImportModel::$savedRows[0]['email']);
    }

    public function test_summary_file_contains_correct_success_and_error_counts(): void
    {
        TestImportModel::$failOnEmail = 'john@example.com';

        $job = new SpreadsheetImportJob();
        $job->setPayload([
            'file_path' => $this->tmpFile,
            'target_model' => TestImportModel::class,
            'column_map' => ['A' => 'email', 'B' => 'display_name'],
            'has_header_row' => true,
            'job_id' => $this->jobId,
        ]);

        $job->handle();

        $summary = json_decode((string) file_get_contents($this->resultPath), true);

        $this->assertSame(2, (int) $summary['total_rows']);
        $this->assertSame(1, (int) $summary['success_count']);
        $this->assertSame(1, (int) $summary['error_count']);
    }
}

class TestImportModel
{
    /** @var array<int, array<string,mixed>> */
    public static array $savedRows = [];
    public static ?string $failOnEmail = null;

    /** @var array<string,mixed> */
    private array $attributes = [];

    /**
     * @param array<string,mixed> $attributes
     */
    public function fill(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function save(): bool
    {
        if (self::$failOnEmail !== null && ($this->attributes['email'] ?? null) === self::$failOnEmail) {
            throw new \RuntimeException('Forced row failure');
        }

        self::$savedRows[] = $this->attributes;
        return true;
    }
}
