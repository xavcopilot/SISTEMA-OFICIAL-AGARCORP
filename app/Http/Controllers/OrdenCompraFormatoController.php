<?php

namespace App\Http\Controllers;

use App\Models\InformacionAgarcorp;
use App\Models\OrdenCompra;
use App\Models\User;
use App\Support\LibreOfficePdfConverter;
use App\Support\UserSignaturePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfDompdfWriter;
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
    private const DEFAULT_VARIANT = 'divisas';
    private const PDF_PRINT_AREA_START = 'B6';
    private const PDF_PRINT_AREA_END = 'H65';
    private const PDF_PRINT_AREA_END_BOLIVARES = 'I65';
    private const ITEMS_START_ROW = 32;
    private const ITEMS_END_ROW = 43;
    private const DEFAULT_SIGNATURE_HEIGHT = 100;
    private const SIGNATURE_TOKENS = [
        'firma_elaborado',
        'firma_aprobado',
    ];
    private const SIGNATURE_RENDER_OVERRIDES = [
        2 => ['height' => 90, 'offset_x' => 20, 'offset_y' => 0],
        14 => ['height' => 90, 'offset_x' => 8, 'offset_y' => 2],
    ];
    private const TEMPLATE_VARIANTS = [
        'divisas' => [
            'template' => 'FORMATO ODC.xlsx',
            'label' => 'ODC DIVISAS',
            'filename_suffix' => 'DIVISAS',
        ],
        'bolivares' => [
            'template' => 'FORMATO ODC CON BOLIVARES.xlsx',
            'label' => 'ODC CON BOLIVARES',
            'filename_suffix' => 'BOLIVARES',
        ],
    ];

    public function __construct(private LibreOfficePdfConverter $libreOfficePdfConverter)
    {
    }

    public function printPreview(OrdenCompra $ordenCompra)
    {
        if (! auth()->check()) {
            abort(401);
        }

        $selectedVariant = $this->resolveVariant();

        return view('ordenes-compra.print-preview', [
            'ordenCompra' => $ordenCompra,
            'selectedVariant' => $selectedVariant,
            'variantOptions' => [
                'divisas' => [
                    'label' => 'Ver ODC Divisas',
                    'pdfUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'divisas']),
                    'downloadUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'divisas', 'download' => 1]),
                    'excelUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'divisas', 'format' => 'xlsx', 'download' => 1]),
                ],
                'bolivares' => [
                    'label' => 'Ver ODC con Bolivares',
                    'pdfUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'bolivares']),
                    'downloadUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'bolivares', 'download' => 1]),
                    'excelUrl' => route('ordenes-compra.formato', ['ordenCompra' => $ordenCompra, 'variant' => 'bolivares', 'format' => 'xlsx', 'download' => 1]),
                ],
            ],
        ]);
    }

    public function __invoke(OrdenCompra $ordenCompra)
    {
        if (! auth()->check()) {
            abort(401);
        }

        $variant = $this->resolveVariant();
        $variantConfig = self::TEMPLATE_VARIANTS[$variant];

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

        $templatePath = storage_path('app/templates/' . $variantConfig['template']);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla Excel ' . $variantConfig['template'] . ' en storage/app/templates.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'orden-compra-' . $variant . '-' . $ordenCompra->id . '-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';
        $excelFileName = 'ODC_' . $variantConfig['filename_suffix'] . '_' . ($ordenCompra->correlativo_odc ?: $ordenCompra->id) . '.xlsx';
        $pdfFileName = 'ODC_' . $variantConfig['filename_suffix'] . '_' . ($ordenCompra->correlativo_odc ?: $ordenCompra->id) . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $missingTokens = $this->findMissingTokens(
                $sheet,
                array_merge($this->requiredGlobalTokenNames($variant), $this->requiredItemTokenNames($variant))
            );

            if ($missingTokens !== []) {
                return response(
                    'Faltan placeholders requeridos en ' . $variantConfig['template'] . ': ' . implode(', ', $missingTokens),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }

            $globalTokens = $this->buildGlobalTokens($ordenCompra, $variant);
            $itemRowsWithTokens = $this->findRowsWithAnyTokens($sheet, $this->itemTokenNames());

            $this->renderSignatureImages($sheet, $ordenCompra);
            $this->replaceGlobalTokens($sheet, $globalTokens);
            $this->renderItemsByTokenRows($sheet, $ordenCompra, $itemRowsWithTokens, $variant);

            $this->normalizeSheetForPdf($sheet, $variant);

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

            if (! $wasConvertedByLibreOffice) {
                Log::warning('Fallo conversion LibreOffice para ODC, se usara fallback Dompdf.', [
                    'orden_compra_id' => $ordenCompra->id,
                    'variant' => $variant,
                ]);

                $pdfWriter = new PdfDompdfWriter($spreadsheet);
                $pdfWriter->save($pdfPath);
            }

            if (! file_exists($pdfPath) || filesize($pdfPath) < 100) {
                Log::error('PDF de ODC generado pero vacio o demasiado pequeno.', [
                    'orden_compra_id' => $ordenCompra->id,
                    'variant' => $variant,
                    'was_converted_by_libreoffice' => $wasConvertedByLibreOffice,
                    'pdf_size' => file_exists($pdfPath) ? filesize($pdfPath) : 0,
                ]);

                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF de la ODC. Verifique que LibreOffice este instalado en el servidor.');
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

    private function buildGlobalTokens(OrdenCompra $ordenCompra, string $variant = self::DEFAULT_VARIANT): array
    {
        $proveedor = $ordenCompra->proveedor;
        $sumario = $ordenCompra->sumario;
        $informacionImpresa = InformacionAgarcorp::current();
        $elaboradoPor = $ordenCompra->elaboradoPor ?: $sumario?->elaboradoPor;
        $aprobadoPor = $ordenCompra->aprobadoPor ?: $sumario?->decisionGerenciaPor;
        $bcvRate = max(0, (float) ($ordenCompra->tasa_bcv ?? 0));
        $useBolivares = $variant === 'bolivares';

        $montoExento = (float) ($ordenCompra->monto_exento ?? 0);
        $subTotal = (float) ($ordenCompra->sub_total ?? 0);
        $iva = (float) ($ordenCompra->iva_16 ?? 0);
        $gastosAdicionales = (float) ($ordenCompra->gastos_adicionales ?? 0);
        $totalGeneral = (float) ($ordenCompra->total_general ?? 0);

        if ($useBolivares) {
            $montoExento *= $bcvRate;
            $subTotal *= $bcvRate;
            $iva *= $bcvRate;
            $gastosAdicionales *= $bcvRate;
            $totalGeneral *= $bcvRate;
        }

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

            'monto_exento' => $montoExento,
            'sub_total' => $subTotal,
            'iva_16' => $iva,
            'gastos_adicionales' => $gastosAdicionales,
            'total_general' => $totalGeneral,
            'total_en_letras' => $this->numberToWordsEs($totalGeneral) . ' BOLIVARES',
            'monto_exento_bs' => round((float) ($ordenCompra->monto_exento ?? 0) * $bcvRate, 2),
            'sub_total_bs' => round((float) ($ordenCompra->sub_total ?? 0) * $bcvRate, 2),
            'iva_16_bs' => round((float) ($ordenCompra->iva_16 ?? 0) * $bcvRate, 2),
            'gastos_adicionales_bs' => round((float) ($ordenCompra->gastos_adicionales ?? 0) * $bcvRate, 2),
            'total_general_bs' => round((float) ($ordenCompra->total_general ?? 0) * $bcvRate, 2),
            'total_en_letras_bs' => $this->numberToWordsEs(round((float) ($ordenCompra->total_general ?? 0) * $bcvRate, 2)) . ' BOLIVARES',

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
        $signatureEntries = [
            'firma_elaborado' => [
                'path' => $this->resolveSignatureImagePath(
                    $ordenCompra->elaboradoPor ?: $sumario?->elaboradoPor,
                    filled($ordenCompra->elaborado_firmado_at) && filled($ordenCompra->elaborado_por_user_id)
                ),
                'signer' => $ordenCompra->elaboradoPor ?: $sumario?->elaboradoPor,
            ],
            'firma_aprobado' => [
                'path' => $this->resolveSignatureImagePath(
                    $ordenCompra->aprobadoPor ?: $sumario?->decisionGerenciaPor,
                    filled($ordenCompra->aprobado_firmado_at) && filled($ordenCompra->aprobado_por_user_id)
                ),
                'signer' => $ordenCompra->aprobadoPor ?: $sumario?->decisionGerenciaPor,
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

    private function resolveSignatureImagePath(?User $signer = null, bool $isSigned = true): ?string
    {
        if (! $isSigned) {
            return null;
        }

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

    private function requiredGlobalTokenNames(string $variant = self::DEFAULT_VARIANT): array
    {
        $required = [
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

        if ($variant === 'bolivares') {
            $required = array_merge($required, [
                ['monto_exento', 'monto_exento_bs'],
                ['sub_total', 'sub_total_bs'],
                ['iva_16', 'iva_16_bs'],
                ['gastos_adicionales', 'gastos_adicionales_bs'],
                ['total_general', 'total_general_bs'],
                ['total_en_letras', 'total_en_letras_bs'],
            ]);
        } else {
            $required = array_merge($required, [
                'monto_exento',
                'sub_total',
                'iva_16',
                'gastos_adicionales',
                'total_general',
                'total_en_letras',
            ]);
        }

        return $required;
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
            'precio_unitario_bs',
            'item_precio_unitario_bs',
            'precio_total',
            'item_precio_total',
            'precio_total_divisas',
            'item_precio_total_divisas',
            'precio_total_bs',
            'item_precio_total_bs',
        ];
    }

    private function requiredItemTokenNames(string $variant = self::DEFAULT_VARIANT): array
    {
        $required = [
            'item',
            'descripcion',
            'unidad_medida',
            'cantidad',
        ];

        if ($variant === 'bolivares') {
            $required[] = ['precio_unitario', 'precio_unitario_bs'];
            $required[] = ['precio_total', 'precio_total_bs'];
        } else {
            $required[] = 'precio_unitario';
            $required[] = 'precio_total';
        }

        return $required;
    }

    private function renderItemsByTokenRows(Worksheet $sheet, OrdenCompra $ordenCompra, array $rows, string $variant = self::DEFAULT_VARIANT): void
    {
        $items = $ordenCompra->items->values();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $totalRows = count($rows);

        for ($index = 0; $index < $totalRows; $index++) {
            $row = $rows[$index];
            $item = $items->get($index);
            $descriptionColumn = $this->findTokenColumnInRow($sheet, $row, ['descripcion', 'item_descripcion'], $highestColumnIndex);
            $tokens = $this->buildItemTokens($item, $index + 1, $ordenCompra, $variant);
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

    private function buildItemTokens(mixed $item, int $fallbackIndex, OrdenCompra $ordenCompra, string $variant = self::DEFAULT_VARIANT): array
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
                'precio_unitario_bs' => '',
                'item_precio_unitario_bs' => '',
                'precio_total' => '',
                'item_precio_total' => '',
                'precio_total_divisas' => '',
                'item_precio_total_divisas' => '',
                'precio_total_bs' => '',
                'item_precio_total_bs' => '',
            ];
        }

        $quantity = (float) ($item->cantidad ?? 0);
        $unitPriceDivisas = (float) ($item->precio_unitario ?? 0);
        $totalDivisas = (float) ($item->precio_total ?? 0);
        $bcvRate = max(0, (float) ($ordenCompra->tasa_bcv ?? 0));
        $unitPriceBolivares = round($unitPriceDivisas * $bcvRate, 2);
        $totalBolivares = round($quantity * $unitPriceBolivares, 2);
        $displayTotal = $variant === 'bolivares' ? $totalBolivares : $totalDivisas;

        return [
            'item' => (string) ($item->item ?? $fallbackIndex),
            'item_n' => (string) ($item->item ?? $fallbackIndex),
            'descripcion' => (string) ($item->descripcion ?? ''),
            'item_descripcion' => (string) ($item->descripcion ?? ''),
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'item_unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => $quantity,
            'item_cantidad' => $quantity,
            'precio_unitario' => $unitPriceDivisas,
            'item_precio_unitario' => $unitPriceDivisas,
            'precio_unitario_bs' => $unitPriceBolivares,
            'item_precio_unitario_bs' => $unitPriceBolivares,
            'precio_total' => $displayTotal,
            'item_precio_total' => $displayTotal,
            'precio_total_divisas' => $totalDivisas,
            'item_precio_total_divisas' => $totalDivisas,
            'precio_total_bs' => $totalBolivares,
            'item_precio_total_bs' => $totalBolivares,
        ];
    }

    private function resolveVariant(): string
    {
        $variant = strtolower(trim((string) request('variant', self::DEFAULT_VARIANT)));

        return array_key_exists($variant, self::TEMPLATE_VARIANTS) ? $variant : self::DEFAULT_VARIANT;
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
            if (is_array($token)) {
                $normalizedGroup = array_values(array_map('strval', $token));

                if ($normalizedGroup !== []) {
                    $pending[implode('|', $normalizedGroup)] = $normalizedGroup;
                }

                continue;
            }

            $pending[(string) $token] = [(string) $token];
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = (string) $sheet->getCellByColumnAndRow($column, $row)->getValue();

                if ($value === '' || $pending === []) {
                    continue;
                }

                foreach ($pending as $pendingKey => $tokenGroup) {
                    foreach ($tokenGroup as $token) {
                        if ($this->containsTokenVariant($value, $token)) {
                            unset($pending[$pendingKey]);
                            break;
                        }
                    }
                }

                if ($pending === []) {
                    break 2;
                }
            }
        }

        return array_map(
            static fn (array $tokenGroup): string => implode(' o ', $tokenGroup),
            array_values($pending)
        );
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

    private function normalizeSheetForPdf(Worksheet $sheet, string $variant = self::DEFAULT_VARIANT): void
    {
        $pageSetup = $sheet->getPageSetup();
        $printAreaEnd = $variant === 'bolivares'
            ? self::PDF_PRINT_AREA_END_BOLIVARES
            : self::PDF_PRINT_AREA_END;

        $pageSetup->setPrintArea(self::PDF_PRINT_AREA_START . ':' . $printAreaEnd);

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
