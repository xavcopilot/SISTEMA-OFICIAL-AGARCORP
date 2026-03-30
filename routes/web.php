<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

// export de tickets a Excel/CSV
Route::get('/admin/tickets/export', function () {
    // legacy: keep the old path for compatibility (will redirect).
    return redirect('/tickets/export');
})->middleware(['auth']);

// public (authenticated) export route outside of Filament panel to avoid Filament's internal 404
Route::get('/tickets/export', function () {
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TicketsExport(), 'tickets.xlsx');
})->middleware(['auth']);

Route::get('/inventario/entradas/export/{format}', function (string $format) {
    $format = strtolower($format);

    if (! in_array($format, ['xlsx', 'csv'], true)) {
        abort(404);
    }

    $writerType = $format === 'csv'
        ? \Maatwebsite\Excel\Excel::CSV
        : \Maatwebsite\Excel\Excel::XLSX;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\EntradasExport(),
        'consultar-entradas.' . $format,
        $writerType,
    );
})->middleware(['auth'])->name('inventario.entradas.export');

Route::get('/inventario/salidas/export/{format}', function (string $format) {
    $format = strtolower($format);

    if (! in_array($format, ['xlsx', 'csv'], true)) {
        abort(404);
    }

    $writerType = $format === 'csv'
        ? \Maatwebsite\Excel\Excel::CSV
        : \Maatwebsite\Excel\Excel::XLSX;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\SalidasExport(),
        'consultar-salidas.' . $format,
        $writerType,
    );
})->middleware(['auth'])->name('inventario.salidas.export');

Route::get('/inventario/export/{format}', function (string $format) {
    $format = strtolower($format);

    if (! in_array($format, ['xlsx', 'csv'], true)) {
        abort(404);
    }

    $writerType = $format === 'csv'
        ? \Maatwebsite\Excel\Excel::CSV
        : \Maatwebsite\Excel\Excel::XLSX;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\InventarioExport(),
        'consultar-inventario.' . $format,
        $writerType,
    );
})->middleware(['auth'])->name('inventario.export');

Route::get('/inventario/maestro/export/{format}', function (string $format) {
    $format = strtolower($format);

    if (! in_array($format, ['xlsx', 'csv'], true)) {
        abort(404);
    }

    $writerType = $format === 'csv'
        ? \Maatwebsite\Excel\Excel::CSV
        : \Maatwebsite\Excel\Excel::XLSX;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\InventarioMaestroExport(),
        'inventario.' . $format,
        $writerType,
    );
})->middleware(['auth'])->name('inventario.maestro.export');

Route::get('/solicitudes-compra/{solicitudCompra}/formato', \App\Http\Controllers\SolicitudCompraFormatoController::class)
    ->middleware(['auth'])
    ->name('solicitudes-compra.formato');

Route::get('/solicitudes-compra/{solicitudCompra}/formato/impresion', [\App\Http\Controllers\SolicitudCompraFormatoController::class, 'printPreview'])
    ->middleware(['auth'])
    ->name('solicitudes-compra.formato.print');

Route::get('/inventario/movimientos/{inventoryMovement}/formato-entrada', \App\Http\Controllers\InventoryEntradaFormatoController::class)
    ->middleware(['auth'])
    ->name('inventario.movimientos.formato-entrada');

Route::get('/inventario/movimientos/{inventoryMovement}/formato-salida', \App\Http\Controllers\InventorySalidaFormatoController::class)
    ->middleware(['auth'])
    ->name('inventario.movimientos.formato-salida');
