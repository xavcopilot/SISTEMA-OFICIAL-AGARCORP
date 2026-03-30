<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfDompdfWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventorySalidaFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'FORMATO SALIDA MATERIAL.xlsx';
    private const PDF_PRINT_AREA_START = 'A1';
    private const PDF_MAX_END_COLUMN = 'I';
    private const PDF_MIN_END_ROW = 40;

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
        'retorna',
        'observaciones_item',
    ];

    public function __invoke(InventoryMovement $inventoryMovement)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole(['Almacen', 'admin', 'Alta Gerencia', 'A.I.T'])) {
            abort(403);
        }

        if ((string) $inventoryMovement->tipo !== 'salida') {
            abort(Response::HTTP_NOT_FOUND, 'Este formato solo aplica para movimientos de salida.');
        }

        $inventoryMovement->loadMissing(['items.product', 'createdBy']);

        $templatePath = storage_path('app/templates/' . self::EXCEL_TEMPLATE_FILE);

        if (! file_exists($templatePath)) {
            abort(Response::HTTP_NOT_FOUND, 'No se encontro la plantilla en storage/app/templates.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $outputFormat = strtolower((string) request('format', 'pdf'));
        $fileBaseName = 'salida-material-' . $inventoryMovement->id . '-' . now()->format('YmdHis');
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.xlsx';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $fileBaseName . '.pdf';

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $this->replaceGlobalTokens($sheet, $this->buildGlobalTokens($inventoryMovement));
            $this->renderItemRows($sheet, $inventoryMovement);
            $this->normalizeSheetForPdf($sheet);

            $xlsxWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $xlsxWriter->save($xlsxPath);

            if ($outputFormat === 'xlsx') {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $fileName = 'FORMATO_SALIDA_' . $inventoryMovement->id . '.xlsx';

                return response()->download($xlsxPath, $fileName)->deleteFileAfterSend(true);
            }

            $pdfWriter = new PdfDompdfWriter($spreadsheet);
            $pdfWriter->save($pdfPath);

            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\Throwable $exception) {
            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'No se pudo generar el formato de salida.');
        }

        $fileName = 'FORMATO_SALIDA_' . $inventoryMovement->id . '.pdf';
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
            'movimiento_id'       => (string) $movement->id,
            'nro_control'         => (string) ($movement->nro_control ?? ''),
            'tipo'                => (string) ($movement->tipo ?? ''),
            'fecha'               => optional($movement->fecha)->format('d/m/Y') ?? '',
            'almacenista'         => (string) ($movement->almacenista ?? ''),
            'creado_por'          => (string) ($movement->createdBy?->name ?? ''),
            'responsable_destino' => (string) ($movement->responsable_destino ?? ''),
            'dpto_destino'        => (string) ($movement->dpto_destino ?? ''),
            'comentarios'         => (string) ($movement->comentarios ?? ''),
            'total_items'         => (string) ($movement->total_items ?? 0),
            'total_cantidad'      => $totalCantidad,
            'total_monto'         => $totalMonto,
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

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $cell->setValue($this->replaceTokenVariants($value, $tokens));
            }
        }
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
            'item'              => (string) ($offset + 1),
            'item_n'            => (string) ($offset + 1),
            'sku'               => (string) ($product?->sku ?? ''),
            'codigo'            => (string) ($product?->cod_ingreso ?? ''),
            'descripcion'       => (string) ($product?->descripcion ?? ''),
            'marca'             => (string) ($product?->marca ?? ''),
            'categoria'         => (string) ($product?->subcategory?->category?->name ?? ''),
            'subcategoria'      => (string) ($product?->subcategory?->name ?? ''),
            'serial'            => (string) ($product?->serial ?? ''),
            'estado'            => (string) ($product?->estado ?? ''),
            'medida'            => (string) ($product?->medida ?? ''),
            'cantidad'          => (string) $cantidad,
            'precio'            => number_format($precio, 2, '.', ''),
            'subtotal'          => number_format($cantidad * $precio, 2, '.', ''),
            'ubicacion'         => (string) ($product?->ubicacion ?? ''),
            'dpto_responsable'  => (string) ($product?->dpto_responsable ?? ''),
            'retorna'           => $item->retorna ? 'SI' : 'NO',
            'observaciones_item' => (string) ($item->observaciones_item ?? ''),
        ];
    }

    private function findRowsWithItemTokens(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getValue();

                if (! is_string($value) || $value === '') {
                    continue;
                }

                foreach (self::ITEM_TOKENS as $token) {
                    if ($this->containsTokenVariant($value, $token)) {
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

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $cell->setValue($this->replaceTokenVariants($value, $tokens));
            }
        }
    }

    private function replaceTokenVariants(string $text, array $tokens): string
    {
        foreach ($tokens as $token => $value) {
            $text = str_replace([
                '{{' . $token . '}}',
                '[[' . $token . ']]',
                '{' . $token . '}',
                '%' . $token . '%',
                '__' . $token . '__',
            ], (string) $value, $text);
        }

        return $text;
    }

    private function containsTokenVariant(string $text, string $token): bool
    {
        return str_contains($text, '{{' . $token . '}}')
            || str_contains($text, '[[' . $token . ']]')
            || str_contains($text, '{' . $token . '}')
            || str_contains($text, '%' . $token . '%')
            || str_contains($text, '__' . $token . '__');
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

        $endColumnIndex = min(
            Coordinate::columnIndexFromString($highestDataColumn),
            Coordinate::columnIndexFromString(self::PDF_MAX_END_COLUMN)
        );
        $endColumn = Coordinate::stringFromColumnIndex(max($endColumnIndex, 1));
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
