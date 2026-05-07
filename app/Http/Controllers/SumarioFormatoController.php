<?php

namespace App\Http\Controllers;

use App\Models\InformacionAgarcorp;
use App\Models\Sumario;
use App\Models\SumarioItemOpcion;
use App\Models\User;
use App\Support\LibreOfficePdfConverter;
use App\Support\SumarioProviderGrouping;
use App\Support\UserSignaturePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfDompdfWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SumarioFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'FORMATO SUM COTIZACIONES.xlsx';
    private const ITEMS_START_ROW = 17;
    private const ITEMS_END_ROW = 28;
    private const PDF_PRINT_AREA_START = 'B3';
    private const PDF_PRINT_AREA_END = 'P43';
    private const DEFAULT_SIGNATURE_HEIGHT = 100;
    private const SIGNATURE_TOKENS = [
        'firma_elaborado',
        'firma_aprobado',
        'firma_revisado',
    ];
    private const SIGNATURE_RENDER_OVERRIDES = [
        14 => ['height' => 90, 'offset_x' => 0, 'offset_y' => 0],
        6 => ['height' => 90, 'offset_x' => 30, 'offset_y' => 2],
    ];

    public function __construct(private LibreOfficePdfConverter $libreOfficePdfConverter)
    {
    }

    public function printPreview(Sumario $sumario)
    {
        if (! $this->canAccess()) {
            abort(403);
        }

        return view('sumarios.print-preview', [
            'sumario' => $sumario,
            'pdfUrl' => route('sumarios.formato', ['sumario' => $sumario]),
            'downloadUrl' => route('sumarios.formato', ['sumario' => $sumario, 'download' => 1]),
            'excelUrl' => route('sumarios.formato', ['sumario' => $sumario, 'format' => 'xlsx', 'download' => 1]),
        ]);
    }

    public function __invoke(Sumario $sumario)
    {
        if (! $this->canAccess()) {
            abort(403);
        }

        $sumario->loadMissing([
            'solicitudCompra',
            'elaboradoPor.cargo',
            'revisadoPor.cargo',
            'validadoPor',
            'decisionGerenciaPor',
            'items.opciones.proveedor',
        ]);

        $templatePath = storage_path('app/templates/' . self::EXCEL_TEMPLATE_FILE);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla Excel FORMATO SUM COTIZACIONES.xlsx en storage/app/templates.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'sumario-cotizaciones-' . $sumario->id . '-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';
        $excelFileName = 'SUMARIO_' . ($sumario->correlativo_sdc ?: $sumario->id) . '.xlsx';
        $pdfFileName = 'SUMARIO_' . ($sumario->correlativo_sdc ?: $sumario->id) . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $missingTokens = $this->findMissingTokens(
                $sheet,
                array_merge($this->requiredGlobalTokenNames(), $this->requiredItemTokenNames())
            );

            if ($missingTokens !== []) {
                return response(
                    'Faltan placeholders requeridos en FORMATO SUM COTIZACIONES.xlsx: ' . implode(', ', $missingTokens),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }

            $globalTokens = $this->buildGlobalTokens($sumario);
            $itemRowsWithTokens = $this->findRowsWithAnyTokens($sheet, $this->itemTokenNames());

            $this->renderSignatureImages($sheet, $sumario);
            $this->replaceGlobalTokens($sheet, $globalTokens);
            $this->renderItemsByTokenRows($sheet, $sumario, $itemRowsWithTokens);

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
                ['documento' => 'sumario_cotizaciones']
            );

            if (! $wasConvertedByLibreOffice) {
                $pdfWriter = new PdfDompdfWriter($spreadsheet);
                $pdfWriter->save($pdfPath);
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

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF del sumario desde la plantilla Excel.');
        }

        if (request()->boolean('download')) {
            return response()->download($pdfPath, $pdfFileName)->deleteFileAfterSend(true);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdfFileName . '"',
        ])->deleteFileAfterSend(true);
    }

    private function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        return $user->hasRole('Procura');
    }

    private function buildGlobalTokens(Sumario $sumario): array
    {
        $informacionImpresa = InformacionAgarcorp::current();
        $procedencia = strtoupper((string) ($sumario->procedencia ?? ''));
        $isImportado = str_contains($procedencia, 'IMPORT');

        $tipoOrden = strtoupper((string) ($sumario->tipo_orden ?? ''));
        $isServicio = str_contains($tipoOrden, 'SERVICIO');

        $prioridad = strtoupper((string) ($sumario->prioridad ?? ''));
        $isMejorServicio = str_contains($prioridad, 'SERVICIO') || str_contains($prioridad, 'CALIDAD');

        [$provider1, $provider2, $provider3] = $this->resolveProviderHeaders($sumario);
        $groupedTotals = SumarioProviderGrouping::groupedTotalsFromSumario($sumario);

        $fechaElaborado = optional($sumario->enviado_validacion_finanzas_at ?? $sumario->created_at)->format('d/m/Y');
        $fechaRevisado = optional($sumario->decision_gerencia_finanzas_at ?? $sumario->updated_at)->format('d/m/Y');

        return [
            'sumario_numero' => (string) ($sumario->correlativo_sdc ?? ''),
            'correlativo_sdc' => (string) ($sumario->correlativo_sdc ?? ''),
            'fecha_sumario' => (string) optional($sumario->fecha)->format('d/m/Y'),
            'departamento_solicitante' => (string) ($sumario->departamento_solicitante ?? ''),

            'empresa_razon_social' => (string) ($informacionImpresa->razon_social ?? ''),
            'empresa_rif' => (string) ($informacionImpresa->rif ?? ''),
            'empresa_direccion_fiscal' => (string) ($informacionImpresa->direccion_fiscal ?? ''),
            'empresa_telefono_principal' => (string) ($informacionImpresa->telefono_principal ?? ''),
            'empresa_nombre' => (string) ($informacionImpresa->razon_social ?? ''),
            'empresa_direccion' => (string) ($informacionImpresa->direccion_fiscal ?? ''),
            'empresa_telefono' => (string) ($informacionImpresa->telefono_principal ?? ''),

            'firma_elaborado' => '',
            'firma_aprobado' => '',
            'firma_revisado' => '',

            'procedencia_local' => $this->checkboxTrailing('Local', ! $isImportado),
            'procedencia_importado' => $this->checkboxTrailing('Importado', $isImportado),

            'tipo_orden_compra' => $this->checkboxLeading('COMPRA', ! $isServicio),
            'tipo_orden_servicios' => $this->checkboxLeading('SERVICIOS', $isServicio),

            'proveedor_1_nombre' => $provider1,
            'proveedor_2_nombre' => $provider2,
            'proveedor_3_nombre' => $provider3,

            'condiciones_pago_1' => (string) ($sumario->condiciones_pago ?? ''),
            'condiciones_pago_2' => (string) ($sumario->condiciones_pago ?? ''),
            'condiciones_pago_3' => (string) ($sumario->condiciones_pago ?? ''),
            'tiempo_entrega_1' => (string) ($sumario->tiempo_entrega ?? ''),
            'tiempo_entrega_2' => (string) ($sumario->tiempo_entrega ?? ''),
            'tiempo_entrega_3' => (string) ($sumario->tiempo_entrega ?? ''),

            'total_compra_prov1' => $groupedTotals[1],
            'total_compra_prov2' => $groupedTotals[2],
            'total_compra_prov3' => $groupedTotals[3],

            'observaciones' => (string) ($sumario->observaciones ?? ''),
            'prioridad_mejor_precio' => $this->checkboxTrailing('MEJOR PRECIO', ! $isMejorServicio),
            'prioridad_mejor_servicio' => $this->checkboxTrailing('MEJOR SERVICIO/CALIDAD', $isMejorServicio),

            'elaborado_por_nombre' => (string) ($sumario->elaboradoPor?->name ?? ''),
            'elaborado_por_cargo' => (string) ($sumario->elaboradoPor?->cargo?->nombre ?? ''),
            'elaborado_fecha' => (string) $fechaElaborado,
            'revisado_por_nombre' => (string) ($sumario->revisadoPor?->name ?? ''),
            'revisado_por_cargo' => (string) ($sumario->revisadoPor?->cargo?->nombre ?? ''),
            'revisado_fecha' => (string) $fechaRevisado,
        ];
    }

    private function resolveProviderHeaders(Sumario $sumario): array
    {
        $providers = SumarioProviderGrouping::providerNamesFromSumario($sumario);

        return [$providers[1], $providers[2], $providers[3]];
    }

    private function renderSignatureImages(Worksheet $sheet, Sumario $sumario): void
    {
        $signatureEntries = [
            'firma_elaborado' => ['path' => $this->resolveSignatureImagePath($sumario->elaboradoPor), 'signer' => $sumario->elaboradoPor],
            'firma_aprobado' => ['path' => $this->resolveSignatureImagePath($sumario->decisionGerenciaPor), 'signer' => $sumario->decisionGerenciaPor],
            'firma_revisado' => ['path' => $this->resolveSignatureImagePath($sumario->validadoPor), 'signer' => $sumario->validadoPor],
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

    private function requiredGlobalTokenNames(): array
    {
        return [
            'sumario_numero',
            'fecha_sumario',
            'departamento_solicitante',
            'procedencia_local',
            'procedencia_importado',
            'tipo_orden_compra',
            'tipo_orden_servicios',
            'proveedor_1_nombre',
            'proveedor_2_nombre',
            'proveedor_3_nombre',
            'condiciones_pago_1',
            'condiciones_pago_2',
            'condiciones_pago_3',
            'tiempo_entrega_1',
            'tiempo_entrega_2',
            'tiempo_entrega_3',
            'total_compra_prov1',
            'total_compra_prov2',
            'total_compra_prov3',
            'observaciones',
            'prioridad_mejor_precio',
            'prioridad_mejor_servicio',
            'elaborado_por_nombre',
            'elaborado_por_cargo',
            'elaborado_fecha',
            'revisado_por_nombre',
            'revisado_por_cargo',
            'revisado_fecha',
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
            'marca_prov1',
            'precio_unitario_prov1',
            'precio_total_prov1',
            'marca_prov2',
            'precio_unitario_prov2',
            'precio_total_prov2',
            'marca_prov3',
            'precio_unitario_prov3',
            'precio_total_prov3',
        ];
    }

    private function requiredItemTokenNames(): array
    {
        return [
            'item',
            'descripcion',
            'unidad_medida',
            'cantidad',
            'marca_prov1',
            'precio_unitario_prov1',
            'precio_total_prov1',
            'marca_prov2',
            'precio_unitario_prov2',
            'precio_total_prov2',
            'marca_prov3',
            'precio_unitario_prov3',
            'precio_total_prov3',
        ];
    }

    private function renderItemsByTokenRows(Worksheet $sheet, Sumario $sumario, array $rows): void
    {
        $items = $sumario->items->values();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $totalRows = count($rows);

        for ($index = 0; $index < $totalRows; $index++) {
            $row = $rows[$index];
            $item = $items->get($index);
            $tokens = $this->buildItemTokens($item, $index + 1);
            $this->replaceTokensInRow($sheet, $row, $tokens, $highestColumnIndex);
        }
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
                'marca_prov1' => '',
                'precio_unitario_prov1' => '',
                'precio_total_prov1' => '',
                'marca_prov2' => '',
                'precio_unitario_prov2' => '',
                'precio_total_prov2' => '',
                'marca_prov3' => '',
                'precio_unitario_prov3' => '',
                'precio_total_prov3' => '',
            ];
        }

        $cantidad = (float) ($item->cantidad ?? 0);
        $byOption = [];

        foreach ($item->opciones as $opcion) {
            $idx = (int) ($opcion->opcion_numero ?? 0);
            if ($idx >= 1 && $idx <= 3) {
                $byOption[$idx] = $opcion;
            }
        }

        $tokenValues = [
            'item' => (string) ($item->item ?? $fallbackIndex),
            'item_n' => (string) ($item->item ?? $fallbackIndex),
            'descripcion' => (string) ($item->descripcion ?? ''),
            'item_descripcion' => (string) ($item->descripcion ?? ''),
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'item_unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => $cantidad,
            'item_cantidad' => $cantidad,
        ];

        for ($provider = 1; $provider <= 3; $provider++) {
            $option = $byOption[$provider] ?? null;
            $unit = (float) ($option?->precio_unitario ?? 0);
            $total = (float) ($option?->precio_total ?? ($cantidad * $unit));

            $tokenValues['marca_prov' . $provider] = (string) ($option?->marca ?? '');
            $tokenValues['precio_unitario_prov' . $provider] = $option ? $unit : '';
            $tokenValues['precio_total_prov' . $provider] = $option ? $total : '';
        }

        return $tokenValues;
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

    private function checkboxLeading(string $label, bool $checked): string
    {
        return ($checked ? '■ ' : '□ ') . $label;
    }

    private function checkboxTrailing(string $label, bool $checked): string
    {
        return $label . ' ' . ($checked ? '■' : '□');
    }

    private function normalizeSheetForPdf(Worksheet $sheet): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPrintArea(self::PDF_PRINT_AREA_START . ':' . self::PDF_PRINT_AREA_END);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $pageSetup->setHorizontalCentered(true);
        $pageSetup->setVerticalCentered(false);
    }
}
