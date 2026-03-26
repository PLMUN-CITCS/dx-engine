<?php

declare(strict_types=1);

namespace DxEngine\Core\Jobs;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class SpreadsheetImportJob extends AbstractJob
{
    public function handle(): void
    {
        $filePath = (string) ($this->payload['file_path'] ?? '');
        $targetModel = (string) ($this->payload['target_model'] ?? '');
        $columnMap = $this->payload['column_map'] ?? [];
        $hasHeaderRow = (bool) ($this->payload['has_header_row'] ?? true);
        $jobId = (string) ($this->payload['job_id'] ?? uniqid('job_', true));

        if ($filePath === '' || !is_file($filePath)) {
            throw new RuntimeException('Import file not found: ' . $filePath);
        }

        if ($targetModel === '' || !class_exists($targetModel)) {
            throw new RuntimeException('Invalid target model class: ' . $targetModel);
        }

        if (!is_array($columnMap) || $columnMap === []) {
            throw new RuntimeException('column_map must be a non-empty array.');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $startRow = $hasHeaderRow ? 2 : 1;
        $highestRow = $sheet->getHighestDataRow();

        $totalRows = 0;
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $totalRows++;

            try {
                $attributes = [];
                foreach ($columnMap as $columnLetter => $fieldName) {
                    $value = $sheet->getCell((string) $columnLetter . $row)->getValue();
                    $attributes[(string) $fieldName] = is_scalar($value) || $value === null ? $value : (string) $value;
                }

                /** @var object $model */
                $model = new $targetModel();

                if (!method_exists($model, 'fill') || !method_exists($model, 'save')) {
                    throw new RuntimeException('Target model must expose fill() and save() methods.');
                }

                // Basic required field validation: no empty strings/nulls
                foreach ($attributes as $key => $value) {
                    if ($value === null || (is_string($value) && trim($value) === '')) {
                        throw new RuntimeException('Required field missing: ' . $key);
                    }
                }

                $model->fill($attributes);
                $saved = $model->save();
                if ($saved !== true) {
                    throw new RuntimeException('Model save() returned false.');
                }

                $successCount++;
            } catch (Throwable $e) {
                $errorCount++;
                $errors[] = [
                    'row' => $row,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $result = [
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];

        $exportPath = dirname(__DIR__, 3) . '/storage/exports/' . $jobId . '_import_result.json';
        file_put_contents($exportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
