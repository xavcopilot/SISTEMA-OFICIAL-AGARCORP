<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$salidas = Illuminate\Support\Facades\DB::table('inventory_movements')
    ->where('tipo', 'salida')
    ->whereIn('nro_control', ['SAL-PRUEBA-0001', 'SAL-PRUEBA-0002'])
    ->orderBy('nro_control')
    ->get(['id', 'nro_control', 'fecha', 'responsable_destino', 'dpto_destino', 'almacenista', 'total_items']);

echo "Movimientos SAL-PRUEBA encontrados: " . $salidas->count() . PHP_EOL;

foreach ($salidas as $mov) {
    $items = Illuminate\Support\Facades\DB::table('movement_items')
        ->where('movement_id', $mov->id)
        ->count();

    echo "- {$mov->nro_control} | fecha={$mov->fecha} | responsable={$mov->responsable_destino} | dpto={$mov->dpto_destino} | items={$items} | total_items={$mov->total_items}" . PHP_EOL;
}

$productos = Illuminate\Support\Facades\DB::table('products')
    ->whereIn('sku', ['UTE-0001', 'UTE-0002', 'TEL-0001'])
    ->get(['sku', 'stock_actual', 'fecha_ultima_entrada', 'fecha_ultima_salida']);

echo PHP_EOL . "Muestra de stock en productos:" . PHP_EOL;
foreach ($productos as $p) {
    echo "- {$p->sku} | stock_actual={$p->stock_actual} | f_entrada={$p->fecha_ultima_entrada} | f_salida={$p->fecha_ultima_salida}" . PHP_EOL;
}
