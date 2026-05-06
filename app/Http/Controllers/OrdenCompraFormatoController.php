<?php

namespace App\Http\Controllers;

use App\Models\InformacionAgarcorp;
use App\Models\OrdenCompra;
use App\Models\User;
use App\Support\LibreOfficePdfConverter;
use App\Support\UserSignaturePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenCompraFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'FORMATO ODC.xlsx';
    private const PDF_PRINT_AREA_START = 'B6';
    private const PDF_PRINT_AREA_END = 'H63';
    private const ITEMS_START_ROW = 32;
    private const ITEMS_END_ROW = 43;
    private const SIGNATURE_TOKENS = [
        'firma_elaborado',
        'firma_aprobado',
    ];

    public function __construct(private LibreOfficePdfConverter $libreOfficePdfConverter)
    {
    }

    public function printPreview(OrdenCompra $ordenCompra)
    {
        if (! auth()->check()) {
            abort(401);
        }

        return view('ordenes-compra.print-preview', [
            'ordenCompra' => $ordenCompra,
            'pdfUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra]),
            'downloadUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'download' => 1]),
            'excelUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'format' => 'xlsx', 'download' => 1]),
        ]);
    }

    public function __invoke(OrdenCompra $ordenCompra)
    {
        if (! auth()->check()) {
            abort(401);
        }

        $ordenCompra->loadMissing([
            'items',
            'proveedor',
            'elaboradoPor.cargo',
            'aprobadoPor.cargo',
            'sumario.solicitudCompra',
            'sumario.elaboradoPor.cargo',
            'sumario.revisadoPor.cargo',
            'sumario.decisionGerenciaPor.cargo',
        ]);

        $templatePath = storage_path('app/templates/' . self::EXCEL_TEMPLATE_FILE);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla Excel FORMATO ODC.xlsx en storage/app/templates.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'orden-compra-' . $ordenCompra->id . '-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';
        $excelFileName = 'ODC_' . ($ordenCompra->correlativo_odc ?: $ordenCompra->id) . '.xlsx';
        $pdfFileName = 'ODC_' . ($ordenCompra->correlativo_odc ?: $ordenCompra->id) . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $missingTokens = $this->findMissingTokens(
                $sheet,
                array_merge($this->requiredGlobalTokenNames(), $this->requiredItemTokenNames())
            );

            if ($missingTokens !== []) {
                return response(
                    'Faltan placeholders requeridos en FORMATO ODC.xlsx: ' . implode(', ', $missingTokens),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }

            $globalTokens = $this->buildGlobalTokens($ordenCompra);
            $itemRowsWithTokens = $this->findRowsWithAnyTokens($sheet, $this->itemTokenNames());

            $this->renderSignatureImages($sheet, $ordenCompra);
            $this->replaceGlobalTokens($sheet, $globalTokens);
            $this->renderItemsByTokenRows($sheet, $ordenCompra, $itemRowsWithTokens);

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
                ['documento' => 'orden_compra']
            );

            if (! $wasConvertedByLibreOffice || ! file_exists($pdfPath)) {
                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF con LibreOffice para la ODC.');
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

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar la ODC desde la plantilla Excel.');
        }

        if (request()->boolean('download')) {
            return response()->download($pdfPath, $pdfFileName)->deleteFileAfterSend(true);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdfFileName . '"',
        ])->deleteFileAfterSend(true);
    }

    private function buildGlobalTokens(OrdenCompra $ordenCompra): array
    {
        $proveedor = $ordenCompra->proveedor;
        $sumario = $ordenCompra->sumario;
        $informacionImpresa = InformacionAgarcorp::current();
        $elaboradoPor = $ordenCompra->elaboradoPor ?: $sumario?->elaboradoPor;
        $aprobadoPor = $ordenCompra->aprobadoPor ?: $sumario?->decisionGerenciaPor;

        $elaboradoFecha = (string) ($ordenCompra->elaborado_firmado_at
            ? 'Registrada el ' . $ordenCompra->elaborado_firmado_at->format('d/m/Y H:i')
            : 'Pendiente por registrar');

        $aprobadoFecha = (string) ($ordenCompra->aprobado_firmado_at
            ? 'Registrada el ' . $ordenCompra->aprobado_firmado_at->format('d/m/Y H:i')
            : 'Pendiente por validacion de Gerencia de Finanzas');

        return [
            'correlativo_odc' => (string) ($ordenCompra->correlativo_odc ?? ''),
            'fecha_odc' => (string) optional($ordenCompra->created_at)->format('d/m/Y'),
            'proveedor_nombre' => (string) ($proveedor?->nombre ?? ''),
            'rif_proveedor' => (string) ($ordenCompra->rif_proveedor ?? $proveedor?->rif ?? ''),
            'telefono_proveedor' => (string) ($proveedor?->telefono ?? ''),
            'direccion_proveedor' => (string) ($ordenCompra->direccion_proveedor ?? $proveedor?->direccion ?? ''),
            'tiempo_entrega' => (string) ($sumario?->tiempo_entrega ?? ''),
            'ciudad_proveedor' => (string) ($proveedor?->ciudad ?? ''),
            'email_proveedor' => (string) ($ordenCompra->email_proveedor ?? $proveedor?->email ?? ''),
            'contacto_proveedor' => (string) ($ordenCompra->contacto_proveedor ?? $proveedor?->contacto ?? ''),

            'monto_exento' => (float) ($ordenCompra->monto_exento ?? 0),
            'sub_total' => (float) ($ordenCompra->sub_total ?? 0),
            'iva_16' => (float) ($ordenCompra->iva_16 ?? 0),
            'gastos_adicionales' => (float) ($ordenCompra->gastos_adicionales ?? 0),
            'total_general' => (float) ($ordenCompra->total_general ?? 0),
            'total_en_letras' => $this->numberToWordsEs((float) ($ordenCompra->total_general ?? 0)) . ' BOLIVARES',

            'sitio_entrega' => (string) ($ordenCompra->sitio_entrega ?: 'ALMACEN AGARCORP'),
            'condicion_pago' => (string) ($ordenCompra->condicion_pago ?? ''),
            'comentarios' => (string) ($ordenCompra->comentarios ?? $sumario?->observaciones ?? ''),
            'tasa_bcv' => (float) ($ordenCompra->tasa_bcv ?? 0),
            'departamento_solicitante' => (string) ($ordenCompra->departamento_solicitante ?? ''),
            'correlativo_sdc' => (string) ($sumario?->correlativo_sdc ?? ''),

            'empresa_razon_social' => (string) ($informacionImpresa->razon_social ?? ''),
            'empresa_rif' => (string) ($informacionImpresa->rif ?? ''),
            'empresa_direccion_fiscal' => (string) ($informacionImpresa->direccion_fiscal ?? ''),
            'empresa_telefono_principal' => (string) ($informacionImpresa->telefono_principal ?? ''),
            'empresa_nombre' => (string) ($informacionImpresa->razon_social ?? ''),
            'empresa_direccion' => (string) ($informacionImpresa->direccion_fiscal ?? ''),
            'empresa_telefono' => (string) ($informacionImpresa->telefono_principal ?? ''),

            'firma_elaborado' => '',
            'firma_aprobado' => '',

            'elaborado_por_nombre' => (string) ($elaboradoPor?->name ?? ''),
            'elaborado_por_cargo' => (string) ($elaboradoPor?->cargo?->nombre ?? ''),
            'elaborado_fecha' => $elaboradoFecha,
            'aprobado_por_nombre' => (string) ($aprobadoPor?->name ?? ''),
            'aprobado_por_cargo' => (string) ($aprobadoPor?->cargo?->nombre ?? ''),
            'aprobado_fecha' => $aprobadoFecha,
        ];
    }

    private function renderSignatureImages(Worksheet $sheet, OrdenCompra $ordenCompra): void
    {
        $sumario = $ordenCompra->sumario;
        $signaturePaths = [
            'firma_elaborado' => $this->resolveSignatureImagePath($ordenCompra->elaboradoPor ?: $sumario?->elaboradoPor),
            'firma_aprobado' => $this->resolveSignatureImagePath($ordenCompra->aprobadoPor ?: $sumario?->decisionGerenciaPor),
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

    private function resolveSignatureImagePath(?User $signer = null): ?string
    {
        if (! $signer) {
            return null;
        }

        $expectedPath = UserSignaturePath::findByUserId((int) $signer->id);

        if (! $expectedPath || ! Storage::disk('public')->exists($expectedPath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($expectedPath);

        return file_exists($absolutePath) ? $absolutePath : null;
    }

    private function insertSignatureImage(Worksheet $sheet, string $coordinates, string $imagePath, string $token): void
    {
        $drawing = new Drawing();
        $drawing->setName($token);
        $drawing->setDescription('Firma ' . $token);
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinates);
        $drawing->setOffsetX(0);
        $drawing->setOffsetY(0);
        $drawing->setResizeProportional(true);
        $drawing->setHeight(120);
        $drawing->setWorksheet($sheet);
    }

    private function requiredGlobalTokenNames(): array
    {
        return [
            'correlativo_odc',
            'fecha_odc',
            'proveedor_nombre',
            'rif_proveedor',
            'telefono_proveedor',
            'direccion_proveedor',
            'tiempo_entrega',
            'ciudad_proveedor',
            'email_proveedor',
            'contacto_proveedor',
            'monto_exento',
            'sub_total',
            'iva_16',
            'gastos_adicionales',
            'total_general',
            'total_en_letras',
            'sitio_entrega',
            'condicion_pago',
            'comentarios',
            'tasa_bcv',
            'departamento_solicitante',
            'correlativo_sdc',
            'elaborado_por_nombre',
            'elaborado_por_cargo',
            'aprobado_por_nombre',
            'aprobado_por_cargo',
        ];
    }

    private function itemTokenNames(): array
    {
        return [
            'item',
            'item_n',
            'descripcion',
            'item_descripcion',
            'unidad_medida',
            'item_unidad_medida',
            'cantidad',
            'item_cantidad',
            'precio_unitario',
            'item_precio_unitario',
            'precio_total',
            'item_precio_total',
        ];
    }

    private function requiredItemTokenNames(): array
    {
        return [
            'item',
            'descripcion',
            'unidad_medida',
            'cantidad',
            'precio_unitario',
            'precio_total',
        ];
    }

    private function renderItemsByTokenRows(Worksheet $sheet, OrdenCompra $ordenCompra, array $rows): void
    {
        $items = $ordenCompra->items->values();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $totalRows = count($rows);

        for ($index = 0; $index < $totalRows; $index++) {
            $row = $rows[$index];
            $item = $items->get($index);
            $descriptionColumn = $this->findTokenColumnInRow($sheet, $row, ['descripcion', 'item_descripcion'], $highestColumnIndex);
            $tokens = $this->buildItemTokens($item, $index + 1);
            $this->replaceTokensInRow($sheet, $row, $tokens, $highestColumnIndex);

            if ($descriptionColumn !== null && $item) {
                $this->adjustItemDescriptionRowHeight($sheet, $row, $descriptionColumn, (string) ($item->descripcion ?? ''));
            }
        }
    }

    private function findTokenColumnInRow(Worksheet $sheet, int $row, array $tokens, int $highestColumnIndex): ?int
    {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $value = (string) $sheet->getCellByColumnAndRow($column, $row)->getValue();

            if ($value === '') {
                continue;
            }

            foreach ($tokens as $token) {
                if ($this->containsTokenVariant($value, (string) $token)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function adjustItemDescriptionRowHeight(Worksheet $sheet, int $row, int $column, string $description): void
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $style = $sheet->getStyle($coordinate)->getAlignment();
        $style->setWrapText(true);
        $style->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        $lineBreaks = substr_count($description, "\n") + 1;
        $descriptionLength = max(1, mb_strlen(trim($description)));
        $columnWidth = (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->getWidth();
        $effectiveWidth = $columnWidth > 0 ? $columnWidth : 30.0;
        $charsPerLine = max(12, (int) floor($effectiveWidth * 1.1));
        $estimatedWrappedLines = (int) ceil($descriptionLength / $charsPerLine);
        $lines = max($lineBreaks, $estimatedWrappedLines);

        $rowDimension = $sheet->getRowDimension($row);
        $baseHeight = (float) $rowDimension->getRowHeight();
        if ($baseHeight <= 0) {
            $baseHeight = (float) $sheet->getDefaultRowDimension()->getRowHeight();
        }
        if ($baseHeight <= 0) {
            $baseHeight = 15.0;
        }

        $rowDimension->setRowHeight(max($baseHeight, $baseHeight * $lines));
    }

    private function buildItemTokens(mixed $item, int $fallbackIndex): array
    {
        if (! $item) {
            return [
                'item' => '',
                'item_n' => '',
                'descripcion' => '',
                'item_descripcion' => '',
                'unidad_medida' => '',
                'item_unidad_medida' => '',
                'cantidad' => '',
                'item_cantidad' => '',
                'precio_unitario' => '',
                'item_precio_unitario' => '',
                'precio_total' => '',
                'item_precio_total' => '',
            ];
        }

        return [
            'item' => (string) ($item->item ?? $fallbackIndex),
            'item_n' => (string) ($item->item ?? $fallbackIndex),
            'descripcion' => (string) ($item->descripcion ?? ''),
            'item_descripcion' => (string) ($item->descripcion ?? ''),
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'item_unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => (float) ($item->cantidad ?? 0),
            'item_cantidad' => (float) ($item->cantidad ?? 0),
            'precio_unitario' => (float) ($item->precio_unitario ?? 0),
            'item_precio_unitario' => (float) ($item->precio_unitario ?? 0),
            'precio_total' => (float) ($item->precio_total ?? 0),
            'item_precio_total' => (float) ($item->precio_total ?? 0),
        ];
    }

    private function replaceGlobalTokens(Worksheet $sheet, array $tokens): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $this->replaceTokensInRow($sheet, $row, $tokens, $highestColumnIndex);
        }
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

    private function findRowsWithAnyTokens(Worksheet $sheet, array $tokens): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = (string) $sheet->getCellByColumnAndRow($column, $row)->getValue();

                if ($value === '') {
                    continue;
                }

                foreach ($tokens as $token) {
                    if ($this->containsTokenVariant($value, $token)) {
                        $rows[] = $row;
                        break 2;
                    }
                }
            }
        }

        return array_values(array_unique($rows));
    }

    private function findMissingTokens(Worksheet $sheet, array $tokens): array
    {
        $pending = [];
        foreach ($tokens as $token) {
            $pending[(string) $token] = true;
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = (string) $sheet->getCellByColumnAndRow($column, $row)->getValue();

                if ($value === '' || $pending === []) {
                    continue;
                }

                foreach (array_keys($pending) as $token) {
                    if ($this->containsTokenVariant($value, $token)) {
                        unset($pending[$token]);
                    }
                }

                if ($pending === []) {
                    break 2;
                }
            }
        }

        return array_keys($pending);
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

    private function normalizeSheetForPdf(Worksheet $sheet): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPrintArea(self::PDF_PRINT_AREA_START . ':' . self::PDF_PRINT_AREA_END);

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

    private function numberToWordsEs(float $number): string
    {
        $integerPart = (int) floor($number);
        $decimalPart = (int) round(($number - $integerPart) * 100);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter('es_VE', NumberFormatter::SPELLOUT);
            $words = (string) $formatter->format($integerPart);
        } else {
            $words = number_format((float) $integerPart, 0, ',', '.');
        }

        return mb_strtoupper(trim($words) . ' CON ' . str_pad((string) $decimalPart, 2, '0', STR_PAD_LEFT) . '/100');
    }
}
