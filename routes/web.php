<?php

use App\Livewire\DailyWithdrawalRecep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/agarcorp/login');
});

Route::get('/login', function () {
    return redirect('/agarcorp/login');
})->name('login');

Route::get('/recepcion', DailyWithdrawalRecep::class)
    ->middleware(['auth', 'role.guard:Almacen Recepcion'])
    ->name('recepcion');

Route::post('/recepcion/logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/agarcorp/login');
})->middleware(['auth'])->name('recepcion.logout');

Route::get('/recepcion/retiros-diarios', function () {
    return redirect()->route('recepcion');
})->middleware(['auth', 'role.guard:Almacen Recepcion'])
    ->name('recepcion.retiros-diarios');

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

Route::get('/inventario/retiros-diarios/control-despacho', \App\Http\Controllers\DailyWithdrawalsDispatchControlController::class)
    ->middleware(['auth'])
    ->name('inventario.retiros-diarios.control-despacho');

Route::get('/inventario/productos/etiquetas-codigos', \App\Http\Controllers\ProductBarcodeLabelsController::class)
    ->middleware(['auth'])
    ->name('inventario.productos.etiquetas-codigos');

Route::get('/inventario/productos/etiquetas-qr', \App\Http\Controllers\ProductQrLabelsController::class)
    ->middleware(['auth'])
    ->name('inventario.productos.etiquetas-qr');

Route::get('/ordenes-compra/{ordenCompra}/formato', \App\Http\Controllers\OrdenCompraFormatoController::class)
    ->middleware(['auth'])
    ->name('ordenes-compra.formato');

Route::get('/ordenes-compra/{ordenCompra}/formato/impresion', [\App\Http\Controllers\OrdenCompraFormatoController::class, 'printPreview'])
    ->middleware(['auth'])
    ->name('ordenes-compra.formato.print');

Route::get('/sumarios/{sumario}/formato', \App\Http\Controllers\SumarioFormatoController::class)
    ->middleware(['auth'])
    ->name('sumarios.formato');

Route::get('/sumarios/{sumario}/formato/impresion', [\App\Http\Controllers\SumarioFormatoController::class, 'printPreview'])
    ->middleware(['auth'])
    ->name('sumarios.formato.print');

Route::get('/sumarios/{sumario}/propuestas/{documento}/descargar', \App\Http\Controllers\SumarioProveedorDocumentoDownloadController::class)
    ->middleware(['auth'])
    ->name('sumarios.propuestas.download');

Route::get('/ordenes-compra/{ordenCompra}/comprobante/descargar', \App\Http\Controllers\OrdenCompraComprobanteDownloadController::class)
    ->middleware(['auth'])
    ->name('ordenes-compra.comprobante.download');

Route::get('/ordenes-compra/{ordenCompra}/documento-recepcion/descargar', \App\Http\Controllers\OrdenCompraDocumentoRecepcionDownloadController::class)
    ->middleware(['auth'])
    ->name('ordenes-compra.documento-recepcion.download');

Route::match(['get', 'post'], '/ordenes-compra/sumarios/{sumario}/generar', \App\Http\Controllers\OrdenCompraGenerateFromSumarioController::class)
    ->middleware(['auth'])
    ->name('ordenes-compra.generar-desde-sumario');
