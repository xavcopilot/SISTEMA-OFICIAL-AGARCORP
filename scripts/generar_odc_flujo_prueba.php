<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrdenCompra;
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
use App\Support\SolicitudCompraFlow;
use App\Support\SumarioFinanceApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$opts = getopt('', [
    'stage::',
    'items::',
    'prefijo::',
    'odc_id::',
]);

$stage = strtolower(trim((string) ($opts['stage'] ?? 'pago_registrado')));
$itemsCount = max(1, (int) ($opts['items'] ?? 3));
$prefix = trim((string) ($opts['prefijo'] ?? 'FLUJO-ODC-PRUEBA'));
$existingOdcId = max(0, (int) ($opts['odc_id'] ?? 0));

$validStages = [
    'odc_generada',
    'pago_registrado',
    'pago_confirmado',
    'recepcion_nota',
    'recepcion_factura',
    'factura_enviada_admin',
    'factura_procesada',
    'cerrada_conforme',
    'rechazo_solicitante',
];

if (! in_array($stage, $validStages, true)) {
    fwrite(STDERR, "Stage invalido: {$stage}\n");
    fwrite(STDERR, 'Stages validos: ' . implode(', ', $validStages) . "\n");
    exit(1);
}

try {
    $context = resolveUsersContext();

    if ($existingOdcId > 0) {
        $order = OrdenCompra::query()->find($existingOdcId);

        if (! $order) {
            throw new RuntimeException("No existe la ODC con id {$existingOdcId}.");
        }

        $order = moveOrderToStage($order, $stage, $context);
        printResult($order, $stage, false);
        exit(0);
    }

    $order = DB::transaction(function () use ($context, $itemsCount, $prefix, $stage): OrdenCompra {
        $providers = ensureProviders();

        $solicitud = createSolicitudCompraBase($context['solicitante'], $context['almacen'], $context['aprobador'], $context['procura'], $prefix);
        $solicitudItems = createSolicitudItems($solicitud, $itemsCount);

        $sumario = createApprovedSumario($solicitud, $context['procura'], $context['gerencia_finanzas'], $providers, $prefix);
        createComparativeRows($sumario, $solicitudItems, $providers);

        $orders = app(SumarioFinanceApprovalService::class)->generateOrdersFromSelections($sumario, $context['procura']);

        if ($orders === []) {
            throw new RuntimeException('No se pudo generar la ODC de prueba.');
        }

        $order = OrdenCompra::query()->findOrFail($orders[0]->id);

        return moveOrderToStage($order, $stage, $context);
    });

    printResult($order, $stage, true);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al preparar flujo de prueba: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * @return array{solicitante: User, almacen: User, aprobador: User, procura: User, finanzas: User, gerencia_finanzas: User}
 */
function resolveUsersContext(): array
{
    $solicitante = User::query()
        ->where('email', 'xavierdpdev@gmail.com')
        ->orWhere('email', 'prueba@gmail.com')
        ->orderBy('id')
        ->first();

    $almacen = userByRole('Almacen');
    $aprobador = userByRole('Gerencia de Operaciones')
        ?? userByRole('Alta Gerencia');
    $procura = userByRole('Procura');
    $finanzas = userByRole('Finanzas');
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $finanzas || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver todos los usuarios requeridos por rol. Ejecuta seeders y revisa roles.');
    }

    return [
        'solicitante' => $solicitante,
        'almacen' => $almacen,
        'aprobador' => $aprobador,
        'procura' => $procura,
        'finanzas' => $finanzas,
        'gerencia_finanzas' => $gerenciaFinanzas,
    ];
}

function userByRole(string $roleName): ?User
{
    return User::query()
        ->whereHas('roles', fn (Builder $q) => $q->where('name', $roleName))
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
            'nombre' => 'Proveedor Demo A',
            'rif' => 'J-30000001-1',
            'direccion' => 'Zona Industrial Demo A',
            'ciudad' => 'Valencia',
            'email' => 'proveedora@example.com',
            'contacto' => 'Ana Demo',
            'telefono' => '04140000001',
        ],
        [
            'nombre' => 'Proveedor Demo B',
            'rif' => 'J-30000002-2',
            'direccion' => 'Zona Industrial Demo B',
            'ciudad' => 'Valencia',
            'email' => 'proveedorb@example.com',
            'contacto' => 'Bruno Demo',
            'telefono' => '04140000002',
        ],
        [
            'nombre' => 'Proveedor Demo C',
            'rif' => 'J-30000003-3',
            'direccion' => 'Zona Industrial Demo C',
            'ciudad' => 'Valencia',
            'email' => 'proveedorc@example.com',
            'contacto' => 'Carla Demo',
            'telefono' => '04140000003',
        ],
    ];

    return collect($providers)
        ->map(function (array $data): Proveedor {
            return Proveedor::query()->updateOrCreate(
                ['rif' => $data['rif']],
                $data
            );
        })
        ->values()
        ->all();
}

function createSolicitudCompraBase(User $solicitante, User $almacen, User $aprobador, User $procura, string $prefix): SolicitudCompra
{
    $numeroUsuario = ((int) SolicitudCompra::query()
        ->where('solicitado_por_user_id', $solicitante->id)
        ->max('numero_solicitud_usuario')) + 1;

    $codeSeed = now()->format('His') . '-' . random_int(100, 999);

    return SolicitudCompra::query()->create([
        'codigo_control' => ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control'),
        'numero_solicitud_usuario' => $numeroUsuario,
        'codigo_control_procura' => ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
        'fecha_solicitud' => now()->toDateString(),
        'tipo_solicitud' => 'Consumo',
        'prioridad' => 'Media',
        'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'A.I.T'),
        'para_ser_usado_en' => 'Solicitud de prueba integral para flujo ODC.',
        'solicitado_por_user_id' => $solicitante->id,
        'por_almacen_user_id' => $almacen->id,
        'aprobado_por_user_id' => $aprobador->id,
        'recibido_por_user_id' => $procura->id,
        'cargo_solicitante' => (string) ($solicitante->cargo?->nombre ?? 'Solicitante'),
        'cargo_almacen' => (string) ($almacen->cargo?->nombre ?? 'Almacenista'),
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
        'estado' => 'SUMARIO_EN_REVISION',
    ]);
}

/**
 * @return array<int, SolicitudCompraItem>
 */
function createSolicitudItems(SolicitudCompra $solicitud, int $itemsCount): array
{
    $rows = [];

    for ($i = 1; $i <= $itemsCount; $i++) {
        $cantidad = random_int(2, 8);

        $rows[] = SolicitudCompraItem::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'item' => $i,
            'descripcion' => 'ITEM DEMO FLUJO #' . $i,
            'unidad_medida' => 'UND',
            'cantidad_solicitada' => $cantidad,
            'cantidad_existencia' => 0,
            'cantidad_a_comprar' => $cantidad,
            'estado_item' => 'EN_SUMARIO',
        ]);
    }

    return $rows;
}

function createApprovedSumario(SolicitudCompra $solicitud, User $procura, User $gerenciaFinanzas, array $providers, string $prefix): Sumario
{
    $corr = ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc');

    return Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => $corr,
        'fecha' => now()->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) $solicitud->departamento_solicitante,
        'total_compra_prov1' => 0,
        'total_compra_prov2' => 0,
        'total_compra_prov3' => 0,
        'condiciones_pago' => 'Contado',
        'tiempo_entrega' => '24 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'proveedor_ganador_id' => $providers[0]->id,
        'observaciones' => 'Sumario de prueba generado por script ' . $prefix,
        'elaborado_por_user_id' => $procura->id,
        'revisado_por_user_id' => $gerenciaFinanzas->id,
        'estado' => 'REVISADO_FINANZAS',
        'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
        'enviado_validacion_finanzas_at' => now()->subHours(8),
        'enviado_por_user_id' => $procura->id,
        'validado_finanzas_at' => now()->subHours(6),
        'validado_por_user_id' => $gerenciaFinanzas->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(4),
        'decision_gerencia_por_user_id' => $gerenciaFinanzas->id,
        'decision_gerencia_resultado' => 'APROBADO',
    ]);
}

function createComparativeRows(Sumario $sumario, array $solicitudItems, array $providers): void
{
    $total1 = 0.0;
    $total2 = 0.0;
    $total3 = 0.0;

    foreach ($solicitudItems as $index => $sourceItem) {
        $cantidad = (float) ($sourceItem->cantidad_a_comprar ?? $sourceItem->cantidad_solicitada ?? 1);

        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $sourceItem->id,
            'item' => $sourceItem->item,
            'descripcion' => $sourceItem->descripcion,
            'unidad_medida' => $sourceItem->unidad_medida,
            'cantidad' => $cantidad,
        ]);

        $priceA = round(10 + ($index * 2), 2);
        $priceB = round($priceA + 1.50, 2);
        $priceC = round($priceA + 2.70, 2);

        $totalA = round($priceA * $cantidad, 2);
        $totalB = round($priceB * $cantidad, 2);
        $totalC = round($priceC * $cantidad, 2);

        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => 1,
            'proveedor_id' => $providers[0]->id,
            'proveedor_nombre' => $providers[0]->nombre,
            'marca' => 'Marca A',
            'precio_unitario' => $priceA,
            'precio_total' => $totalA,
            'seleccionada' => true,
        ]);

        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => 2,
            'proveedor_id' => $providers[1]->id,
            'proveedor_nombre' => $providers[1]->nombre,
            'marca' => 'Marca B',
            'precio_unitario' => $priceB,
            'precio_total' => $totalB,
            'seleccionada' => false,
        ]);

        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => 3,
            'proveedor_id' => $providers[2]->id,
            'proveedor_nombre' => $providers[2]->nombre,
            'marca' => 'Marca C',
            'precio_unitario' => $priceC,
            'precio_total' => $totalC,
            'seleccionada' => false,
        ]);

        $total1 += $totalA;
        $total2 += $totalB;
        $total3 += $totalC;
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($total1, 2),
        'total_compra_prov2' => round($total2, 2),
        'total_compra_prov3' => round($total3, 2),
    ])->save();
}

/**
 * @param array{solicitante: User, almacen: User, aprobador: User, procura: User, finanzas: User, gerencia_finanzas: User} $context
 */
function moveOrderToStage(OrdenCompra $order, string $stage, array $context): OrdenCompra
{
    $order = OrdenCompra::query()->findOrFail($order->id);

    if (in_array($stage, ['pago_registrado', 'pago_confirmado', 'recepcion_nota', 'recepcion_factura', 'factura_enviada_admin', 'factura_procesada', 'cerrada_conforme', 'rechazo_solicitante'], true)) {
        $order->forceFill([
            'monto_pagado' => round((float) ($order->total_general ?: 0), 2),
            'referencia_pago' => 'TRX-' . now()->format('YmdHis'),
            'comprobante_pago_path' => ensurePaymentVoucherPath($order),
            'observacion_pago' => 'Pago de prueba registrado por script',
            'pago_registrado_at' => now()->subHours(3),
            'pago_por_user_id' => $context['finanzas']->id,
            'estado' => 'PAGADA',
            'workflow_post_compra' => 'PAGO_REGISTRADO_FINANZAS',
        ])->save();
    }

    if (in_array($stage, ['pago_confirmado', 'recepcion_nota', 'recepcion_factura', 'factura_enviada_admin', 'factura_procesada', 'cerrada_conforme', 'rechazo_solicitante'], true)) {
        $order->forceFill([
            'confirmado_procura_at' => now()->subHours(2),
            'confirmado_por_user_id' => $context['procura']->id,
            'estado' => 'EN_ESPERA_DE_PRODUCTO',
            'workflow_post_compra' => 'ESPERANDO_PRODUCTO',
        ])->save();
    }

    if (in_array($stage, ['recepcion_nota', 'recepcion_factura', 'factura_enviada_admin', 'factura_procesada', 'cerrada_conforme', 'rechazo_solicitante'], true)) {
        $tipo = in_array($stage, ['recepcion_factura', 'factura_enviada_admin', 'factura_procesada'], true) ? 'FACTURA' : 'NOTA';
        $facturaPath = $tipo === 'FACTURA' ? ensureInvoicePath($order) : null;

        $order = app(OrdenCompraRecepcionService::class)
            ->procesarRecepcion($order, $context['procura'], $tipo, $facturaPath);
    }

    if (in_array($stage, ['factura_enviada_admin', 'factura_procesada'], true)) {
        $order->forceFill([
            'factura_enviada_administracion_at' => now()->subHour(),
            'factura_enviada_por_user_id' => $context['finanzas']->id,
            'workflow_post_compra' => 'FACTURA_ENVIADA_ADMINISTRACION',
        ])->save();
    }

    if ($stage === 'factura_procesada') {
        $order->forceFill([
            'factura_procesada_administracion_at' => now(),
            'workflow_post_compra' => 'BACKUP_FACTURA_COMPLETADO',
        ])->save();
    }

    if ($stage === 'cerrada_conforme') {
        $order = app(OrdenCompraConformidadService::class)
            ->aceptar($order, $context['solicitante']);
    }

    if ($stage === 'rechazo_solicitante') {
        $order->forceFill([
            'devolucion_solicitada_at' => now(),
            'devolucion_solicitada_por_user_id' => $context['solicitante']->id,
            'devolucion_motivo' => 'Prueba de rechazo para flujo de devolucion con proveedor',
            'workflow_post_compra' => 'RECHAZADA_SOLICITANTE',
        ])->save();
    }

    return OrdenCompra::query()->with(['sumario', 'sumario.solicitudCompra'])->findOrFail($order->id);
}

function ensurePaymentVoucherPath(OrdenCompra $order): string
{
    $path = 'ordenes-compra/comprobantes-pago/odc-' . $order->id . '-comprobante.txt';
    Storage::disk('public')->put($path, 'Comprobante de pago de prueba para ODC ' . $order->correlativo_odc);

    return $path;
}

function ensureInvoicePath(OrdenCompra $order): string
{
    $path = 'ordenes-compra/facturas/odc-' . $order->id . '-factura.txt';
    Storage::disk('public')->put($path, 'Factura de prueba para ODC ' . $order->correlativo_odc);

    return $path;
}

function printResult(OrdenCompra $order, string $stage, bool $createdNew): void
{
    $sumario = $order->sumario;
    $solicitud = $sumario?->solicitudCompra;

    fwrite(STDOUT, PHP_EOL . '=== FLUJO ODC DE PRUEBA LISTO ===' . PHP_EOL);
    fwrite(STDOUT, 'Modo: ' . ($createdNew ? 'creado desde cero' : 'avanzado desde ODC existente') . PHP_EOL);
    fwrite(STDOUT, 'Stage solicitado: ' . $stage . PHP_EOL);
    fwrite(STDOUT, 'ODC ID: ' . $order->id . PHP_EOL);
    fwrite(STDOUT, 'ODC correlativo: ' . (string) $order->correlativo_odc . PHP_EOL);
    fwrite(STDOUT, 'Flujo post-compra: ' . (string) $order->workflow_post_compra . PHP_EOL);
    fwrite(STDOUT, 'Estado ODC: ' . (string) $order->estado . PHP_EOL);
    fwrite(STDOUT, 'Tipo recepcion: ' . (string) ($order->tipo_documento_recepcion ?? 'N/A') . PHP_EOL);
    fwrite(STDOUT, 'Sumario ID: ' . (string) ($sumario?->id ?? 'N/A') . ' | Workflow: ' . (string) ($sumario?->workflow_estado ?? 'N/A') . PHP_EOL);
    fwrite(STDOUT, 'Solicitud ID: ' . (string) ($solicitud?->id ?? 'N/A') . ' | Codigo: ' . (string) ($solicitud?->codigo_control ?? 'N/A') . PHP_EOL);

    fwrite(STDOUT, PHP_EOL . 'Siguiente paso sugerido en UI:' . PHP_EOL);

    $suggestion = match ($stage) {
        'odc_generada' => 'Inicia con Finanzas -> Registrar Pago.',
        'pago_registrado' => 'Entrar con Procura y usar Confirmar pago recibido.',
        'pago_confirmado' => 'Entrar con Procura y usar Procesar Recepcion (NOTA o FACTURA).',
        'recepcion_nota' => 'Entrar con Solicitante y decidir Aceptar Conformidad o Rechazar producto.',
        'recepcion_factura' => 'Entrar con Finanzas y usar Enviar factura a Administracion.',
        'factura_enviada_admin' => 'Entrar con Administracion y abrir Cargar factura manual (Proximamente) o Marcar Factura Procesada.',
        'factura_procesada' => 'Entrar con Solicitante para cierre conforme o rechazo.',
        'cerrada_conforme' => 'Flujo cerrado conforme. Revisa inventario y movimientos generados.',
        'rechazo_solicitante' => 'Revisar notificaciones de Procura/Finanzas para gestion de devolucion.',
        default => 'Revisar la ODC creada en la tabla de Ordenes de Compra.',
    };

    fwrite(STDOUT, '- ' . $suggestion . PHP_EOL . PHP_EOL);
}
