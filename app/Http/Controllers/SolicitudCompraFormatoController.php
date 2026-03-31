<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCompra;
use App\Support\SolicitudCompraFlow;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SolicitudCompraFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'PLANILLA DE FORMATO DE COMPRA.xlsx';
    private const PDF_PRINT_AREA_START = 'C3';
    private const PDF_MAX_END_COLUMN = 'L';
    private const PDF_MAX_END_ROW = 48;
    private const USO_LINE_MAX = 80;
    private const MAX_ITEMS = 15;
    private const USAR_CODIGOS_PREDEFINIDOS = true;
    private const CODIGO_CONTROL_PREDEFINIDO = 'CTRL-2026-000123';
    private const CODIGO_PROCURA_PREDEFINIDO = 'PROC-2026-000123';
    private const LIBREOFFICE_TIMEOUT_SECONDS = 120;
    private const SIGNATURE_TOKENS = [
        'firma_solicitante',
        'firma_almacen',
        'firma_aprobador',
        'firma_receptor',
    ];

    public function printPreview(SolicitudCompra $solicitudCompra)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (! SolicitudCompraFlow::canView($user, $solicitudCompra)) {
            abort(403);
        }

        $codigoControl = self::USAR_CODIGOS_PREDEFINIDOS
            ? self::CODIGO_CONTROL_PREDEFINIDO
            : (string) ($solicitudCompra->codigo_control ?? '');

        return view('solicitudes-compra.print-preview', [
            'solicitudCompra' => $solicitudCompra,
            'pdfUrl' => route('solicitudes-compra.formato', ['solicitudCompra' => $solicitudCompra]),
            'downloadUrl' => route('solicitudes-compra.formato', ['solicitudCompra' => $solicitudCompra, 'download' => 1]),
            'excelUrl' => route('solicitudes-compra.formato', ['solicitudCompra' => $solicitudCompra, 'format' => 'xlsx', 'download' => 1]),
            'codigoControlVisible' => $codigoControl,
        ]);
    }

    public function __invoke(SolicitudCompra $solicitudCompra)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        $canSee = SolicitudCompraFlow::canView($user, $solicitudCompra);

        if (! $canSee) {
            abort(403);
        }

        $solicitudCompra->loadMissing(['items', 'solicitadoPor', 'porAlmacen', 'aprobadoPor', 'recibidoPor']);

        $templatePath = storage_path('app/templates/' . self::EXCEL_TEMPLATE_FILE);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla Excel en storage/app/templates.');
        }

        $uso = trim((string) ($solicitudCompra->para_ser_usado_en ?? ''));
        [$usoLinea1, $usoLinea2, $usoLinea3] = $this->splitTextIntoThreeLines($uso, self::USO_LINE_MAX);

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'solicitud-compra-' . $solicitudCompra->id . '-' . now()->format('YmdHis');
        $excelFileName = 'SOLICITUD_COMPRA_' . $solicitudCompra->id . '.xlsx';
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $globalTokens = $this->buildGlobalTokens($solicitudCompra, $usoLinea1, $usoLinea2, $usoLinea3);
            $itemTemplateRow = $this->findFirstRowWithItemTokens($sheet);

            $this->replaceGlobalTokens($sheet, $globalTokens);
            $this->renderSignatureImages($sheet, $solicitudCompra);
            $this->renderItemRows($sheet, $itemTemplateRow, $solicitudCompra);
            $this->normalizeSheetForPdf($sheet);

            $xlsxWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $xlsxWriter->save($xlsxPath);

            if ($outputFormat === 'xlsx') {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                return response()->download($xlsxPath, $excelFileName)->deleteFileAfterSend(true);
            }

            if (! $this->convertExcelToPdfWithLibreOffice($xlsxPath, $pdfPath, $tmpDir)) {
                throw new \RuntimeException('LibreOffice no pudo convertir el archivo a PDF.');
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

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF desde la plantilla Excel.');
        }

        $fileName = 'SOLICITUD_COMPRA_' . $solicitudCompra->id . '.pdf';
        $download = request()->boolean('download');

        if ($download) {
            return response()->download($pdfPath, $fileName)->deleteFileAfterSend(true);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ])->deleteFileAfterSend(true);
    }

    private function buildGlobalTokens(SolicitudCompra $solicitudCompra, string $usoLinea1, string $usoLinea2, string $usoLinea3): array
    {
        $codigoControl = self::USAR_CODIGOS_PREDEFINIDOS
            ? self::CODIGO_CONTROL_PREDEFINIDO
            : (string) ($solicitudCompra->codigo_control ?? '');
        $codigoProcura = self::USAR_CODIGOS_PREDEFINIDOS
            ? self::CODIGO_PROCURA_PREDEFINIDO
            : (string) ($solicitudCompra->codigo_control_procura ?? '');

        return [
            'codigo_control' => $codigoControl,
            'codigo_control_procura' => $codigoProcura,
            'fecha_solicitud' => optional($solicitudCompra->fecha_solicitud)->format('d/m/Y') ?? '',
            'prioridad_alta' => $solicitudCompra->prioridad === 'Alta' ? 'X' : '',
            'prioridad_media' => $solicitudCompra->prioridad === 'Media' ? 'X' : '',
            'prioridad_baja' => $solicitudCompra->prioridad === 'Baja' ? 'X' : '',
            'prioridad_alta_x' => $solicitudCompra->prioridad === 'Alta' ? 'X' : '',
            'prioridad_media_x' => $solicitudCompra->prioridad === 'Media' ? 'X' : '',
            'prioridad_baja_x' => $solicitudCompra->prioridad === 'Baja' ? 'X' : '',
            'departamento_solicitante' => (string) ($solicitudCompra->departamento_solicitante ?? ''),
            'para_ser_usado_en' => (string) ($solicitudCompra->para_ser_usado_en ?? ''),
            'para_ser_usado_en_1' => $usoLinea1,
            'para_ser_usado_en_2' => $usoLinea2,
            'para_ser_usado_en_3' => $usoLinea3,
            'para_uso_linea1' => $usoLinea1,
            'para_uso_linea2' => $usoLinea2,
            'para_uso_linea3' => $usoLinea3,
            'centro' => (string) ($solicitudCompra->centro ?? ''),
            'elemento' => (string) ($solicitudCompra->elemento ?? ''),
            'cuenta' => (string) ($solicitudCompra->cuenta ?? ''),
            'contrato' => (string) ($solicitudCompra->contrato ?? ''),
            'solicitado_por' => (string) ($solicitudCompra->solicitadoPor?->name ?? ''),
            'por_almacen' => (string) ($solicitudCompra->porAlmacen?->name ?? ''),
            'aprobado_por' => (string) ($solicitudCompra->aprobadoPor?->name ?? ''),
            'recibido_por' => (string) ($solicitudCompra->recibidoPor?->name ?? ''),
            'cargo_solicitante' => (string) ($solicitudCompra->cargo_solicitante ?? ''),
            'cargo_almacen' => (string) ($solicitudCompra->cargo_almacen ?? ''),
            'cargo_aprobador' => (string) ($solicitudCompra->cargo_aprobador ?? ''),
            'cargo_receptor' => (string) ($solicitudCompra->cargo_receptor ?? ''),
            'firma_solicitante' => '',
            'firma_almacen' => '',
            'firma_aprobador' => '',
            'firma_receptor' => '',
            'fecha_solicitante' => optional($solicitudCompra->fecha_solicitante)->format('d/m/Y') ?? '',
            'fecha_almacen' => optional($solicitudCompra->fecha_almacen)->format('d/m/Y') ?? '',
            'fecha_aprobador' => optional($solicitudCompra->fecha_aprobador)->format('d/m/Y') ?? '',
            'fecha_receptor' => optional($solicitudCompra->fecha_receptor)->format('d/m/Y') ?? '',
            'hora_receptor' => (string) ($solicitudCompra->hora_receptor ?? ''),
            'hora' => (string) ($solicitudCompra->hora_receptor ?? ''),
        ];
    }

    private function renderItemRows(Worksheet $sheet, ?int $itemTemplateRow, SolicitudCompra $solicitudCompra): void
    {
        if ($itemTemplateRow === null) {
            return;
        }

        $items = $solicitudCompra->items->values()->take(self::MAX_ITEMS);
        $totalRowsToRender = self::MAX_ITEMS;
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($offset = 0; $offset < $totalRowsToRender; $offset++) {
            $targetRow = $itemTemplateRow + $offset;
            $item = $items->get($offset);
            $itemTokens = $this->buildItemTokens($item, $offset);
            $this->replaceTokensInRow($sheet, $targetRow, $itemTokens, $highestColumnIndex);
        }
    }

    private function buildItemTokens($item, int $offset): array
    {
        if (! $item) {
            return [
                'item' => '',
                'item_n' => '',
                'descripcion' => '',
                'item_descripcion' => '',
                'unidad_medida' => '',
                'item_und' => '',
                'cantidad_solicitada' => '',
                'item_solicitada' => '',
                'cantidad_existencia' => '',
                'item_existencia' => '',
                'cantidad_a_comprar' => '',
                'item_a_comprar' => '',
            ];
        }

        $itemNumber = (string) ($item->item ?? ($offset + 1));

        return [
            'item' => $itemNumber,
            'item_n' => $itemNumber,
            'descripcion' => (string) ($item->descripcion ?? ''),
            'item_descripcion' => (string) ($item->descripcion ?? ''),
            'unidad_medida' => (string) ($item->unidad_medida ?? ''),
            'item_und' => (string) ($item->unidad_medida ?? ''),
            'cantidad_solicitada' => (string) ($item->cantidad_solicitada ?? ''),
            'item_solicitada' => (string) ($item->cantidad_solicitada ?? ''),
            'cantidad_existencia' => (string) ($item->cantidad_existencia ?? ''),
            'item_existencia' => (string) ($item->cantidad_existencia ?? ''),
            'cantidad_a_comprar' => (string) ($item->cantidad_a_comprar ?? ''),
            'item_a_comprar' => (string) ($item->cantidad_a_comprar ?? ''),
        ];
    }

    private function copyTemplateRow(Worksheet $sheet, int $templateRow, int $targetRow, int $highestColumnIndex): void
    {
        $sheet->getRowDimension($targetRow)
            ->setRowHeight($sheet->getRowDimension($templateRow)->getRowHeight());

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $columnLetter = Coordinate::stringFromColumnIndex($column);
            $sourceCell = $columnLetter . $templateRow;
            $targetCell = $columnLetter . $targetRow;

            $source = $sheet->getCell($sourceCell);
            $sheet->setCellValueExplicit($targetCell, $source->getValue(), $source->getDataType());
            $sheet->duplicateStyle($sheet->getStyle($sourceCell), $targetCell);
        }

        foreach ($sheet->getMergeCells() as $mergedRange) {
            [$start, $end] = Coordinate::rangeBoundaries($mergedRange);
            $startColumn = $start[0];
            $startRow = $start[1];
            $endColumn = $end[0];
            $endRow = $end[1];

            if ($startRow === $templateRow && $endRow === $templateRow) {
                $newRange = Coordinate::stringFromColumnIndex($startColumn) . $targetRow
                    . ':'
                    . Coordinate::stringFromColumnIndex($endColumn) . $targetRow;
                $sheet->mergeCells($newRange);
            }
        }
    }

    private function findFirstRowWithItemTokens(Worksheet $sheet): ?int
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $itemTokens = [
            'item',
            'item_n',
            'descripcion',
            'item_descripcion',
            'unidad_medida',
            'item_und',
            'cantidad_solicitada',
            'item_solicitada',
            'cantidad_existencia',
            'item_existencia',
            'cantidad_a_comprar',
            'item_a_comprar',
        ];

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = (string) $sheet->getCellByColumnAndRow($column, $row)->getValue();
                if ($value === '') {
                    continue;
                }

                foreach ($itemTokens as $token) {
                    if ($this->containsTokenVariant($value, $token)) {
                        return $row;
                    }
                }
            }
        }

        return null;
    }

    private function replaceGlobalTokens(Worksheet $sheet, array $globalTokens): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $this->replaceTokensInRow($sheet, $row, $globalTokens, $highestColumnIndex);
        }
    }

    private function renderSignatureImages(Worksheet $sheet, SolicitudCompra $solicitudCompra): void
    {
        $signaturePaths = [
            'firma_solicitante' => $this->resolveSignatureImagePath($solicitudCompra->firma_solicitante),
            'firma_almacen' => $this->resolveSignatureImagePath($solicitudCompra->firma_almacen),
            'firma_aprobador' => $this->resolveSignatureImagePath($solicitudCompra->firma_aprobador),
            'firma_receptor' => $this->resolveSignatureImagePath($solicitudCompra->firma_receptor),
        ];

        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $cell = $sheet->getCellByColumnAndRow($column, $row);
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

                foreach (self::SIGNATURE_TOKENS as $token) {
                    if (! $this->containsTokenVariant($textValue, $token)) {
                        continue;
                    }

                    $cell->setValue($this->replaceTokenVariant($textValue, $token, ''));

                    $signaturePath = $signaturePaths[$token] ?? null;
                    if ($signaturePath !== null) {
                        $this->insertSignatureImage($sheet, Coordinate::stringFromColumnIndex($column) . $row, $signaturePath, $token);
                    }
                }
            }
        }
    }

    private function resolveSignatureImagePath(?string $storedPath): ?string
    {
        if (blank($storedPath) || $storedPath === '__ENVIADA__') {
            return null;
        }

        if (file_exists($storedPath)) {
            return $storedPath;
        }

        if (Storage::disk('public')->exists($storedPath)) {
            $path = Storage::disk('public')->path($storedPath);

            return file_exists($path) ? $path : null;
        }

        $publicPath = public_path('storage/' . ltrim($storedPath, '/\\'));

        return file_exists($publicPath) ? $publicPath : null;
    }

    private function insertSignatureImage(Worksheet $sheet, string $coordinates, string $imagePath, string $token): void
    {
        $drawing = new Drawing();
        $drawing->setName($token);
        $drawing->setDescription('Firma ' . $token);
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinates);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(4);
        $drawing->setResizeProportional(true);
        $drawing->setHeight(36);
        $drawing->setWorksheet($sheet);
    }

    private function replaceTokensInRow(Worksheet $sheet, int $row, array $tokenMap, int $highestColumnIndex): void
    {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $cell = $sheet->getCellByColumnAndRow($column, $row);
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

            $newValue = $textValue;
            foreach ($tokenMap as $token => $replacement) {
                $newValue = $this->replaceTokenVariant($newValue, $token, (string) $replacement);
            }

            if ($newValue !== $textValue) {
                $cell->setValue($newValue);
            }
        }
    }

    private function replaceTokenVariant(string $value, string $token, string $replacement): string
    {
        $quotedToken = preg_quote($token, '/');
        $patterns = [
            '/\{\{\s*' . $quotedToken . '\s*\}\}/u',
            '/\[\[\s*' . $quotedToken . '\s*\]\]/u',
            '/\{\s*' . $quotedToken . '\s*\}/u',
            '/%\s*' . $quotedToken . '\s*%/u',
            '/__\s*' . $quotedToken . '\s*__/u',
        ];

        return preg_replace($patterns, $replacement, $value) ?? $value;
    }

    private function containsTokenVariant(string $value, string $token): bool
    {
        $quotedToken = preg_quote($token, '/');
        $pattern = '/(\{\{\s*' . $quotedToken . '\s*\}\}|\[\[\s*' . $quotedToken . '\s*\]\]|\{\s*' . $quotedToken . '\s*\}|%\s*' . $quotedToken . '\s*%|__\s*' . $quotedToken . '\s*__)/u';

        return (bool) preg_match($pattern, $value);
    }

    private function splitTextIntoThreeLines(string $text, int $lineLimit): array
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($normalized === '') {
            return ['', '', ''];
        }

        $line1 = $this->sliceByWordBoundary($normalized, $lineLimit);
        $remaining = ltrim(mb_substr($normalized, mb_strlen($line1)));

        if ($remaining === '') {
            return [$line1, '', ''];
        }

        $line2 = $this->sliceByWordBoundary($remaining, $lineLimit);
        $remaining = ltrim(mb_substr($remaining, mb_strlen($line2)));

        if ($remaining === '') {
            return [$line1, $line2, ''];
        }

        $line3 = $this->sliceByWordBoundary($remaining, $lineLimit);

        return [$line1, $line2, $line3];
    }

    private function sliceByWordBoundary(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $segment = mb_substr($text, 0, $limit + 1);
        $breakPos = mb_strrpos($segment, ' ');

        if ($breakPos === false || $breakPos < (int) floor($limit * 0.6)) {
            return rtrim(mb_substr($text, 0, $limit));
        }

        return rtrim(mb_substr($segment, 0, $breakPos));
    }

    private function normalizeSheetForPdf(Worksheet $sheet): void
    {
        $pageSetup = $sheet->getPageSetup();
        $endColumn = self::PDF_MAX_END_COLUMN;
        $endRow = self::PDF_MAX_END_ROW;

        $pageSetup->setPrintArea(
            self::PDF_PRINT_AREA_START
            . ':'
            . $endColumn
            . $endRow
        );

        $pageMargins = $sheet->getPageMargins();
        $pageMargins->setTop(0.5);
        $pageMargins->setBottom(0.5);
        $pageMargins->setLeft(0.5);
        $pageMargins->setRight(0.5);

        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $pageSetup->setHorizontalCentered(true);
        $pageSetup->setVerticalCentered(true);
    }

    private function convertExcelToPdfWithLibreOffice(string $xlsxPath, string $pdfPath, string $outputDir): bool
    {
        $binary = $this->resolveLibreOfficeBinary();
        if ($binary === null) {
            return false;
        }

        $libreOfficeProfileDir = storage_path('app/libreoffice-profile');
        if (! is_dir($libreOfficeProfileDir)) {
            mkdir($libreOfficeProfileDir, 0755, true);
        }

        $libreOfficeHome = storage_path('app/libreoffice-home');
        if (! is_dir($libreOfficeHome)) {
            mkdir($libreOfficeHome, 0755, true);
        }

        $profileRealPath = realpath($libreOfficeProfileDir) ?: $libreOfficeProfileDir;
        $profileUri = 'file://' . str_replace(DIRECTORY_SEPARATOR, '/', $profileRealPath);

        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '-env:UserInstallation=' . $profileUri,
            '--convert-to',
            'pdf:calc_pdf_Export',
            '--outdir',
            $outputDir,
            $xlsxPath,
        ]);
        $process->setEnv([
            'HOME' => $libreOfficeHome,
            'XDG_CACHE_HOME' => $libreOfficeHome . DIRECTORY_SEPARATOR . '.cache',
            'XDG_CONFIG_HOME' => $libreOfficeHome . DIRECTORY_SEPARATOR . '.config',
            'SAL_USE_VCLPLUGIN' => 'svp',
        ]);
        $process->setTimeout(self::LIBREOFFICE_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Fallo conversion LibreOffice.', [
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
            Log::warning('LibreOffice no genero el PDF esperado.', [
                'expected_pdf' => $generatedPdfPath,
                'xlsx' => $xlsxPath,
            ]);

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
        $fromPath = $finder->find('libreoffice')
            ?? $finder->find('soffice')
            ?? $finder->find('soffice.com');

        if ($fromPath !== null) {
            return $fromPath;
        }

        return null;
    }
}
