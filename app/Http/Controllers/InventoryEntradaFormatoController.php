<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\LibreOfficePdfConverter;
use App\Support\UserSignaturePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfDompdfWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryEntradaFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'FORMATO ENTRADA MATERIAL.xlsx';
    private const PDF_PRINT_AREA_START = 'A1';
    private const PDF_MIN_END_COLUMN = 'I';
    private const PDF_MIN_END_ROW = 35;
    private const DEFAULT_SIGNATURE_HEIGHT = 80;
    private const DEFAULT_SIGNATURE_OFFSET_X = 0;
    private const DEFAULT_SIGNATURE_OFFSET_Y = 0;
    private const SIGNATURE_TOKENS = [
        'firma_almacen',
        'firma_entregado',
    ];
    private const SIGNATURE_RENDER_SETTINGS = [
        'firma_almacen' => [
            'height' => 40,
            'offset_x' => 0,
            'offset_y' => 0,
        ],
        'firma_entregado' => [
            'height' => 80,
            'offset_x' => 0,
            'offset_y' => 0,
        ],
    ];
    private const ITEM_TOKENS = [
        'item',
        'item_n',
        'sku',
        'codigo',
        'descripcion',
        'marca',
        'categoria',
        'subcategoria',
        'serial',
        'estado',
        'medida',
        'cantidad',
        'precio',
        'subtotal',
        'ubicacion',
        'dpto_responsable',
    ];

    public function __construct(private LibreOfficePdfConverter $libreOfficePdfConverter)
    {
    }

    public function __invoke(InventoryMovement $inventoryMovement)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole(['Almacen', 'admin', 'Alta Gerencia', 'A.I.T'])) {
            abort(403);
        }

        if (! in_array((string) $inventoryMovement->tipo, ['entrada', 'ingreso'], true)) {
            abort(Response::HTTP_NOT_FOUND, 'Este formato solo aplica para movimientos de entrada o ingreso.');
        }

        $inventoryMovement->loadMissing(['items.product.subcategory.category', 'createdBy', 'almacenistaUser', 'entregadoPorUser']);

        $templatePath = storage_path('app/templates/' . self::EXCEL_TEMPLATE_FILE);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla en storage/app/templates.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'entrada-material-' . $inventoryMovement->id . '-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $this->renderSignatureImages($sheet, $inventoryMovement);
            $this->replaceGlobalTokens($sheet, $this->buildGlobalTokens($inventoryMovement));
            $this->renderItemRows($sheet, $inventoryMovement);
            $this->normalizeSheetForPdf($sheet);

            $xlsxWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $xlsxWriter->save($xlsxPath);

            if ($outputFormat === 'xlsx') {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $fileName = 'FORMATO_ENTRADA_' . $inventoryMovement->id . '.xlsx';

                return response()->download($xlsxPath, $fileName)->deleteFileAfterSend(true);
            }

            $wasConvertedByLibreOffice = $this->libreOfficePdfConverter->convertSpreadsheetToPdf(
                $xlsxPath,
                $pdfPath,
                $tmpDir,
                ['documento' => 'formato_entrada']
            );
            if (! $wasConvertedByLibreOffice) {
                \Illuminate\Support\Facades\Log::warning('Fallo conversion LibreOffice para Entrada, se usara fallback Dompdf.');

                $pdfWriter = new PdfDompdfWriter($spreadsheet);
                $pdfWriter->save($pdfPath);
            }

            if (! file_exists($pdfPath) || filesize($pdfPath) < 100) {
                \Illuminate\Support\Facades\Log::error('PDF de entrada generado pero vacio o demasiado pequeno.', [
                    'was_converted_by_libreoffice' => $wasConvertedByLibreOffice,
                    'pdf_size' => file_exists($pdfPath) ? filesize($pdfPath) : 0,
                ]);

                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el PDF del formato de entrada. Verifique que LibreOffice este instalado en el servidor.');
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

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el formato de entrada.');
        }

        $fileName = 'FORMATO_ENTRADA_' . $inventoryMovement->id . '.pdf';
        $download = request()->boolean('download', true);

        if ($download) {
            return response()->download($pdfPath, $fileName)->deleteFileAfterSend(true);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ])->deleteFileAfterSend(true);
    }

    private function buildGlobalTokens(InventoryMovement $movement): array
    {
        $totalCantidad = (string) $movement->items->sum('cantidad');
        $totalMonto = number_format(
            (float) $movement->items->sum(fn ($item) => ((float) $item->precio_momento) * ((int) $item->cantidad)),
            2,
            '.',
            ''
        );

        return [
            'movimiento_id' => (string) $movement->id,
            'nro_control'   => (string) ($movement->nro_control ?? ''),
            'tipo'          => (string) ($movement->tipo ?? ''),
            'fecha'         => optional($movement->fecha)->format('d/m/Y') ?? '',
            'entregado_por' => (string) ($movement->entregadoPorUser?->name ?? $movement->entregado_por ?? ''),
            'almacenista'   => (string) ($movement->almacenista ?? ''),
            'recibido_por'  => (string) ($movement->almacenista ?? ''),
            'firma_almacen' => '',
            'firma_entregado' => '',
            'creado_por'    => (string) ($movement->createdBy?->name ?? ''),
            'orden_compra'  => (string) ($movement->orden_compra ?? ''),
            'nro_solicitud' => (string) ($movement->nro_solicitud ?? ''),
            'factura_nota'  => (string) ($movement->factura_nota ?? ''),
            'nro_doc_legal' => (string) ($movement->nro_doc_legal ?? ''),
            'proveedor'     => (string) ($movement->proveedor ?? ''),
            'comentarios'   => (string) ($movement->comentarios ?? ''),
            'total_items'   => (string) ($movement->total_items ?? 0),
            'total_cantidad' => $totalCantidad,
            'total_monto'   => $totalMonto,
        ];
    }

    private function renderItemRows(Worksheet $sheet, InventoryMovement $movement): void
    {
        $itemRows = $this->findRowsWithItemTokens($sheet);
        if ($itemRows === []) {
            return;
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $items = $movement->items->values();

        foreach ($itemRows as $offset => $rowIndex) {
            $item = $items->get($offset);
            $tokens = $this->buildItemTokens($item, $offset);

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $rowIndex);
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

    private function renderSignatureImages(Worksheet $sheet, InventoryMovement $movement): void
    {
        $signaturePaths = [
            'firma_almacen' => $this->resolveSignatureImagePath($this->resolveAlmacenistaSigner($movement)),
            'firma_entregado' => $this->resolveSignatureImagePath($movement->entregadoPorUser),
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

                    $cell->setValue($this->replaceTokenVariants($textValue, [$token => '']));

                    $signaturePath = $signaturePaths[$token] ?? null;

                    if ($signaturePath !== null) {
                        $this->insertSignatureImage(
                            $sheet,
                            Coordinate::stringFromColumnIndex($column) . $row,
                            $signaturePath,
                            $token
                        );
                    }
                }
            }
        }
    }

    private function resolveAlmacenistaSigner(InventoryMovement $movement): ?User
    {
        if ($movement->almacenistaUser) {
            return $movement->almacenistaUser;
        }

        $name = trim((string) ($movement->almacenista ?? ''));

        if ($name === '') {
            return null;
        }

        return User::role('Almacen')->where('name', $name)->first();
    }

    private function resolveSignatureImagePath(?User $signer): ?string
    {
        if (! $signer) {
            return null;
        }

        $path = UserSignaturePath::findByUserId((int) $signer->id);

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);

        return file_exists($absolutePath) ? $absolutePath : null;
    }

    private function insertSignatureImage(Worksheet $sheet, string $coordinates, string $imagePath, string $token): void
    {
        $settings = $this->resolveSignatureRenderSettings($token);

        $drawing = new Drawing();
        $drawing->setName($token);
        $drawing->setDescription('Firma ' . $token);
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinates);
        $drawing->setOffsetX((int) ($settings['offset_x'] ?? self::DEFAULT_SIGNATURE_OFFSET_X));
        $drawing->setOffsetY((int) ($settings['offset_y'] ?? self::DEFAULT_SIGNATURE_OFFSET_Y));
        $drawing->setResizeProportional(true);
        $drawing->setHeight((int) ($settings['height'] ?? self::DEFAULT_SIGNATURE_HEIGHT));
        $drawing->setWorksheet($sheet);
    }

    private function resolveSignatureRenderSettings(string $token): array
    {
        return array_replace(
            [
                'height' => self::DEFAULT_SIGNATURE_HEIGHT,
                'offset_x' => self::DEFAULT_SIGNATURE_OFFSET_X,
                'offset_y' => self::DEFAULT_SIGNATURE_OFFSET_Y,
            ],
            self::SIGNATURE_RENDER_SETTINGS[$token] ?? []
        );
    }

    private function buildItemTokens(mixed $item, int $offset): array
    {
        if (! $item) {
            return array_fill_keys(self::ITEM_TOKENS, '');
        }

        $product = $item->product;
        $cantidad = (int) ($item->cantidad ?? 0);
        $precio = (float) ($item->precio_momento ?? 0);

        return [
            'item'           => (string) ($offset + 1),
            'item_n'         => (string) ($offset + 1),
            'sku'            => (string) ($product?->sku ?? ''),
            'codigo'         => (string) ($product?->cod_ingreso ?? ''),
            'descripcion'    => (string) ($product?->descripcion ?? ''),
            'marca'          => (string) ($product?->marca ?? ''),
            'categoria'      => (string) ($product?->subcategory?->category?->name ?? ''),
            'subcategoria'   => (string) ($product?->subcategory?->name ?? ''),
            'serial'         => (string) ($product?->serial ?? ''),
            'estado'         => (string) ($product?->estado ?? ''),
            'medida'         => (string) ($product?->medida ?? ''),
            'cantidad'       => (string) $cantidad,
            'precio'         => number_format($precio, 2, '.', ''),
            'subtotal'       => number_format($cantidad * $precio, 2, '.', ''),
            'ubicacion'      => (string) ($product?->ubicacion ?? ''),
            'dpto_responsable' => (string) ($product?->dpto_responsable ?? ''),
        ];
    }

    private function findRowsWithItemTokens(Worksheet $sheet): array
    {
        $rows = [];
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

                foreach (self::ITEM_TOKENS as $token) {
                    if ($this->containsTokenVariant($textValue, $token)) {
                        $rows[$row] = $row;
                        break 2;
                    }
                }
            }
        }

        return array_values($rows);
    }

    private function replaceGlobalTokens(Worksheet $sheet, array $tokens): void
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

        $highestDataColumn = $sheet->getHighestDataColumn();
        $highestDataRow = $sheet->getHighestDataRow();

        if ($highestDataColumn === 'A' && $highestDataRow <= 1) {
            $highestDataColumn = $sheet->getHighestColumn();
            $highestDataRow = $sheet->getHighestRow();
        }

        $endColumnIndex = max(
            Coordinate::columnIndexFromString($highestDataColumn),
            Coordinate::columnIndexFromString(self::PDF_MIN_END_COLUMN)
        );
        $endColumn = Coordinate::stringFromColumnIndex($endColumnIndex);
        $endRow = max((int) $highestDataRow, self::PDF_MIN_END_ROW);

        $pageSetup->setPrintArea(
            self::PDF_PRINT_AREA_START
            . ':'
            . $endColumn
            . $endRow
        );

        $pageMargins = $sheet->getPageMargins();
        $pageMargins->setTop(0.25);
        $pageMargins->setBottom(0.25);
        $pageMargins->setLeft(0.2);
        $pageMargins->setRight(0.2);

        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
    }

}