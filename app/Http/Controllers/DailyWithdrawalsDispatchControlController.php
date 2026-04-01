<?php

namespace App\Http\Controllers;

use App\Models\DailyWithdrawal;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DailyWithdrawalsDispatchControlController extends Controller
{
    private const TEMPLATE_BASE_NAME = 'FORMATO CONTROL DE DESPACHO';
    private const ROWS_PER_PAGE = 22;
    private const LIBREOFFICE_TIMEOUT_SECONDS = 120;

    private const ITEM_TOKENS = [
        'fecha',
        'solicitante',
        'descripcion_material',
        'cant',
        'destino',
        'retorna',
        'fecha_retorno',
    ];

    public function __invoke()
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole(['Almacen', 'admin', 'Alta Gerencia', 'A.I.T', 'Almacen Recepcion'])) {
            abort(403);
        }

        $templatePath = $this->resolveTemplatePath();

        if ($templatePath === null) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla FORMATO CONTROL DE DESPACHO en storage/templates o storage/app/templates.');
        }

        $fromDate = request()->date('from');
        $toDate = request()->date('to');

        if ($fromDate && ! $toDate) {
            $toDate = $fromDate->copy();
        }

        if ($toDate && ! $fromDate) {
            $fromDate = $toDate->copy();
        }

        if (! $fromDate && ! $toDate) {
            $fromDate = now()->startOfDay();
            $toDate = now()->startOfDay();
        }

        if ($fromDate && $toDate && $fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $fromDate = $fromDate?->startOfDay();
        $toDate = $toDate?->endOfDay();

        $approvedWithdrawals = DailyWithdrawal::query()
            ->with(['user', 'product'])
            ->where('status', 'aprobado')
            ->whereBetween('requested_at', [$fromDate, $toDate])
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();

        if ($approvedWithdrawals->isEmpty()) {
            abort(Response::HTTP_NOT_FOUND, 'No hay retiros diarios aprobados en el rango de fechas indicado.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $fileBaseName = 'control-despacho-materiales-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            // La exportacion debe usar solo la primera planilla del formato base.
            $baseSheet = $spreadsheet->getSheet(0);
            $spreadsheet->setActiveSheetIndex(0);

            // Elimina hojas adicionales de la plantilla para evitar paginas ajenas en el PDF.
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $templateSheet = clone $baseSheet;

            $chunks = $approvedWithdrawals->chunk(self::ROWS_PER_PAGE)->values();
            $totalPages = $chunks->count();

            foreach ($chunks as $pageIndex => $chunk) {
                if ($pageIndex === 0) {
                    $sheet = $baseSheet;
                } else {
                    $sheet = clone $templateSheet;
                    $sheet->setTitle('Despacho ' . ($pageIndex + 1));
                    $spreadsheet->addSheet($sheet);
                }

                $itemTemplateRow = $this->findFirstRowWithItemTokens($sheet);

                if ($itemTemplateRow === null) {
                    throw new \RuntimeException('No se encontro fila con placeholders de detalle en la plantilla.');
                }

                $itemColumnMap = $this->extractItemColumnMap($sheet, $itemTemplateRow);

                $globalTokens = [
                    'fecha_reporte' => $fromDate->format('d/m/Y') . ' - ' . $toDate->format('d/m/Y'),
                    'pagina_actual' => (string) ($pageIndex + 1),
                    'total_paginas' => (string) $totalPages,
                ];

                $this->replaceTokenVariantsAcrossSheet($sheet, $globalTokens);
                $this->fillFixedRows($sheet, $itemTemplateRow, $itemColumnMap, $chunk->values()->all());
                $this->normalizeSheetForPdf($sheet);
            }

            $xlsxWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $xlsxWriter->save($xlsxPath);

            $wasConvertedByLibreOffice = $this->convertExcelToPdfWithLibreOffice($xlsxPath, $pdfPath, $tmpDir);

            if (! $wasConvertedByLibreOffice || ! file_exists($pdfPath)) {
                throw new \RuntimeException('No se pudo convertir el archivo con LibreOffice.');
            }

            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\Throwable $exception) {
            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el reporte Control de Despacho.');
        }

        $fileName = 'CONTROL_DESPACHO_MATERIALES_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.pdf';
        $download = request()->boolean('download', true);

        if ($download) {
            return response()->download($pdfPath, $fileName)->deleteFileAfterSend(true);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ])->deleteFileAfterSend(true);
    }

    private function resolveTemplatePath(): ?string
    {
        $directories = [
            storage_path('templates'),
            storage_path('app/templates'),
        ];

        $extensions = ['xlsx', 'xls', 'ods'];

        foreach ($directories as $directory) {
            foreach ($extensions as $extension) {
                $candidate = $directory . DIRECTORY_SEPARATOR . self::TEMPLATE_BASE_NAME . '.' . $extension;

                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function findFirstRowWithItemTokens(Worksheet $sheet): ?int
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();

                if ($cellValue instanceof RichText) {
                    $textValue = $cellValue->getPlainText();
                } elseif (is_string($cellValue)) {
                    $textValue = $cellValue;
                } else {
                    continue;
                }

                foreach (self::ITEM_TOKENS as $token) {
                    if ($this->containsTokenVariant($textValue, $token)) {
                        return $row;
                    }
                }
            }
        }

        return null;
    }

    private function extractItemColumnMap(Worksheet $sheet, int $row): array
    {
        $map = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();

            if ($cellValue instanceof RichText) {
                $textValue = $cellValue->getPlainText();
            } elseif (is_string($cellValue)) {
                $textValue = $cellValue;
            } else {
                continue;
            }

            foreach (self::ITEM_TOKENS as $token) {
                if ($this->containsTokenVariant($textValue, $token)) {
                    $map[$token] = Coordinate::stringFromColumnIndex($col);
                }
            }
        }

        return $map;
    }

    private function fillFixedRows(Worksheet $sheet, int $startRow, array $columnMap, array $rows): void
    {
        for ($i = 0; $i < self::ROWS_PER_PAGE; $i++) {
            $targetRow = $startRow + $i;
            $record = $rows[$i] ?? null;
            $tokens = $this->buildItemTokens($record);

            foreach ($columnMap as $token => $columnLetter) {
                $sheet->setCellValue($columnLetter . $targetRow, $tokens[$token] ?? '');
            }
        }
    }

    private function buildItemTokens(?DailyWithdrawal $withdrawal): array
    {
        if (! $withdrawal) {
            return array_fill_keys(self::ITEM_TOKENS, '');
        }

        $product = $withdrawal->product;

        return [
            'fecha' => optional($withdrawal->requested_at)->format('d/m/Y') ?? '',
            'solicitante' => (string) ($withdrawal->user?->name ?? ''),
            'descripcion_material' => (string) (($product?->name ?? null) ?: ($product?->descripcion ?? '')),
            'cant' => (string) $withdrawal->quantity,
            'destino' => (string) ($withdrawal->destination ?? ''),
            'retorna' => (bool) $withdrawal->requires_return ? 'SI' : 'NO',
            'fecha_retorno' => $withdrawal->return_date ? Carbon::parse($withdrawal->return_date)->format('d/m/Y') : '',
        ];
    }

    private function replaceTokenVariantsAcrossSheet(Worksheet $sheet, array $tokens): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $row);
                $value = $cell->getValue();

                if ($value instanceof RichText) {
                    $textValue = $value->getPlainText();
                } elseif (is_string($value)) {
                    $textValue = $value;
                } else {
                    continue;
                }

                if ($textValue === '') {
                    continue;
                }

                $newValue = $this->replaceTokenVariants($textValue, $tokens);

                if ($newValue !== $textValue) {
                    $cell->setValue($newValue);
                }
            }
        }
    }

    private function replaceTokenVariants(string $text, array $tokens): string
    {
        foreach ($tokens as $token => $value) {
            $quotedToken = preg_quote($token, '/');
            $patterns = [
                '/\{\{\s*' . $quotedToken . '\s*\}\}/u',
                '/\[\[\s*' . $quotedToken . '\s*\]\]/u',
                '/\{\s*' . $quotedToken . '\s*\}/u',
                '/%\s*' . $quotedToken . '\s*%/u',
                '/__\s*' . $quotedToken . '\s*__/u',
            ];

            $text = preg_replace($patterns, (string) $value, $text) ?? $text;
        }

        return $text;
    }

    private function containsTokenVariant(string $text, string $token): bool
    {
        $quotedToken = preg_quote($token, '/');
        $pattern = '/(\{\{\s*' . $quotedToken . '\s*\}\}|\[\[\s*' . $quotedToken . '\s*\]\]|\{\s*' . $quotedToken . '\s*\}|%\s*' . $quotedToken . '\s*%|__\s*' . $quotedToken . '\s*__)/u';

        return (bool) preg_match($pattern, $text);
    }

    private function normalizeSheetForPdf(Worksheet $sheet): void
    {
        $pageSetup = $sheet->getPageSetup();

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();

        if ($highestColumn === 'A' && $highestRow <= 1) {
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
        }

        $pageSetup->setPrintArea('A1:' . $highestColumn . $highestRow);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
    }

    private function convertExcelToPdfWithLibreOffice(string $xlsxPath, string $pdfPath, string $outputDir): bool
    {
        $binary = $this->resolveLibreOfficeBinary();

        if ($binary === null) {
            return false;
        }

        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to',
            'pdf:calc_pdf_Export',
            '--outdir',
            $outputDir,
            $xlsxPath,
        ]);

        $process->setTimeout(self::LIBREOFFICE_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Fallo conversion LibreOffice para control de despacho.', [
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
                'xlsx' => $xlsxPath,
            ]);

            return false;
        }

        $generatedPdfPath = $outputDir
            . DIRECTORY_SEPARATOR
            . pathinfo($xlsxPath, PATHINFO_FILENAME)
            . '.pdf';

        if (! file_exists($generatedPdfPath)) {
            return false;
        }

        if (realpath($generatedPdfPath) !== realpath($pdfPath)) {
            if (file_exists($pdfPath)) {
                @unlink($pdfPath);
            }

            rename($generatedPdfPath, $pdfPath);
        }

        return true;
    }

    private function resolveLibreOfficeBinary(): ?string
    {
        $envPath = trim((string) env('LIBREOFFICE_PATH', ''));
        $candidates = array_filter([
            $envPath !== '' ? $envPath : null,
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/usr/local/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/snap/bin/libreoffice',
            '/snap/bin/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder();

        return $finder->find('libreoffice')
            ?? $finder->find('soffice')
            ?? $finder->find('soffice.com');
    }
}
