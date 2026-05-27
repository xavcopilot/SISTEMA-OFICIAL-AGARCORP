<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\LibreOfficePdfConverter;
use App\Support\SolicitudCompraFlow;
use App\Support\UserSignaturePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolicitudCompraFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'PLANILLA DE FORMATO DE COMPRA.xlsx';
    private const PDF_PRINT_AREA_START = 'C3';
    private const PDF_MAX_END_COLUMN = 'M';
    private const PDF_MAX_END_ROW = 48;
    private const USO_LINE_MAX = 80;
    private const MAX_ITEMS = 15;
    private const USAR_CODIGOS_PREDEFINIDOS = false;
    private const CODIGO_CONTROL_PREDEFINIDO = 'CTRL-2026-000123';
    private const CODIGO_PROCURA_PREDEFINIDO = 'PROC-2026-000123';
    private const DEFAULT_SIGNATURE_HEIGHT = 80;
    private const SIGNATURE_TOKENS = [
        'firma_solicitante',
        'firma_almacen',
        'firma_aprobador',
        'firma_receptor',
    ];
    private const SIGNATURE_RENDER_OVERRIDES = [
        15 => ['height' => 100, 'offset_x' => 0, 'offset_y' => 0],
        3 => ['height' => 50, 'offset_x' => 5, 'offset_y' => 30],
        14 => ['height' => 90, 'offset_x' => 0, 'offset_y' => 0],
        12 => ['height' => 70, 'offset_x' => 0, 'offset_y' => 0],
        16 => ['height' => 60, 'offset_x' => 0, 'offset_y' => 30],
        2 => ['height' => 120, 'offset_x' => 6, 'offset_y' => 0],
    ];

    public function __construct(private LibreOfficePdfConverter $libreOfficePdfConverter)
    {
    }

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

        $this->configureExportRuntime();

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
        $fileBaseName = 'solicitud-compra-' . $solicitudCompra->id . '-' . now()->format('YmdHisu') . '-' . bin2hex(random_bytes(3));
        $excelFileName = 'SOLICITUD_COMPRA_' . $solicitudCompra->id . '.xlsx';
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $globalTokens = $this->buildGlobalTokens($solicitudCompra, $usoLinea1, $usoLinea2, $usoLinea3);
            $itemTemplateRow = $this->findFirstRowWithItemTokens($sheet);

            $this->renderSignatureImages($sheet, $solicitudCompra);
            $this->replaceGlobalTokens($sheet, $globalTokens);
            $this->renderItemRows($sheet, $itemTemplateRow, $solicitudCompra);
            $this->normalizeSheetForPdf($sheet);

            $xlsxWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $xlsxWriter->save($xlsxPath);

            if ($outputFormat === 'xlsx') {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                return response()->download($xlsxPath, $excelFileName)->deleteFileAfterSend(true);
            }

            $wasConvertedByLibreOffice = $this->libreOfficePdfConverter->convertSpreadsheetToPdf(
                $xlsxPath,
                $pdfPath,
                $tmpDir,
                ['documento' => 'solicitud_compra']
            );
            if (! $wasConvertedByLibreOffice) {
                Log::warning('Fallo conversion LibreOffice para Solicitud. No se usara fallback PDF.', [
                    'solicitud_id' => $solicitudCompra->id,
                ]);

                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF de la solicitud porque LibreOffice no pudo convertir el archivo.');
            }

            if (! file_exists($pdfPath) || filesize($pdfPath) < 100) {
                Log::error('PDF de solicitud generado pero vacio o demasiado pequeno.', [
                    'solicitud_id' => $solicitudCompra->id,
                    'was_converted_by_libreoffice' => $wasConvertedByLibreOffice,
                    'pdf_size' => file_exists($pdfPath) ? filesize($pdfPath) : 0,
                ]);

                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF de la solicitud. Verifique que LibreOffice este instalado en el servidor.');
            }

            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
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

    private function configureExportRuntime(): void
    {
        $memoryLimit = (string) env('PDF_EXPORT_MEMORY_LIMIT', '512M');
        $executionTime = (int) env('PDF_EXPORT_MAX_EXECUTION_TIME', 180);

        @ini_set('memory_limit', $memoryLimit);

        if ($executionTime > 0) {
            @set_time_limit($executionTime);
            @ini_set('max_execution_time', (string) $executionTime);
        }
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
        $signatureEntries = [
            'firma_solicitante' => [
                'path' => $this->resolveSignatureImagePath($solicitudCompra->firma_solicitante, $solicitudCompra->solicitadoPor),
                'signer' => $solicitudCompra->solicitadoPor,
            ],
            'firma_almacen' => [
                'path' => $this->resolveSignatureImagePath($solicitudCompra->firma_almacen, $solicitudCompra->porAlmacen),
                'signer' => $solicitudCompra->porAlmacen,
            ],
            'firma_aprobador' => [
                'path' => $this->resolveSignatureImagePath($solicitudCompra->firma_aprobador, $solicitudCompra->aprobadoPor),
                'signer' => $solicitudCompra->aprobadoPor,
            ],
            'firma_receptor' => [
                'path' => $this->resolveSignatureImagePath($solicitudCompra->firma_receptor, $solicitudCompra->recibidoPor),
                'signer' => $solicitudCompra->recibidoPor,
            ],
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

                    $signatureEntry = $signatureEntries[$token] ?? null;
                    $signaturePath = $signatureEntry['path'] ?? null;

                    if ($signaturePath !== null) {
                        $this->insertSignatureImage(
                            $sheet,
                            Coordinate::stringFromColumnIndex($column) . $row,
                            $signaturePath,
                            $token,
                            $signatureEntry['signer'] ?? null
                        );
                    }
                }
            }
        }
    }

    private function resolveSignatureImagePath(?string $storedPath, ?User $signer = null): ?string
    {
        $normalizedPath = trim((string) $storedPath);

        if ($normalizedPath === '') {
            return null;
        }

        if ($normalizedPath === '__ENVIADA__') {
            if (! $signer) {
                return null;
            }

            $expectedPath = UserSignaturePath::findByUserId((int) $signer->id);

            if ($expectedPath && Storage::disk('public')->exists($expectedPath)) {
                $absolutePath = Storage::disk('public')->path($expectedPath);

                return file_exists($absolutePath) ? $absolutePath : null;
            }

            return null;
        }

        if (file_exists($normalizedPath)) {
            return $normalizedPath;
        }

        if (Storage::disk('public')->exists($normalizedPath)) {
            $path = Storage::disk('public')->path($normalizedPath);

            return file_exists($path) ? $path : null;
        }

        $publicPath = public_path('storage/' . ltrim($normalizedPath, '/\\'));

        return file_exists($publicPath) ? $publicPath : null;
    }

    private function insertSignatureImage(Worksheet $sheet, string $coordinates, string $imagePath, string $token, ?User $signer = null): void
    {
        $settings = $this->resolveSignatureRenderSettings($signer);

        $drawing = new Drawing();
        $drawing->setName($token);
        $drawing->setDescription('Firma ' . $token);
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinates);
        $drawing->setOffsetX((int) ($settings['offset_x'] ?? 0));
        $drawing->setOffsetY((int) ($settings['offset_y'] ?? 0));
        $drawing->setResizeProportional(true);
        $drawing->setHeight((int) ($settings['height'] ?? self::DEFAULT_SIGNATURE_HEIGHT));
        $drawing->setWorksheet($sheet);
    }

    private function resolveSignatureRenderSettings(?User $signer = null): array
    {
        if (! $signer) {
            return ['height' => self::DEFAULT_SIGNATURE_HEIGHT, 'offset_x' => 0, 'offset_y' => 0];
        }

        return array_replace(
            ['height' => self::DEFAULT_SIGNATURE_HEIGHT, 'offset_x' => 0, 'offset_y' => 0],
            self::SIGNATURE_RENDER_OVERRIDES[(int) $signer->id] ?? []
        );
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

}
