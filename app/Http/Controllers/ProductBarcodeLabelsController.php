<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ProductBarcodeLabelsController extends Controller
{
    public function __invoke(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        $productsQuery = Product::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('descripcion');

        if ($ids->isNotEmpty()) {
            $productsQuery->whereIn('id', $ids->all());
        } else {
            $productsQuery->where('is_archived', false)->limit(240);
        }

        $products = $productsQuery->get(['id', 'sku', 'descripcion']);

        abort_if($products->isEmpty(), 404, 'No hay productos con SKU para generar etiquetas.');

        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

        $labels = $products->map(function (Product $product) use ($generator): array {
            $sku = trim((string) $product->sku);
            $descripcion = trim((string) $product->descripcion);

            return [
                'sku' => $sku,
                'descripcion' => $descripcion,
                'barcode_base64' => base64_encode(
                    $generator->getBarcode($sku, $generator::TYPE_CODE_128, 1.6, 42)
                ),
            ];
        })->all();

        $pdf = Pdf::loadView('pdf.product-barcode-labels', [
            'labels' => $labels,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('etiquetas-productos-' . now()->format('YmdHis') . '.pdf');
    }
}
