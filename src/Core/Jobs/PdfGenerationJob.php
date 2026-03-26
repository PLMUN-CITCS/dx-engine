<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use Dompdf\Dompdf;
use DxEngine\Core\DBALWrapper;
use RuntimeException;

class PdfGenerationJob extends AbstractJob
{
    public function __construct(private readonly ?DBALWrapper $dbal = null)
    {
    }

    public function handle(): void
    {
        if ($this->dbal === null) {
            throw new RuntimeException('DBALWrapper is required for PdfGenerationJob.');
        }

        $caseId = (string) ($this->payload['case_id'] ?? '');
        $templateRelativePath = (string) ($this->payload['template_path'] ?? '');

        if ($caseId === '') {
            throw new RuntimeException('Missing case_id payload key.');
        }

        if ($templateRelativePath === '') {
            throw new RuntimeException('Missing template_path payload key.');
        }

        $case = $this->dbal->selectOne(
            'SELECT id, case_reference, case_data FROM dx_cases WHERE id = ?',
            [$caseId]
        );

        if ($case === null) {
            throw new RuntimeException('Case not found for id: ' . $caseId);
        }

        $templatePath = dirname(__DIR__, 3) . '/templates/' . ltrim($templateRelativePath, '/\\');
        if (!is_file($templatePath)) {
            throw new RuntimeException('Template file does not exist: ' . $templateRelativePath);
        }

        $templateHtml = (string) file_get_contents($templatePath);
        $caseData = json_decode((string) ($case['case_data'] ?? '{}'), true);
        $caseData = is_array($caseData) ? $caseData : [];

        $mergedHtml = $templateHtml;
        foreach ($caseData as $key => $value) {
            $placeholder = '{{ ' . $key . ' }}';
            $mergedHtml = str_replace($placeholder, (string) $value, $mergedHtml);
        }

        $dompdf = new Dompdf();
        $dompdf->loadHtml($mergedHtml);
        $dompdf->render();

        $caseReference = (string) ($case['case_reference'] ?? 'CASE');
        $timestamp = date('Ymd_His');
        $fileName = $caseReference . '_' . $timestamp . '.pdf';
        $relativeOutputPath = 'storage/exports/' . $fileName;
        $absoluteOutputPath = dirname(__DIR__, 3) . '/' . $relativeOutputPath;

        file_put_contents($absoluteOutputPath, $dompdf->output());

        $caseData['generated_pdf_path'] = $relativeOutputPath;

        $this->dbal->update('dx_cases', [
            'case_data' => json_encode($caseData, JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $caseId,
        ]);
    }
}
