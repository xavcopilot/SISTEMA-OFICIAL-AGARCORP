<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Models\Sumario;
use App\Models\User;
use App\Support\FinanceDashboardStats;
use App\Support\InventoryDashboardStats;
use App\Support\OrdenCompraAdministracionService;
use App\Support\ProcuraDashboardStats;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

$opts = getopt('', [
    'procura::',
    'finanzas-pendientes::',
    'finanzas-pagadas::',
    'finanzas-documentadas::',
    'items::',
    'inventario-batches::',
    'inventario-productos::',
    'inventario-entradas::',
    'inventario-salidas::',
    'inventario-max-items::',
    'cleanup',
    'prefijo::',
]);

$procuraFlows = max(0, (int) ($opts['procura'] ?? 3));
$financePending = max(0, (int) ($opts['finanzas-pendientes'] ?? 2));
$financePaid = max(0, (int) ($opts['finanzas-pagadas'] ?? 2));
$financeDocumented = max(0, (int) ($opts['finanzas-documentadas'] ?? 2));
$itemsPerFlow = max(1, (int) ($opts['items'] ?? 3));
$inventoryBatches = max(0, (int) ($opts['inventario-batches'] ?? 1));
$inventoryProducts = max(0, (int) ($opts['inventario-productos'] ?? 40));
$inventoryEntradas = max(0, (int) ($opts['inventario-entradas'] ?? 20));
$inventorySalidas = max(0, (int) ($opts['inventario-salidas'] ?? 12));
$inventoryMaxItems = max(1, (int) ($opts['inventario-max-items'] ?? 3));
$cleanup = (bool) ($opts['cleanup'] ?? false);
$prefix = trim((string) ($opts['prefijo'] ?? 'KPIS-DASH'));
$runTag = $prefix . '-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

if ($procuraFlows === 0 && $financePending === 0 && $financePaid === 0 && $financeDocumented === 0 && $inventoryBatches === 0) {
    fwrite(STDERR, 'No hay trabajo que ejecutar. Ajusta las opciones del script.' . PHP_EOL);
    exit(1);
}

$created = [
    'solicitudes' => [],
    'sumarios' => [],
    'ordenes' => [],
];

$before = captureDashboardSnapshot();

fwrite(STDOUT, '=== PRUEBA DE METRICAS Y KPIS DE DASHBOARDS ===' . PHP_EOL);
fwrite(STDOUT, 'Run tag: ' . $runTag . PHP_EOL);
fwrite(STDOUT, sprintf(
    'Configuracion -> procura=%d, finanzas pendientes=%d, finanzas pagadas=%d, finanzas documentadas=%d, inventario batches=%d' . PHP_EOL,
    $procuraFlows,
    $financePending,
    $financePaid,
    $financeDocumented,
    $inventoryBatches
));
fwrite(STDOUT, PHP_EOL);

try {
    $adminUser = resolveAdminUser();

    for ($index = 1; $index <= $procuraFlows; $index++) {
        $meta = createFlowFromScript('odc_generada', $itemsPerFlow, $runTag . '-PROC-' . $index);
        applyProcuraTimeline($meta, $index);
        trackCreated($created, $meta);
    }

    for ($index = 1; $index <= $financePending; $index++) {
        $meta = createFlowFromScript('odc_generada', $itemsPerFlow, $runTag . '-FIN-PEND-' . $index);
        applyFinancePendingTimeline($meta, $index);
        trackCreated($created, $meta);
    }

    for ($index = 1; $index <= $financePaid; $index++) {
        $meta = createFlowFromScript('pago_registrado', $itemsPerFlow, $runTag . '-FIN-PAID-' . $index);
        applyFinancePaidTimeline($meta, $index);
        trackCreated($created, $meta);
    }

    for ($index = 1; $index <= $financeDocumented; $index++) {
        $meta = createFlowFromScript('factura_enviada_admin', $itemsPerFlow, $runTag . '-FIN-DOC-' . $index);
        applyFinanceDocumentedTimeline($meta, $adminUser, $index);
        trackCreated($created, $meta);
    }

    if ($inventoryBatches > 0) {
        $inventoryExitCode = $kernel->call('inventario:stress-test', [
            '--batches' => $inventoryBatches,
            '--productos' => $inventoryProducts,
            '--entradas' => $inventoryEntradas,
            '--salidas' => $inventorySalidas,
            '--max-items' => $inventoryMaxItems,
            '--cleanup' => $cleanup,
        ]);

        fwrite(STDOUT, PHP_EOL . '=== SALIDA INVENTARIO:STRESS-TEST ===' . PHP_EOL);
        fwrite(STDOUT, $kernel->output() . PHP_EOL);

        if ($inventoryExitCode !== 0) {
            throw new RuntimeException('inventario:stress-test finalizo con codigo ' . $inventoryExitCode . '.');
        }
    }

    $after = captureDashboardSnapshot();

    fwrite(STDOUT, PHP_EOL . '=== RESUMEN DE DATOS GENERADOS ===' . PHP_EOL);
    fwrite(STDOUT, 'Solicitudes creadas: ' . count(array_unique($created['solicitudes'])) . PHP_EOL);
    fwrite(STDOUT, 'Sumarios creados: ' . count(array_unique($created['sumarios'])) . PHP_EOL);
    fwrite(STDOUT, 'ODC creadas: ' . count(array_unique($created['ordenes'])) . PHP_EOL);

    renderSummaryDiff('Finanzas', $before['finanzas'], $after['finanzas']);
    renderSummaryDiff('Procura', $before['procura'], $after['procura']);
    renderSummaryDiff('Almacen', $before['almacen'], $after['almacen']);

    fwrite(STDOUT, PHP_EOL . '=== DETALLE KPI FINANZAS ===' . PHP_EOL);
    renderRows(FinanceDashboardStats::getPaymentsByProvider(limit: 5));

    fwrite(STDOUT, PHP_EOL . '=== DETALLE KPI PROCURA ===' . PHP_EOL);
    fwrite(STDOUT, 'Promedio por analista:' . PHP_EOL);
    renderRows(ProcuraDashboardStats::getSummaryToOrderByAnalyst(limit: 5));
    fwrite(STDOUT, 'Sumarios por solicitud:' . PHP_EOL);
    renderRows(ProcuraDashboardStats::getSummariesPerRequest(limit: 5));
    fwrite(STDOUT, 'ODC por sumario:' . PHP_EOL);
    renderRows(ProcuraDashboardStats::getOrdersPerSummary(limit: 5));

    fwrite(STDOUT, PHP_EOL . '=== DETALLE KPI ALMACEN ===' . PHP_EOL);
    fwrite(STDOUT, 'Cantidad por categoria:' . PHP_EOL);
    renderRows(InventoryDashboardStats::getQuantityByCategory(limit: 5));
    fwrite(STDOUT, 'Consumo por categoria:' . PHP_EOL);
    renderRows(InventoryDashboardStats::getConsumptionByCategory(limit: 5));
    fwrite(STDOUT, 'Asignado por departamento:' . PHP_EOL);
    renderRows(InventoryDashboardStats::getAssignedByDepartment(limit: 5));
    fwrite(STDOUT, 'Consumo por departamento:' . PHP_EOL);
    renderRows(InventoryDashboardStats::getConsumptionByDepartment(limit: 5));

    if ($cleanup) {
        cleanupProcuraFinanceData($created);
        fwrite(STDOUT, PHP_EOL . 'Cleanup de datos de Procura/Finanzas completado.' . PHP_EOL);
    }

    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, PHP_EOL . 'Error ejecutando prueba de dashboards: ' . $throwable->getMessage() . PHP_EOL);

    if ($cleanup) {
        cleanupProcuraFinanceData($created);
        fwrite(STDERR, 'Se ejecuto cleanup de los datos creados antes del error.' . PHP_EOL);
    }

    exit(1);
}

function captureDashboardSnapshot(): array
{
    return [
        'finanzas' => FinanceDashboardStats::getSummary(),
        'procura' => ProcuraDashboardStats::getSummary(),
        'almacen' => InventoryDashboardStats::getSummary(),
    ];
}

function renderSummaryDiff(string $title, array $before, array $after): void
{
    fwrite(STDOUT, PHP_EOL . '=== ' . strtoupper($title) . ' ===' . PHP_EOL);

    foreach ($after as $key => $value) {
        $previous = $before[$key] ?? 0;
        $delta = round((float) $value - (float) $previous, 2);
        fwrite(STDOUT, sprintf(
            '- %s | antes=%s | despues=%s | delta=%s' . PHP_EOL,
            $key,
            normalizeMetric($previous),
            normalizeMetric($value),
            normalizeMetric($delta)
        ));
    }
}

function normalizeMetric(mixed $value): string
{
    if (is_float($value) || (is_numeric($value) && str_contains((string) $value, '.'))) {
        return number_format((float) $value, 2, '.', '');
    }

    return (string) $value;
}

function renderRows(Collection $rows): void
{
    if ($rows->isEmpty()) {
        fwrite(STDOUT, '- Sin datos.' . PHP_EOL);

        return;
    }

    foreach ($rows as $row) {
        fwrite(STDOUT, '- ' . (string) $row->label . ': ' . normalizeMetric($row->total) . PHP_EOL);
    }
}

function createFlowFromScript(string $stage, int $items, string $prefix): array
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/generar_odc_flujo_prueba.php')
        . ' --stage=' . escapeshellarg($stage)
        . ' --items=' . escapeshellarg((string) $items)
        . ' --prefijo=' . escapeshellarg($prefix);

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    $text = implode(PHP_EOL, $output);

    if ($exitCode !== 0) {
        throw new RuntimeException('El generador de ODC fallo para stage ' . $stage . ': ' . $text);
    }

    if (! preg_match('/ODC ID:\s+(\d+)/', $text, $matches)) {
        throw new RuntimeException('No se pudo obtener el ODC ID del generador para stage ' . $stage . '. Salida: ' . $text);
    }

    $order = OrdenCompra::query()->with(['sumario.solicitudCompra'])->findOrFail((int) $matches[1]);
    $sumario = $order->sumario;
    $solicitud = $sumario?->solicitudCompra;

    if (! $sumario || ! $solicitud) {
        throw new RuntimeException('La ODC ' . $order->id . ' no quedo vinculada correctamente con sumario/solicitud.');
    }

    return [
        'order_id' => (int) $order->id,
        'sumario_id' => (int) $sumario->id,
        'solicitud_id' => (int) $solicitud->id,
    ];
}

function applyProcuraTimeline(array $meta, int $index): void
{
    $requestAt = CarbonImmutable::now()->subDays(25 + ($index * 3));
    $summaryAt = $requestAt->addDays(2 + ($index % 3));
    $orderAt = $summaryAt->addDays(1 + ($index % 2));

    updateFlowTimeline($meta, $requestAt, $summaryAt, $orderAt);
}

function applyFinancePendingTimeline(array $meta, int $index): void
{
    $requestAt = CarbonImmutable::now()->subDays(18 + ($index * 2));
    $summaryAt = $requestAt->addDays(2);
    $orderAt = $summaryAt->addDay();

    updateFlowTimeline($meta, $requestAt, $summaryAt, $orderAt);

    OrdenCompra::query()->whereKey($meta['order_id'])->update([
        'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
        'estado' => 'APROBADA',
        'pago_registrado_at' => null,
        'pago_por_user_id' => null,
        'monto_pagado' => null,
        'referencia_pago' => null,
        'observacion_pago' => null,
        'updated_at' => now(),
    ]);
}

function applyFinancePaidTimeline(array $meta, int $index): void
{
    $requestAt = CarbonImmutable::now()->subDays(14 + ($index * 2));
    $summaryAt = $requestAt->addDays(2);
    $orderAt = $summaryAt->addDay();
    $paymentAt = $orderAt->addDays(1 + ($index % 3));

    updateFlowTimeline($meta, $requestAt, $summaryAt, $orderAt);

    OrdenCompra::query()->whereKey($meta['order_id'])->update([
        'workflow_post_compra' => 'PAGO_REGISTRADO_FINANZAS',
        'pago_registrado_at' => $paymentAt,
        'updated_at' => now(),
    ]);
}

function applyFinanceDocumentedTimeline(array $meta, User $adminUser, int $index): void
{
    $requestAt = CarbonImmutable::now()->subDays(10 + ($index * 2));
    $summaryAt = $requestAt->addDays(1 + ($index % 2));
    $orderAt = $summaryAt->addDay();
    $paymentAt = $orderAt->addDay();
    $sentAt = $paymentAt->addDays(2);
    $loadedAt = $sentAt->addDays(1 + ($index % 2));

    updateFlowTimeline($meta, $requestAt, $summaryAt, $orderAt);

    $order = OrdenCompra::query()->findOrFail($meta['order_id']);

    app(OrdenCompraAdministracionService::class)->registrarDatosFactura($order, $adminUser, [
        'factura_numero' => 'FAC-' . $meta['order_id'],
        'factura_numero_control' => 'CTRL-' . $meta['order_id'],
        'factura_fecha_emision' => $sentAt->toDateString(),
        'factura_base_imponible' => (float) ($order->sub_total ?? $order->total_general ?? 0),
        'factura_monto_iva' => (float) ($order->iva_16 ?? 0),
        'factura_monto_total' => (float) ($order->total_general ?? 0),
        'retencion_iva_monto' => 0,
        'retencion_islr_monto' => 0,
        'comprobantes_retencion_paths' => [],
        'observacion_administracion' => 'Carga de factura de prueba para dashboard',
    ]);

    OrdenCompra::query()->whereKey($meta['order_id'])->update([
        'pago_registrado_at' => $paymentAt,
        'factura_enviada_administracion_at' => $sentAt,
        'factura_cargada_administracion_at' => $loadedAt,
        'factura_procesada_administracion_at' => $loadedAt,
        'workflow_post_compra' => 'BACKUP_FACTURA_COMPLETADO',
        'updated_at' => now(),
    ]);
}

function updateFlowTimeline(array $meta, CarbonImmutable $requestAt, CarbonImmutable $summaryAt, CarbonImmutable $orderAt): void
{
    SolicitudCompra::query()->whereKey($meta['solicitud_id'])->update([
        'created_at' => $requestAt,
        'updated_at' => now(),
    ]);

    Sumario::query()->whereKey($meta['sumario_id'])->update([
        'created_at' => $summaryAt,
        'updated_at' => now(),
    ]);

    OrdenCompra::query()->whereKey($meta['order_id'])->update([
        'created_at' => $orderAt,
        'updated_at' => now(),
    ]);
}

function trackCreated(array &$created, array $meta): void
{
    $created['solicitudes'][] = $meta['solicitud_id'];
    $created['sumarios'][] = $meta['sumario_id'];
    $created['ordenes'][] = $meta['order_id'];
}

function resolveAdminUser(): User
{
    $admin = User::query()
        ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
            'Administracion',
            'Administración',
            'Gerencia de Finanzas',
            'Finanzas',
        ]))
        ->orderBy('id')
        ->first();

    return $admin ?? User::query()->orderBy('id')->firstOrFail();
}

function cleanupProcuraFinanceData(array $created): void
{
    $orderIds = array_values(array_unique(array_map('intval', $created['ordenes'])));
    $sumarioIds = array_values(array_unique(array_map('intval', $created['sumarios'])));
    $solicitudIds = array_values(array_unique(array_map('intval', $created['solicitudes'])));

    if ($orderIds === [] && $sumarioIds === [] && $solicitudIds === []) {
        return;
    }

    DB::transaction(function () use ($orderIds, $sumarioIds, $solicitudIds): void {
        if ($orderIds !== []) {
            DB::table('orden_compra_comprobantes')->whereIn('orden_compra_id', $orderIds)->delete();
            DB::table('orden_compra_items')->whereIn('orden_compra_id', $orderIds)->delete();
            DB::table('ordenes_compra')->whereIn('id', $orderIds)->delete();
        }

        if ($sumarioIds !== []) {
            $sumarioItemIds = DB::table('sumario_items')->whereIn('sumario_id', $sumarioIds)->pluck('id')->all();

            if ($sumarioItemIds !== []) {
                DB::table('sumario_item_opciones')->whereIn('sumario_item_id', $sumarioItemIds)->delete();
            }

            DB::table('sumario_items')->whereIn('sumario_id', $sumarioIds)->delete();
            DB::table('sumarios')->whereIn('id', $sumarioIds)->delete();
        }

        if ($solicitudIds !== []) {
            DB::table('solicitud_compra_items')->whereIn('solicitud_compra_id', $solicitudIds)->delete();
            DB::table('solicitud_compras')->whereIn('id', $solicitudIds)->delete();
        }
    });
}