<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;

class ProductQrLabelsController extends Controller
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

        abort_if($products->isEmpty(), 404, 'No hay productos con SKU para generar etiquetas QR.');

        $qrOptions = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'scale' => 4,
            'eccLevel' => QRCode::ECC_M,
            'addQuietzone' => true,
        ]);

        $labels = $products->map(function (Product $product) use ($qrOptions): array {
            $sku = trim((string) $product->sku);
            $descripcion = trim((string) $product->descripcion);
            $qrGenerator = new QRCode($qrOptions);
            $qrPngOrDataUri = (string) $qrGenerator->render($sku);

            if (str_starts_with($qrPngOrDataUri, 'data:image')) {
                $commaPosition = strpos($qrPngOrDataUri, ',');
                $qrBase64 = $commaPosition !== false ? substr($qrPngOrDataUri, $commaPosition + 1) : '';
            } else {
                $qrBase64 = base64_encode($qrPngOrDataUri);
            }

            return [
                'sku' => $sku,
                'descripcion' => $descripcion,
                'qr_base64' => $qrBase64,
            ];
        })->all();

        $pdf = Pdf::loadView('pdf.product-qr-labels', [
            'labels' => $labels,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('etiquetas-qr-productos-' . now()->format('YmdHis') . '.pdf');
    }
}
