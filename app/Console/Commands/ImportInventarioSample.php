<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\Subcategory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportInventarioSample extends Command
{
    protected $signature = 'inventario:import-sample
        {--file=INVENTARIO.xlsx : Nombre del archivo dentro de storage/app/templates}
        {--rows=10 : Cantidad de filas de prueba a importar por hoja}';

    protected $description = 'Importa una muestra desde Excel (hojas INVENTARIO, ENTRADA y SALIDA) a la base relacional del modulo de almacen.';

    private array $categoryMap = [];

    private array $subcategoryMap = [];

    public function handle(): int
    {
        $fileOption = (string) $this->option('file');
        $rowsLimit = max(1, (int) $this->option('rows'));
        $fullPath = storage_path('app/templates/' . $fileOption);

        if (! file_exists($fullPath)) {
            $this->error('No existe el archivo: ' . $fullPath);

            return self::FAILURE;
        }

        $this->info('Leyendo archivo: ' . $fileOption);

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['INVENTARIO', 'ENTRADA', 'SALIDA']);

        $spreadsheet = $reader->load($fullPath);

        $inventarioSheet = $spreadsheet->getSheetByName('INVENTARIO');
        $entradaSheet = $spreadsheet->getSheetByName('ENTRADA');
        $salidaSheet = $spreadsheet->getSheetByName('SALIDA');

        if (! $inventarioSheet || ! $entradaSheet || ! $salidaSheet) {
            $this->error('El archivo debe contener las hojas INVENTARIO, ENTRADA y SALIDA.');

            return self::FAILURE;
        }

        $inventarioRows = $this->extractRowsBySku($inventarioSheet->toArray(null, false, false, false), 2, $rowsLimit);
        $entradaRows = $this->extractRowsBySku($entradaSheet->toArray(null, false, false, false), 8, $rowsLimit);
        $salidaRows = $this->extractRowsBySku($salidaSheet->toArray(null, false, false, false), 6, $rowsLimit);

        if ($inventarioRows === [] && $entradaRows === [] && $salidaRows === []) {
            $this->warn('No se encontraron filas de datos para importar.');

            return self::SUCCESS;
        }

        $this->warmupMaps();

        DB::transaction(function () use ($inventarioRows, $entradaRows, $salidaRows): void {
            $createdProducts = 0;
            $updatedProducts = 0;
            $createdEntradaMovements = 0;
            $createdEntradaItems = 0;
            $createdSalidaMovements = 0;
            $createdSalidaItems = 0;

            foreach ($inventarioRows as $row) {
                [$product, $created] = $this->upsertProductFromInventario($row);

                if ($created) {
                    $createdProducts++;
                } else {
                    $updatedProducts++;
                }
            }

            $movementCache = [];

            foreach ($entradaRows as $row) {
                [$product, $created] = $this->upsertProductFromEntrada($row);

                if ($created) {
                    $createdProducts++;
                } else {
                    $updatedProducts++;
                }

                $nroControl = $this->cell($row, 0);
                $movementKey = $nroControl !== '' ? $nroControl : 'SIN-CONTROL-' . $this->cell($row, 1);

                if (! isset($movementCache[$movementKey])) {
                    $movementId = $this->createOrGetEntradaMovement($row, $nroControl);
                    $movementCache[$movementKey] = $movementId;

                    if ($movementId['created']) {
                        $createdEntradaMovements++;
                    }
                }

                $movementId = $movementCache[$movementKey]['id'];
                $cantidad = max(0, (int) round($this->toFloat($this->cell($row, 16))));

                if ($cantidad <= 0) {
                    continue;
                }

                $precio = $this->toFloat($this->cell($row, 19));
                $item = MovementItem::firstOrCreate([
                    'movement_id' => $movementId,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precio,
                ], [
                    'retorna' => false,
                    'observaciones_item' => null,
                ]);

                if ($item->wasRecentlyCreated) {
                    $createdEntradaItems++;
                }

                $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

                Product::query()->whereKey($product->id)->update([
                    'stock_actual' => DB::raw('stock_actual + ' . $cantidad),
                    'fecha_ultima_entrada' => $fecha->toDateString(),
                ]);
            }

            $salidaMovementCache = [];

            foreach ($salidaRows as $row) {
                [$product, $created] = $this->upsertProductFromSalida($row);

                if ($created) {
                    $createdProducts++;
                } else {
                    $updatedProducts++;
                }

                $nroControl = $this->cell($row, 0);
                $movementKey = $nroControl !== '' ? $nroControl : 'SIN-SALIDA-CONTROL-' . $this->cell($row, 1);

                if (! isset($salidaMovementCache[$movementKey])) {
                    $movementId = $this->createOrGetSalidaMovement($row, $nroControl);
                    $salidaMovementCache[$movementKey] = $movementId;

                    if ($movementId['created']) {
                        $createdSalidaMovements++;
                    }
                }

                $movementId = $salidaMovementCache[$movementKey]['id'];
                $cantidad = max(0, (int) round($this->toFloat($this->cell($row, 14))));

                if ($cantidad <= 0) {
                    continue;
                }

                $retorna = in_array($this->normalize($this->cell($row, 16)), ['SI', 'TRUE', '1'], true);
                $item = MovementItem::firstOrCreate([
                    'movement_id' => $movementId,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => null,
                    'retorna' => $retorna,
                ], [
                    'observaciones_item' => $this->nullIfEmpty($this->cell($row, 17)),
                ]);

                if ($item->wasRecentlyCreated) {
                    $createdSalidaItems++;
                }

                $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

                Product::query()->whereKey($product->id)->update([
                    'stock_actual' => DB::raw('GREATEST(stock_actual - ' . $cantidad . ', 0)'),
                    'fecha_ultima_salida' => $fecha->toDateString(),
                ]);
            }

            foreach ($movementCache as $movementData) {
                $movementId = $movementData['id'];
                $totalItems = MovementItem::query()->where('movement_id', $movementId)->sum('cantidad');

                DB::table('inventory_movements')
                    ->where('id', $movementId)
                    ->update([
                        'total_items' => (int) $totalItems,
                        'updated_at' => now(),
                    ]);
            }

            foreach ($salidaMovementCache as $movementData) {
                $movementId = $movementData['id'];
                $totalItems = MovementItem::query()->where('movement_id', $movementId)->sum('cantidad');

                DB::table('inventory_movements')
                    ->where('id', $movementId)
                    ->update([
                        'total_items' => (int) $totalItems,
                        'updated_at' => now(),
                    ]);
            }

            $this->newLine();
            $this->info('Importacion de prueba completada.');
            $this->line('Productos creados: ' . $createdProducts);
            $this->line('Productos actualizados: ' . $updatedProducts);
            $this->line('Movimientos entrada creados: ' . $createdEntradaMovements);
            $this->line('Items entrada creados: ' . $createdEntradaItems);
            $this->line('Movimientos salida creados: ' . $createdSalidaMovements);
            $this->line('Items salida creados: ' . $createdSalidaItems);
        });

        return self::SUCCESS;
    }

    private function createOrGetEntradaMovement(array $row, string $nroControl): array
    {
        $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

        $existing = DB::table('inventory_movements')
            ->where('tipo', 'entrada')
            ->where('nro_control', $nroControl)
            ->first();

        if ($existing) {
            return ['id' => (int) $existing->id, 'created' => false];
        }

        $id = DB::table('inventory_movements')->insertGetId([
            'tipo' => 'entrada',
            'nro_control' => $nroControl !== '' ? $nroControl : null,
            'fecha' => $fecha->toDateString(),
            'orden_compra' => $this->nullIfEmpty($this->cell($row, 4)),
            'nro_solicitud' => $this->nullIfEmpty($this->cell($row, 3)),
            'factura_nota' => $this->nullIfEmpty($this->cell($row, 5)),
            'nro_doc_legal' => $this->nullIfEmpty($this->cell($row, 6)),
            'proveedor' => $this->nullIfEmpty($this->cell($row, 7)),
            'almacenista' => 'IMPORTACION EXCEL',
            'responsable_destino' => null,
            'dpto_destino' => null,
            'comentarios' => $this->nullIfEmpty($this->cell($row, 20)),
            'total_items' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'created' => true];
    }

    private function upsertProductFromInventario(array $row): array
    {
        $sku = $this->cell($row, 2);
        $codIngreso = $this->cell($row, 1) !== '' ? $this->cell($row, 1) : 'INV-IMPORT';
        $fechaAdq = $this->parseDate($this->cell($row, 0)) ?: now();
        $categoryName = $this->cell($row, 5);
        $subcategoryName = $this->cell($row, 6);
        $subcategoryId = $this->resolveSubcategoryId($categoryName, $subcategoryName);

        $values = [
            'cod_ingreso' => $codIngreso,
            'descripcion' => $this->fallback($this->cell($row, 3), 'SIN DESCRIPCION'),
            'marca' => $this->fallback($this->cell($row, 4), 'N/A'),
            'subcategory_id' => $subcategoryId,
            'serial' => $this->fallback($this->cell($row, 7), 'N/A'),
            'estado' => $this->fallback($this->cell($row, 8), 'NUEVO'),
            'medida' => $this->fallback($this->cell($row, 9), 'UND'),
            'ubicacion' => $this->fallback($this->cell($row, 10), 'SIN UBICACION'),
            'dpto_responsable' => $this->fallback($this->cell($row, 11), 'SIN RESPONSABLE'),
            'stock_minimo' => max(0, (int) round($this->toFloat($this->cell($row, 12)))),
            'precio_unitario' => $this->toFloat($this->cell($row, 13)),
            'fecha_adquisicion' => $fechaAdq->toDateString(),
        ];

        $product = Product::query()->where('sku', $sku)->first();

        if ($product) {
            $product->update($values);

            return [$product->fresh(), false];
        }

        $values['sku'] = $sku;
        $values['stock_actual'] = 0;
        $values['fecha_ultima_entrada'] = null;
        $values['fecha_ultima_salida'] = null;

        return [Product::create($values), true];
    }

    private function upsertProductFromEntrada(array $row): array
    {
        $sku = $this->cell($row, 8);
        $categoryName = $this->cell($row, 11);
        $subcategoryName = $this->cell($row, 12);
        $subcategoryId = $this->resolveSubcategoryId($categoryName, $subcategoryName);
        $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

        $values = [
            'cod_ingreso' => 'ENT-IMPORT',
            'descripcion' => $this->fallback($this->cell($row, 9), 'SIN DESCRIPCION'),
            'marca' => $this->fallback($this->cell($row, 10), 'N/A'),
            'subcategory_id' => $subcategoryId,
            'serial' => $this->fallback($this->cell($row, 13), 'N/A'),
            'estado' => $this->fallback($this->cell($row, 14), 'NUEVO'),
            'medida' => $this->fallback($this->cell($row, 15), 'UND'),
            'ubicacion' => $this->fallback($this->cell($row, 17), 'SIN UBICACION'),
            'dpto_responsable' => $this->fallback($this->cell($row, 18), 'SIN RESPONSABLE'),
            'stock_minimo' => 0,
            'precio_unitario' => $this->toFloat($this->cell($row, 19)),
            'fecha_adquisicion' => $fecha->toDateString(),
        ];

        $product = Product::query()->where('sku', $sku)->first();

        if ($product) {
            $product->update($values);

            return [$product->fresh(), false];
        }

        $values['sku'] = $sku;
        $values['stock_actual'] = 0;
        $values['fecha_ultima_entrada'] = null;
        $values['fecha_ultima_salida'] = null;

        return [Product::create($values), true];
    }

    private function upsertProductFromSalida(array $row): array
    {
        $sku = $this->cell($row, 6);
        $categoryName = $this->cell($row, 9);
        $subcategoryName = $this->cell($row, 10);
        $subcategoryId = $this->resolveSubcategoryId($categoryName, $subcategoryName);
        $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

        $values = [
            'cod_ingreso' => 'SAL-IMPORT',
            'descripcion' => $this->fallback($this->cell($row, 7), 'SIN DESCRIPCION'),
            'marca' => $this->fallback($this->cell($row, 8), 'N/A'),
            'subcategory_id' => $subcategoryId,
            'serial' => $this->fallback($this->cell($row, 11), 'N/A'),
            'estado' => $this->fallback($this->cell($row, 12), 'NUEVO'),
            'medida' => $this->fallback($this->cell($row, 13), 'UND'),
            'ubicacion' => $this->fallback($this->cell($row, 15), 'SIN UBICACION'),
            'dpto_responsable' => $this->fallback($this->cell($row, 4), 'SIN RESPONSABLE'),
            'stock_minimo' => 0,
            'precio_unitario' => 0,
            'fecha_adquisicion' => $fecha->toDateString(),
        ];

        $product = Product::query()->where('sku', $sku)->first();

        if ($product) {
            $product->update($values);

            return [$product->fresh(), false];
        }

        $values['sku'] = $sku;
        $values['stock_actual'] = 0;
        $values['fecha_ultima_entrada'] = null;
        $values['fecha_ultima_salida'] = null;

        return [Product::create($values), true];
    }

    private function createOrGetSalidaMovement(array $row, string $nroControl): array
    {
        $fecha = $this->parseDate($this->cell($row, 1)) ?: now();

        $existing = DB::table('inventory_movements')
            ->where('tipo', 'salida')
            ->where('nro_control', $nroControl)
            ->first();

        if ($existing) {
            return ['id' => (int) $existing->id, 'created' => false];
        }

        $id = DB::table('inventory_movements')->insertGetId([
            'tipo' => 'salida',
            'nro_control' => $nroControl !== '' ? $nroControl : null,
            'fecha' => $fecha->toDateString(),
            'orden_compra' => null,
            'nro_solicitud' => null,
            'factura_nota' => null,
            'nro_doc_legal' => null,
            'proveedor' => null,
            'almacenista' => $this->nullIfEmpty($this->cell($row, 5)) ?? 'IMPORTACION EXCEL',
            'responsable_destino' => $this->nullIfEmpty($this->cell($row, 3)),
            'dpto_destino' => $this->nullIfEmpty($this->cell($row, 4)),
            'comentarios' => $this->nullIfEmpty($this->cell($row, 17)),
            'total_items' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'created' => true];
    }

    private function extractRowsBySku(array $rows, int $skuIndex, int $limit): array
    {
        $data = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = $this->cell($row, $skuIndex);

            if ($sku === '' || $this->looksLikeHeader($sku)) {
                continue;
            }

            $data[] = $row;

            if (count($data) >= $limit) {
                break;
            }
        }

        return $data;
    }

    private function looksLikeHeader(string $value): bool
    {
        $normalized = $this->normalize($value);

        return in_array($normalized, ['SKU', 'CODINGRESO', 'NCONTROL'], true);
    }

    private function warmupMaps(): void
    {
        $this->categoryMap = [];
        $this->subcategoryMap = [];

        foreach (Category::query()->get(['id', 'name']) as $category) {
            $this->categoryMap[$this->normalize((string) $category->name)] = (int) $category->id;
        }

        foreach (Subcategory::query()->get(['id', 'name', 'category_id']) as $subcategory) {
            $key = $subcategory->category_id . '|' . $this->normalize((string) $subcategory->name);
            $this->subcategoryMap[$key] = (int) $subcategory->id;
        }
    }

    private function resolveSubcategoryId(string $categoryName, string $subcategoryName): int
    {
        $categoryNormalized = $this->normalize($categoryName);
        $categoryId = $this->categoryMap[$categoryNormalized] ?? null;

        if (! $categoryId) {
            $category = Category::firstOrCreate(['name' => $this->fallback($categoryName, 'SIN_CATEGORIA')]);
            $categoryId = (int) $category->id;
            $this->categoryMap[$categoryNormalized] = $categoryId;
        }

        $subcategoryNormalized = $this->normalize($subcategoryName);
        $subKey = $categoryId . '|' . $subcategoryNormalized;
        $subcategoryId = $this->subcategoryMap[$subKey] ?? null;

        if ($subcategoryId) {
            return $subcategoryId;
        }

        $subcategory = Subcategory::firstOrCreate([
            'category_id' => $categoryId,
            'name' => $this->fallback($subcategoryName, 'SIN SUBCATEGORIA'),
        ]);

        $subcategoryId = (int) $subcategory->id;
        $this->subcategoryMap[$subKey] = $subcategoryId;

        return $subcategoryId;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Continue.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        $normalized = preg_replace('/\s+/', '', $value) ?? '';

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 2);
    }

    private function cell(array $row, int $index): string
    {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    private function fallback(string $value, string $default): string
    {
        return $value !== '' ? $value : $default;
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['_', '-', '.'], ' ', trim($value));
        $value = Str::upper(Str::ascii($value));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}