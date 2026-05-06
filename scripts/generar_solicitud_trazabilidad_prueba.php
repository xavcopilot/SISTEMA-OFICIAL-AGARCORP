<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Models\User;
use App\Support\ControlCodeGenerator;
use App\Support\OrdenCompraConformidadService;
use App\Support\OrdenCompraRecepcionService;
use App\Support\SolicitudCompraCompletionService;
use App\Support\SolicitudItemTrackingService;
use App\Support\SumarioFinanceApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$opts = getopt('', [
    'email::',
    'prefijo::',
]);

$email = trim((string) ($opts['email'] ?? 'xavierdpdev@gmail.com'));
$prefix = trim((string) ($opts['prefijo'] ?? 'TRAZA-SOLICITANTE-PRUEBA'));

try {
    $context = resolveUsersContext($email);

    $result = DB::transaction(function () use ($context, $prefix): array {
        $providers = ensureProviders();

        $solicitud = createSolicitudBase(
            $context['solicitante'],
            $context['almacen'],
            $context['aprobador'],
            $context['procura'],
            $prefix
        );

        $items = createScenarioItems($solicitud);

        $sumarioCotizacion = createSumarioCotizacion($solicitud, $context['procura']);
        attachSingleItemToSumario($sumarioCotizacion, $items['en_sumario'], 6, $providers, false);

        $sumarioOdc = createSumarioAprobado($solicitud, $context['procura'], $context['gerencia_finanzas'], $prefix . '-A');
        attachSingleItemToSumario($sumarioOdc, $items['en_odc'], 4, $providers, true);
        attachSingleItemToSumario($sumarioOdc, $items['parcial'], 4, $providers, true, 1);
        attachSingleItemToSumario($sumarioOdc, $items['completo'], 3, $providers, true);

        $sumarioOdcPendiente = createSumarioAprobado($solicitud, $context['procura'], $context['gerencia_finanzas'], $prefix . '-B');
        attachSingleItemToSumario($sumarioOdcPendiente, $items['parcial'], 3, $providers, true, 2);

        SolicitudItemTrackingService::syncByItemIds([
            $items['sin_procesar']->id,
            $items['en_sumario']->id,
            $items['en_odc']->id,
            $items['parcial']->id,
            $items['completo']->id,
        ]);

        $orders = app(SumarioFinanceApprovalService::class)->generateOrdersFromSelections($sumarioOdc, $context['procura']);
        $pendingOrders = app(SumarioFinanceApprovalService::class)->generateOrdersFromSelections($sumarioOdcPendiente, $context['procura']);

        if ($orders === [] || $pendingOrders === []) {
            throw new RuntimeException('No se pudieron generar las ODC del escenario de trazabilidad.');
        }

        $order = OrdenCompra::query()->with('items')->findOrFail($orders[0]->id);
        $pendingOrder = OrdenCompra::query()->findOrFail($pendingOrders[0]->id);

        $notePath = ensureReceptionNotePath($order);
        $order = app(OrdenCompraRecepcionService::class)->procesarRecepcion($order, $context['procura'], 'NOTA', $notePath);
        $order->load('items');

        $acceptedRows = buildAcceptedRows($order);
        app(OrdenCompraConformidadService::class)->registrarConformidadPorItems($order, $context['solicitante'], $acceptedRows);

        app(SolicitudCompraCompletionService::class)->syncSolicitud($solicitud->id);

        return summarizeScenario(
            $solicitud->fresh(['items.ordenCompraItems', 'sumarios.ordenesCompra']),
            $sumarioCotizacion,
            [$sumarioOdc, $sumarioOdcPendiente],
            [$order->fresh(), $pendingOrder->fresh()]
        );
    });

    printScenario($result, $email);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al generar solicitud de trazabilidad: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return array{solicitante:User,almacen:User,aprobador:User,procura:User,gerencia_finanzas:User}
 */
function resolveUsersContext(string $email): array
{
    $solicitante = User::query()
        ->where('email', $email)
        ->first();

    $almacen = userByRole('Almacen');
    $aprobador = userByRole('Gerencia de Operaciones')
        ?? userByRole('Alta Gerencia');
    $procura = userByRole('Procura');
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver los usuarios requeridos para el script.');
    }

    return [
        'solicitante' => $solicitante,
        'almacen' => $almacen,
        'aprobador' => $aprobador,
        'procura' => $procura,
        'gerencia_finanzas' => $gerenciaFinanzas,
    ];
}

function userByRole(string $roleName): ?User
{
    return User::query()
        ->whereHas('roles', fn (Builder $query) => $query->where('name', $roleName))
        ->orderBy('id')
        ->first();
}

/**
 * @return array<int, Proveedor>
 */
function ensureProviders(): array
{
    $providers = [
        [
            'nombre' => 'Proveedor Trazabilidad A',
            'rif' => 'J-31000001-1',
            'direccion' => 'Zona Industrial Trazabilidad A',
            'ciudad' => 'Valencia',
            'email' => 'traza-a@example.com',
            'contacto' => 'Ana Trazabilidad',
            'telefono' => '04140000011',
        ],
        [
            'nombre' => 'Proveedor Trazabilidad B',
            'rif' => 'J-31000002-2',
            'direccion' => 'Zona Industrial Trazabilidad B',
            'ciudad' => 'Valencia',
            'email' => 'traza-b@example.com',
            'contacto' => 'Bruno Trazabilidad',
            'telefono' => '04140000012',
        ],
        [
            'nombre' => 'Proveedor Trazabilidad C',
            'rif' => 'J-31000003-3',
            'direccion' => 'Zona Industrial Trazabilidad C',
            'ciudad' => 'Valencia',
            'email' => 'traza-c@example.com',
            'contacto' => 'Carla Trazabilidad',
            'telefono' => '04140000013',
        ],
    ];

    return collect($providers)
        ->map(fn (array $data): Proveedor => Proveedor::query()->updateOrCreate(['rif' => $data['rif']], $data))
        ->values()
        ->all();
}

function createSolicitudBase(User $solicitante, User $almacen, User $aprobador, User $procura, string $prefix): SolicitudCompra
{
    $numeroUsuario = ((int) SolicitudCompra::query()
        ->where('solicitado_por_user_id', $solicitante->id)
        ->max('numero_solicitud_usuario')) + 1;

    return SolicitudCompra::query()->create([
        'codigo_control' => ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control'),
        'numero_solicitud_usuario' => $numeroUsuario,
        'codigo_control_procura' => ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
        'fecha_solicitud' => now()->toDateString(),
        'tipo_solicitud' => 'Consumo',
        'prioridad' => 'Media',
        'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'PRUEBA'),
        'para_ser_usado_en' => 'Solicitud de prueba para trazabilidad del solicitante ' . $prefix,
        'solicitado_por_user_id' => $solicitante->id,
        'por_almacen_user_id' => $almacen->id,
        'aprobado_por_user_id' => $aprobador->id,
        'recibido_por_user_id' => $procura->id,
        'cargo_solicitante' => (string) ($solicitante->cargo?->nombre ?? 'Solicitante'),
        'cargo_almacen' => (string) ($almacen->cargo?->nombre ?? 'Almacen'),
        'cargo_aprobador' => (string) ($aprobador->cargo?->nombre ?? 'Aprobador'),
        'cargo_receptor' => (string) ($procura->cargo?->nombre ?? 'Procura'),
        'firma_solicitante' => '__FIRMA_TEST__',
        'firma_almacen' => '__FIRMA_TEST__',
        'firma_aprobador' => '__FIRMA_TEST__',
        'firma_receptor' => '__FIRMA_TEST__',
        'fecha_solicitante' => now()->subDays(4)->toDateString(),
        'fecha_almacen' => now()->subDays(3)->toDateString(),
        'fecha_aprobador' => now()->subDays(2)->toDateString(),
        'fecha_receptor' => now()->subDay()->toDateString(),
        'hora_receptor' => now()->format('H:i:s'),
        'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
    ]);
}

/**
 * @return array{sin_procesar:SolicitudCompraItem,en_sumario:SolicitudCompraItem,en_odc:SolicitudCompraItem,parcial:SolicitudCompraItem,completo:SolicitudCompraItem}
 */
function createScenarioItems(SolicitudCompra $solicitud): array
{
    return [
        'sin_procesar' => createSolicitudItem($solicitud, 1, 'ITEM 1 SIN PROCESAR', 5),
        'en_sumario' => createSolicitudItem($solicitud, 2, 'ITEM 2 EN SUMARIO', 6),
        'en_odc' => createSolicitudItem($solicitud, 3, 'ITEM 3 EN ODC SIN ENTREGA', 4),
        'parcial' => createSolicitudItem($solicitud, 4, 'ITEM 4 ENTREGA PARCIAL 4 DE 7', 7),
        'completo' => createSolicitudItem($solicitud, 5, 'ITEM 5 ENTREGA COMPLETA 3 DE 3', 3),
    ];
}

function createSolicitudItem(SolicitudCompra $solicitud, int $itemNumber, string $description, float $qty): SolicitudCompraItem
{
    return SolicitudCompraItem::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'item' => $itemNumber,
        'descripcion' => $description,
        'unidad_medida' => 'UND',
        'cantidad_solicitada' => $qty,
        'cantidad_existencia' => 0,
        'cantidad_a_comprar' => $qty,
        'estado_item' => 'SIN_PROCESAR',
    ]);
}

function createSumarioCotizacion(SolicitudCompra $solicitud, User $procura): Sumario
{
    return Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
        'fecha' => now()->subHours(8)->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) $solicitud->departamento_solicitante,
        'condiciones_pago' => 'Contado',
        'tiempo_entrega' => '48 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'observaciones' => 'Sumario de cotizacion para trazabilidad de prueba.',
        'elaborado_por_user_id' => $procura->id,
        'estado' => 'EN_REVISION_FINANZAS',
        'workflow_estado' => 'PENDIENTE_VALIDACION_FINANZAS',
        'enviado_validacion_finanzas_at' => now()->subHours(7),
        'enviado_por_user_id' => $procura->id,
    ]);
}

function createSumarioAprobado(SolicitudCompra $solicitud, User $procura, User $gerenciaFinanzas, string $prefix): Sumario
{
    return Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
        'fecha' => now()->subHours(6)->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) $solicitud->departamento_solicitante,
        'condiciones_pago' => 'Contado',
        'tiempo_entrega' => '24 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'observaciones' => 'Sumario aprobado para escenario mixto de trazabilidad ' . $prefix,
        'elaborado_por_user_id' => $procura->id,
        'revisado_por_user_id' => $gerenciaFinanzas->id,
        'estado' => 'REVISADO_FINANZAS',
        'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
        'enviado_validacion_finanzas_at' => now()->subHours(5),
        'enviado_por_user_id' => $procura->id,
        'validado_finanzas_at' => now()->subHours(4),
        'validado_por_user_id' => $gerenciaFinanzas->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(3),
        'decision_gerencia_por_user_id' => $gerenciaFinanzas->id,
        'decision_gerencia_resultado' => 'APROBADO',
    ]);
}

function attachSingleItemToSumario(Sumario $sumario, SolicitudCompraItem $sourceItem, float $qty, array $providers, bool $selected, int $variant = 0): SumarioItem
{
    $sumarioItem = SumarioItem::query()->create([
        'sumario_id' => $sumario->id,
        'solicitud_compra_item_id' => $sourceItem->id,
        'item' => $sourceItem->item,
        'descripcion' => $sourceItem->descripcion . ($variant > 0 ? ' | PARTE ' . $variant : ''),
        'unidad_medida' => $sourceItem->unidad_medida,
        'cantidad' => $qty,
    ]);

    $base = round(10 + (($sourceItem->item - 1) * 2) + ($variant * 0.25), 2);
    $prices = [$base, $base + 1.25, $base + 2.5];

    foreach ($providers as $index => $provider) {
        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => $index + 1,
            'proveedor_id' => $provider->id,
            'proveedor_nombre' => $provider->nombre,
            'marca' => 'Marca ' . chr(65 + $index),
            'precio_unitario' => $prices[$index],
            'precio_total' => round($prices[$index] * $qty, 2),
            'seleccionada' => $selected && $index === 0,
        ]);
    }

    refreshSumarioTotals($sumario);

    return $sumarioItem;
}

function refreshSumarioTotals(Sumario $sumario): void
{
    $sumario->load('items.opciones');

    $totals = [0.0, 0.0, 0.0];

    foreach ($sumario->items as $item) {
        foreach ($item->opciones as $option) {
            $index = max(0, ((int) $option->opcion_numero) - 1);
            if ($index <= 2) {
                $totals[$index] += (float) ($option->precio_total ?? 0);
            }
        }
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($totals[0], 2),
        'total_compra_prov2' => round($totals[1], 2),
        'total_compra_prov3' => round($totals[2], 2),
    ])->save();
}

function ensureReceptionNotePath(OrdenCompra $order): string
{
    $path = 'ordenes-compra/notas-entrega/odc-' . $order->id . '-nota-trazabilidad.txt';
    Storage::disk('public')->put($path, 'Nota de entrega de prueba para la ODC ' . (string) $order->correlativo_odc);

    return $path;
}

/**
 * @return array<int, array{orden_compra_item_id:int,decision:string,motivo:string}>
 */
function buildAcceptedRows(OrdenCompra $order): array
{
    $rows = [];

    foreach ($order->items as $item) {
        $description = (string) ($item->descripcion ?? '');

        if (str_contains($description, 'ITEM 5')) {
            $rows[] = [
                'orden_compra_item_id' => (int) $item->id,
                'decision' => 'ACEPTADO',
                'motivo' => '',
            ];
        }

        if (str_contains($description, 'ITEM 4') && str_contains($description, 'PARTE 1')) {
            $rows[] = [
                'orden_compra_item_id' => (int) $item->id,
                'decision' => 'ACEPTADO',
                'motivo' => '',
            ];
        }
    }

    if ($rows === []) {
        throw new RuntimeException('No se encontraron filas esperadas para aceptar parcialmente la ODC.');
    }

    return $rows;
}

/**
 * @param array<int, Sumario> $sumariosOdc
 * @param array<int, OrdenCompra> $odcs
 * @return array{solicitud:SolicitudCompra,sumario_cotizacion:Sumario,sumarios_odc:array<int,Sumario>,odcs:array<int,OrdenCompra>,items:array<int,array<string,mixed>>}
 */
function summarizeScenario(SolicitudCompra $solicitud, Sumario $sumarioCotizacion, array $sumariosOdc, array $odcs): array
{
    $items = $solicitud->items
        ->sortBy('item')
        ->values()
        ->map(function (SolicitudCompraItem $item): array {
            $accepted = round((float) $item->ordenCompraItems->where('decision_solicitante', 'ACEPTADO')->sum('cantidad'), 2);
            $ordered = round((float) $item->ordenCompraItems->sum('cantidad'), 2);
            $requested = round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);

            return [
                'item' => (int) ($item->item ?? $item->id),
                'descripcion' => (string) $item->descripcion,
                'estado_item' => (string) ($item->estado_item ?? ''),
                'pedida' => $requested,
                'comprada' => $ordered,
                'aceptada' => $accepted,
                'faltante' => max(0, round($requested - $accepted, 2)),
            ];
        })
        ->all();

    return [
        'solicitud' => $solicitud,
        'sumario_cotizacion' => $sumarioCotizacion,
        'sumarios_odc' => $sumariosOdc,
        'odcs' => $odcs,
        'items' => $items,
    ];
}

/**
 * @param array{solicitud:SolicitudCompra,sumario_cotizacion:Sumario,sumarios_odc:array<int,Sumario>,odcs:array<int,OrdenCompra>,items:array<int,array<string,mixed>>} $result
 */
function printScenario(array $result, string $email): void
{
    $solicitud = $result['solicitud'];
    $sumarioCotizacion = $result['sumario_cotizacion'];
    $sumariosOdc = $result['sumarios_odc'];
    $odcs = $result['odcs'];

    fwrite(STDOUT, PHP_EOL . '=== SOLICITUD DE TRAZABILIDAD LISTA ===' . PHP_EOL);
    fwrite(STDOUT, 'Solicitante: ' . $email . PHP_EOL);
    fwrite(STDOUT, 'Solicitud ID: ' . $solicitud->id . ' | Codigo: ' . (string) $solicitud->codigo_control . PHP_EOL);
    fwrite(STDOUT, 'Sumario cotizacion: ' . (string) $sumarioCotizacion->correlativo_sdc . ' | Workflow: ' . (string) $sumarioCotizacion->workflow_estado . PHP_EOL);

    foreach ($sumariosOdc as $index => $sumarioOdc) {
        fwrite(STDOUT, 'Sumario con ODC ' . ($index + 1) . ': ' . (string) $sumarioOdc->correlativo_sdc . ' | Workflow: ' . (string) $sumarioOdc->workflow_estado . PHP_EOL);
    }

    foreach ($odcs as $index => $odc) {
        fwrite(STDOUT, 'ODC ' . ($index + 1) . ': ' . (string) $odc->correlativo_odc . ' | Workflow: ' . (string) $odc->workflow_post_compra . PHP_EOL);
    }
    fwrite(STDOUT, PHP_EOL . 'Resumen esperado en trazabilidad:' . PHP_EOL);

    foreach ($result['items'] as $row) {
        fwrite(
            STDOUT,
            sprintf(
                '- Item %d | %s | estado_item=%s | pedida=%s | comprada=%s | aceptada=%s | faltante=%s',
                $row['item'],
                $row['descripcion'],
                $row['estado_item'],
                number_format((float) $row['pedida'], 2, ',', '.'),
                number_format((float) $row['comprada'], 2, ',', '.'),
                number_format((float) $row['aceptada'], 2, ',', '.'),
                number_format((float) $row['faltante'], 2, ',', '.')
            ) . PHP_EOL
        );
    }

    fwrite(STDOUT, PHP_EOL . 'Interpretacion funcional:' . PHP_EOL);
    fwrite(STDOUT, '- Item 1: sin procesar, sin sumario ni ODC.' . PHP_EOL);
    fwrite(STDOUT, '- Item 2: en sumario, sin ODC todavia.' . PHP_EOL);
    fwrite(STDOUT, '- Item 3: pedido en ODC, aun no aceptado por el solicitante.' . PHP_EOL);
    fwrite(STDOUT, '- Item 4: entrega parcial, 4 aceptados de 7 pedidos.' . PHP_EOL);
    fwrite(STDOUT, '- Item 5: entrega completa, 3 aceptados de 3 pedidos.' . PHP_EOL . PHP_EOL);
}