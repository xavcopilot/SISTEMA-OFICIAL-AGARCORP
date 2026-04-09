<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Support\LibreOfficePdfConverter;
use Illuminate\Http\Response;
use NumberFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenCompraFormatoController extends Controller
{
    private const EXCEL_TEMPLATE_FILE = 'FORMATO ODC.xlsx';
    private const PDF_PRINT_AREA_START = 'A7';
    private const PDF_PRINT_AREA_END = 'H62';
    private const ITEMS_START_ROW = 32;
    private const ITEMS_END_ROW = 43;

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

        $ordenCompra->loadMissing(['items', 'proveedor', 'sumario.solicitudCompra']);

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

            $this->fillHeader($sheet, $ordenCompra);
            $this->fillItems($sheet, $ordenCompra);
            $this->fillTotals($sheet, $ordenCompra);
            $this->fillFooters($sheet, $ordenCompra);
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

    private function fillHeader(Worksheet $sheet, OrdenCompra $ordenCompra): void
    {
        $proveedor = $ordenCompra->proveedor;
        $sumario = $ordenCompra->sumario;

        $sheet->setCellValue('G12', 'N°.' . (string) $ordenCompra->correlativo_odc);
        $sheet->setCellValue('G13', 'Fecha: ' . (string) optional($ordenCompra->created_at)->format('d/m/Y'));

        $sheet->setCellValue('C17', (string) ($proveedor?->nombre ?? ''));
        $sheet->setCellValue('C19', (string) ($ordenCompra->rif_proveedor ?? $proveedor?->rif ?? ''));
        $sheet->setCellValue('H19', (string) ($proveedor?->telefono ?? ''));
        $sheet->setCellValue('C21', (string) ($ordenCompra->direccion_proveedor ?? $proveedor?->direccion ?? ''));
        $sheet->setCellValue('H21', (string) ($sumario?->tiempo_entrega ?? ''));
        $sheet->setCellValue('C23', (string) ($proveedor?->ciudad ?? ''));
        $sheet->setCellValue('C25', (string) ($ordenCompra->email_proveedor ?? $proveedor?->email ?? ''));
        $sheet->setCellValue('C27', (string) ($ordenCompra->contacto_proveedor ?? $proveedor?->contacto ?? ''));
    }

    private function fillItems(Worksheet $sheet, OrdenCompra $ordenCompra): void
    {
        $items = $ordenCompra->items->values();
        $maxRows = self::ITEMS_END_ROW - self::ITEMS_START_ROW + 1;

        for ($offset = 0; $offset < $maxRows; $offset++) {
            $row = self::ITEMS_START_ROW + $offset;
            $item = $items->get($offset);

            if (! $item) {
                $sheet->setCellValue('B' . $row, '');
                $sheet->setCellValue('C' . $row, '');
                $sheet->setCellValue('E' . $row, '');
                $sheet->setCellValue('F' . $row, '');
                $sheet->setCellValue('G' . $row, '');
                $sheet->setCellValue('H' . $row, '');
                continue;
            }

            $sheet->setCellValue('B' . $row, (string) ($item->item ?? 'N/A'));
            $sheet->setCellValue('C' . $row, (string) ($item->descripcion ?? ''));
            $sheet->setCellValue('E' . $row, (string) ($item->unidad_medida ?? 'UND'));
            $sheet->setCellValue('F' . $row, (float) ($item->cantidad ?? 0));
            $sheet->setCellValue('G' . $row, (float) ($item->precio_unitario ?? 0));
            $sheet->setCellValue('H' . $row, (float) ($item->precio_total ?? 0));
        }
    }

    private function fillTotals(Worksheet $sheet, OrdenCompra $ordenCompra): void
    {
        $sheet->setCellValue('H44', (float) ($ordenCompra->monto_exento ?? 0));
        $sheet->setCellValue('H45', (float) ($ordenCompra->sub_total ?? 0));
        $sheet->setCellValue('H46', (float) ($ordenCompra->iva_16 ?? 0));
        $sheet->setCellValue('H47', (float) ($ordenCompra->gastos_adicionales ?? 0));
        $sheet->setCellValue('H48', (float) ($ordenCompra->total_general ?? 0));

        $sheet->setCellValue('B45', 'SON: ' . $this->numberToWordsEs((float) ($ordenCompra->total_general ?? 0)) . ' BOLIVARES');
    }

    private function fillFooters(Worksheet $sheet, OrdenCompra $ordenCompra): void
    {
        $sumario = $ordenCompra->sumario;

        $sheet->setCellValue('B50', 'SITIO DE ENTREGA: ALMACEN AGARCORP');
        $sheet->setCellValue('B51', 'CONDICION DE PAGO: ' . (string) ($ordenCompra->condicion_pago ?? ''));
        $sheet->setCellValue('B53', 'COMENTARIOS: ' . (string) ($sumario?->observaciones ?? ''));
        $sheet->setCellValue('C56', (float) ($ordenCompra->tasa_bcv ?? 0));
        $sheet->setCellValue('C58', (string) ($sumario?->correlativo_sdc ?? ''));

        $sheet->setCellValue('C60', (string) ($sumario?->elaboradoPor?->name ?? ''));
        $sheet->setCellValue('C61', (string) ($sumario?->elaboradoPor?->cargo?->nombre ?? ''));
        $sheet->setCellValue('G60', (string) ($sumario?->revisadoPor?->name ?? ''));
        $sheet->setCellValue('G61', (string) ($sumario?->revisadoPor?->cargo?->nombre ?? ''));
    }

    private function normalizeSheetForPdf(Worksheet $sheet): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPrintArea(self::PDF_PRINT_AREA_START . ':' . self::PDF_PRINT_AREA_END);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $pageSetup->setHorizontalCentered(true);
        $pageSetup->setVerticalCentered(false);
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
