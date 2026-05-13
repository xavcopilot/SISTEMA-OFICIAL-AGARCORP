<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cargo;
use App\Models\DailyWithdrawal;
use App\Models\DailyWithdrawalRequest;
use App\Models\Departamento;
use App\Models\Product;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ControlCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$opts = getopt('', [
    'prefijo::',
    'solicitudes::',
    'items::',
    'tickets::',
    'retiros-pendientes::',
    'retiros-aprobados::',
    'retiros-rechazados::',
    'inventario-batches::',
    'inventario-productos::',
    'inventario-entradas::',
    'inventario-salidas::',
    'inventario-max-items::',
]);

$prefix = trim((string) ($opts['prefijo'] ?? 'DOC-DEMO'));
$runTag = $prefix . '-' . now()->format('YmdHis');
$solicitudes = max(1, (int) ($opts['solicitudes'] ?? 4));
$items = max(2, (int) ($opts['items'] ?? 4));
$tickets = max(4, (int) ($opts['tickets'] ?? 12));
$retirosPendientes = max(1, (int) ($opts['retiros-pendientes'] ?? 5));
$retirosAprobados = max(1, (int) ($opts['retiros-aprobados'] ?? 4));
$retirosRechazados = max(1, (int) ($opts['retiros-rechazados'] ?? 3));
$inventarioBatches = max(1, (int) ($opts['inventario-batches'] ?? 1));
$inventarioProductos = max(10, (int) ($opts['inventario-productos'] ?? 60));
$inventarioEntradas = max(5, (int) ($opts['inventario-entradas'] ?? 25));
$inventarioSalidas = max(5, (int) ($opts['inventario-salidas'] ?? 18));
$inventarioMaxItems = max(1, (int) ($opts['inventario-max-items'] ?? 4));

fwrite(STDOUT, '=== DEMO INTEGRAL PARA DOCUMENTACION ===' . PHP_EOL);
fwrite(STDOUT, 'Prefijo: ' . $runTag . PHP_EOL);
fwrite(STDOUT, 'Este script crea datos realistas para bandejas, historiales, formularios y dashboards.' . PHP_EOL . PHP_EOL);

try {
    $context = resolveDemoContext();

    runStep('Solicitudes pendientes para aprobaciones', function () use ($runTag, $solicitudes, $items): void {
        runExternalScript('generar_solicitudes_compra_prueba.php', [
            'cantidad' => $solicitudes,
            'items' => $items,
            'prefijo' => $runTag . '-APROB',
        ]);
    });

    runStep('Trazabilidad del solicitante y conformidades', function () use ($runTag): void {
        runExternalScript('generar_solicitud_trazabilidad_prueba.php', [
            'prefijo' => $runTag . '-TRAZA',
        ]);
    });

    runStep('Borrador de solicitud de compra', function () use ($context, $runTag): void {
        createSolicitudDraft($context, $runTag);
    });

    runStep('Sumario listo para creación de ODC', function () use ($runTag): void {
        runExternalScript('generar_sumario_creacion_odc_prueba.php', [
            'prefijo' => $runTag . '-SUM-ODC',
        ]);
    });

    runStep('Sumario en corrección de gerencia', function (): void {
        runExternalScript('generar_sumario_parcial_gerencia_prueba.php');
    });

    runStep('Historial de sumarios', function () use ($runTag): void {
        runExternalScript('generar_sumario_historial_prueba.php', [
            'prefijo' => $runTag . '-SUM-HIST',
        ]);
    });

    runStep('Borrador de sumario', function () use ($context, $runTag): void {
        createSumarioDraft($context, $runTag);
    });

    runStep('ODC y flujo post-compra', function () use ($runTag): void {
        $stages = [
            'odc_generada' => 'ODC-PEND-APROB',
            'pago_registrado' => 'ODC-PAGO-PEND',
            'pago_confirmado' => 'ODC-PAGO-CONF',
            'recepcion_factura' => 'ODC-FACT-REC',
            'factura_enviada_admin' => 'ODC-FACT-ENV',
            'factura_procesada' => 'ODC-FACT-CARG',
            'cerrada_conforme' => 'ODC-HIST-CIERRE',
            'rechazo_solicitante' => 'ODC-CORRECCION',
        ];

        foreach ($stages as $stage => $label) {
            runExternalScript('generar_odc_flujo_prueba.php', [
                'stage' => $stage,
                'items' => 3,
                'prefijo' => $runTag . '-' . $label,
            ]);
        }
    });

    runStep('Inventario, movimientos y maestro de productos', function () use ($kernel, $inventarioBatches, $inventarioProductos, $inventarioEntradas, $inventarioSalidas, $inventarioMaxItems): void {
        $exitCode = $kernel->call('inventario:stress-test', [
            '--batches' => $inventarioBatches,
            '--productos' => $inventarioProductos,
            '--entradas' => $inventarioEntradas,
            '--salidas' => $inventarioSalidas,
            '--max-items' => $inventarioMaxItems,
        ]);

        fwrite(STDOUT, $kernel->output() . PHP_EOL);

        if ($exitCode !== 0) {
            throw new RuntimeException('inventario:stress-test fallo con codigo ' . $exitCode . '.');
        }
    });

    runStep('Tickets de soporte realistas', function () use ($context, $runTag, $tickets): void {
        createSupportTickets($context, $runTag, $tickets);
    });

    runStep('Bandeja de retiros diarios', function () use ($context, $runTag, $retirosPendientes, $retirosAprobados, $retirosRechazados): void {
        createDailyWithdrawalsDemo($context, $runTag, $retirosPendientes, $retirosAprobados, $retirosRechazados);
    });

    fwrite(STDOUT, PHP_EOL . '=== DEMO DOCUMENTAL LISTA ===' . PHP_EOL);
    fwrite(STDOUT, 'El panel ya tiene datos amplios para capturas de tablas, historiales, bandejas y dashboards.' . PHP_EOL);
    fwrite(STDOUT, 'Prefijo util para ubicar este lote: ' . $runTag . PHP_EOL);
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, PHP_EOL . 'Error generando demo documental: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function runStep(string $label, callable $callback): void
{
    fwrite(STDOUT, PHP_EOL . '--- ' . $label . ' ---' . PHP_EOL);
    $callback();
}

function runExternalScript(string $scriptName, array $options = []): void
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $scriptName);

    foreach ($options as $key => $value) {
        $command .= ' --' . $key;

        if ($value !== true) {
            $command .= '=' . escapeshellarg((string) $value);
        }
    }

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    if ($output !== []) {
        fwrite(STDOUT, implode(PHP_EOL, $output) . PHP_EOL);
    }

    if ($exitCode !== 0) {
        throw new RuntimeException($scriptName . ' fallo con codigo ' . $exitCode . '.');
    }
}

/**
 * @return array{
 *   solicitante: User,
 *   solicitantes: Collection<int, User>,
 *   almacen: User,
 *   procura: User,
 *   aprobador: User,
 *   gerencia_finanzas: User,
 *   ait: User,
 *   administracion: User,
 *   finanzas_pagos: User
 * }
 */
function resolveDemoContext(): array
{
    $solicitante = User::query()->where('email', 'xavierdpdev@gmail.com')->first()
        ?? User::query()->where('email', 'prueba@gmail.com')->first()
        ?? User::query()->orderBy('id')->first();

    $solicitantes = User::query()
        ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
            'Talento Humano',
            'Mantenimiento',
            'S.I.H.O',
            'Gerencia de Operaciones',
            'Alta Gerencia',
            'Administracion',
            'Finanzas Pagos',
        ]))
        ->with(['departamento', 'cargo', 'roles'])
        ->orderBy('name')
        ->get();

    $almacen = userByRole('Almacen');
    $procura = userByRole('Procura');
    $aprobador = userByRole('Gerencia de Operaciones') ?? userByRole('Alta Gerencia');
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');
    $ait = userByRole('A.I.T');
    $administracion = userByRole('Administracion');
    $finanzasPagos = userByRole('Finanzas Pagos');

    if (! $solicitante || ! $almacen || ! $procura || ! $aprobador || ! $gerenciaFinanzas || ! $ait || ! $administracion || ! $finanzasPagos) {
        throw new RuntimeException('No se pudieron resolver todos los usuarios base por rol. Ejecuta primero los seeders.');
    }

    if ($solicitantes->isEmpty()) {
        $solicitantes = collect([$solicitante]);
    }

    return [
        'solicitante' => $solicitante,
        'solicitantes' => $solicitantes,
        'almacen' => $almacen,
        'procura' => $procura,
        'aprobador' => $aprobador,
        'gerencia_finanzas' => $gerenciaFinanzas,
        'ait' => $ait,
        'administracion' => $administracion,
        'finanzas_pagos' => $finanzasPagos,
    ];
}

function userByRole(string $role): ?User
{
    return User::query()
        ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', $role))
        ->with(['departamento', 'cargo', 'roles'])
        ->orderBy('name')
        ->first();
}

/**
 * @param array{solicitante: User, almacen: User, procura: User, aprobador: User} $context
 */
function createSolicitudDraft(array $context, string $runTag): void
{
    $numeroUsuario = ((int) SolicitudCompra::query()
        ->where('solicitado_por_user_id', $context['solicitante']->id)
        ->max('numero_solicitud_usuario')) + 1;

    $solicitud = SolicitudCompra::query()->create([
        'codigo_control' => ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control'),
        'numero_solicitud_usuario' => $numeroUsuario,
        'codigo_control_procura' => ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
        'fecha_solicitud' => now()->toDateString(),
        'tipo_solicitud' => 'Consumo',
        'prioridad' => 'Alta',
        'departamento_solicitante' => (string) ($context['solicitante']->departamento?->nombre ?? 'OPERACIONES'),
        'para_ser_usado_en' => 'Reposición preventiva de repuestos críticos para parada programada de planta (' . $runTag . ').',
        'solicitado_por_user_id' => $context['solicitante']->id,
        'por_almacen_user_id' => $context['almacen']->id,
        'aprobado_por_user_id' => $context['aprobador']->id,
        'recibido_por_user_id' => $context['procura']->id,
        'cargo_solicitante' => (string) ($context['solicitante']->cargo?->nombre ?? 'Supervisor'),
        'cargo_almacen' => (string) ($context['almacen']->cargo?->nombre ?? 'Almacenista'),
        'cargo_aprobador' => (string) ($context['aprobador']->cargo?->nombre ?? 'Gerente'),
        'cargo_receptor' => (string) ($context['procura']->cargo?->nombre ?? 'Procura'),
        'estado' => SolicitudCompra::ESTADO_BORRADOR,
    ]);

    $items = [
        ['descripcion' => 'Filtro separador de agua 2 pulgadas para línea de bombeo', 'cantidad' => 6],
        ['descripcion' => 'Juego de empaques NBR para bomba centrífuga modelo CP-80', 'cantidad' => 4],
        ['descripcion' => 'Lubricante dieléctrico para mantenimiento de tableros de control', 'cantidad' => 12],
    ];

    foreach ($items as $index => $item) {
        SolicitudCompraItem::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'item' => $index + 1,
            'descripcion' => $item['descripcion'],
            'unidad_medida' => 'UND',
            'cantidad_solicitada' => $item['cantidad'],
            'cantidad_existencia' => 0,
            'cantidad_a_comprar' => $item['cantidad'],
            'estado_item' => 'SIN_PROCESAR',
        ]);
    }

    fwrite(STDOUT, 'Solicitud borrador creada: ' . (string) $solicitud->codigo_control . PHP_EOL);
}

/**
 * @param array{solicitante: User, almacen: User, procura: User, aprobador: User} $context
 */
function createSumarioDraft(array $context, string $runTag): void
{
    $numeroUsuario = ((int) SolicitudCompra::query()
        ->where('solicitado_por_user_id', $context['solicitante']->id)
        ->max('numero_solicitud_usuario')) + 1;

    $solicitud = SolicitudCompra::query()->create([
        'codigo_control' => ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control'),
        'numero_solicitud_usuario' => $numeroUsuario,
        'codigo_control_procura' => ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
        'fecha_solicitud' => now()->subDays(2)->toDateString(),
        'tipo_solicitud' => 'Consumo',
        'prioridad' => 'Media',
        'departamento_solicitante' => (string) ($context['solicitante']->departamento?->nombre ?? 'OPERACIONES'),
        'para_ser_usado_en' => 'Acondicionamiento de estación eléctrica y reposición de consumibles de mantenimiento (' . $runTag . ').',
        'solicitado_por_user_id' => $context['solicitante']->id,
        'por_almacen_user_id' => $context['almacen']->id,
        'aprobado_por_user_id' => $context['aprobador']->id,
        'recibido_por_user_id' => $context['procura']->id,
        'cargo_solicitante' => (string) ($context['solicitante']->cargo?->nombre ?? 'Supervisor'),
        'cargo_almacen' => (string) ($context['almacen']->cargo?->nombre ?? 'Almacenista'),
        'cargo_aprobador' => (string) ($context['aprobador']->cargo?->nombre ?? 'Gerente'),
        'cargo_receptor' => (string) ($context['procura']->cargo?->nombre ?? 'Analista de Procura'),
        'firma_solicitante' => '__FIRMA_DOC__',
        'firma_almacen' => '__FIRMA_DOC__',
        'firma_aprobador' => '__FIRMA_DOC__',
        'fecha_solicitante' => now()->subDays(2)->toDateString(),
        'fecha_almacen' => now()->subDay()->toDateString(),
        'fecha_aprobador' => now()->subDay()->toDateString(),
        'fecha_receptor' => now()->toDateString(),
        'hora_receptor' => now()->format('H:i:s'),
        'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
    ]);

    $solicitudItems = collect([
        ['descripcion' => 'Breaker tripolar 60A curva C para tablero de distribución', 'cantidad' => 3],
        ['descripcion' => 'Conector bimetálico 2/0 AWG para acometida principal', 'cantidad' => 10],
        ['descripcion' => 'Canaleta ranurada industrial 60x60 mm color gris', 'cantidad' => 18],
    ])->map(function (array $row, int $index) use ($solicitud): SolicitudCompraItem {
        return SolicitudCompraItem::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'item' => $index + 1,
            'descripcion' => $row['descripcion'],
            'unidad_medida' => 'UND',
            'cantidad_solicitada' => $row['cantidad'],
            'cantidad_existencia' => 0,
            'cantidad_a_comprar' => $row['cantidad'],
            'cantidad_pedida' => $row['cantidad'],
            'cantidad_en_sumario' => 0,
            'estado_item' => 'EN_SUMARIO',
        ]);
    });

    $providers = ensureDocumentProviders();

    $sumario = Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
        'fecha' => now()->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) ($solicitud->departamento_solicitante ?? 'OPERACIONES'),
        'total_compra_prov1' => 0,
        'total_compra_prov2' => 0,
        'total_compra_prov3' => 0,
        'condiciones_pago' => 'Crédito 15 días',
        'tiempo_entrega' => 'Entrega parcial en 72 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'observaciones' => 'Borrador documental preparado para mostrar estructura de comparación de proveedores.',
        'elaborado_por_user_id' => $context['procura']->id,
        'estado' => 'BORRADOR',
        'workflow_estado' => 'BORRADOR',
    ]);

    $totals = [1 => 0.0, 2 => 0.0, 3 => 0.0];

    foreach ($solicitudItems as $index => $item) {
        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $item->id,
            'item' => $item->item,
            'descripcion' => (string) $item->descripcion,
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0),
            'validacion_gerencia_resultado' => 'PENDIENTE',
            'sub_estado' => 'EN_COMPARACION',
        ]);

        foreach ($providers as $providerIndex => $provider) {
            $precioUnitario = [1 => 124.50, 2 => 128.90, 3 => 133.75][$providerIndex + 1] + ($index * 8.25);
            $precioTotal = round(((float) $sumarioItem->cantidad) * $precioUnitario, 2);

            SumarioItemOpcion::query()->create([
                'sumario_item_id' => $sumarioItem->id,
                'opcion_numero' => $providerIndex + 1,
                'proveedor_id' => $provider->id,
                'proveedor_nombre' => (string) $provider->nombre,
                'marca' => ['Schneider Electric', 'Siemens', 'Eaton'][$providerIndex],
                'precio_unitario' => $precioUnitario,
                'precio_total' => $precioTotal,
                'seleccionada' => $providerIndex === 0,
            ]);

            $totals[$providerIndex + 1] += $precioTotal;
        }
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($totals[1], 2),
        'total_compra_prov2' => round($totals[2], 2),
        'total_compra_prov3' => round($totals[3], 2),
    ])->save();

    fwrite(STDOUT, 'Sumario borrador creado: ' . (string) $sumario->correlativo_sdc . PHP_EOL);
}

/**
 * @return array<int, Proveedor>
 */
function ensureDocumentProviders(): array
{
    $rows = [
        [
            'nombre' => 'Electroindustrial Andina, C.A.',
            'rif' => 'J-31100456-7',
            'direccion' => 'Zona Industrial Castillito, Galpón 12',
            'ciudad' => 'Valencia',
            'email' => 'ventas@electroandina.com.ve',
            'contacto' => 'María Fernanda Paredes',
            'telefono' => '04141234567',
        ],
        [
            'nombre' => 'Suministros Técnicos Occidente, C.A.',
            'rif' => 'J-30588941-2',
            'direccion' => 'Av. Intercomunal, Centro Empresarial Paraparal',
            'ciudad' => 'Valencia',
            'email' => 'licitaciones@stoccidente.com',
            'contacto' => 'Carlos Rivas',
            'telefono' => '04142345678',
        ],
        [
            'nombre' => 'Equipos y Proyectos Eléctricos Lara, C.A.',
            'rif' => 'J-29877110-5',
            'direccion' => 'Parque Comercial Los Industriales, Local 8',
            'ciudad' => 'Barquisimeto',
            'email' => 'ofertas@eprolara.com',
            'contacto' => 'Andrea Colmenares',
            'telefono' => '04142678901',
        ],
    ];

    return collect($rows)
        ->map(fn (array $row): Proveedor => Proveedor::query()->updateOrCreate(['rif' => $row['rif']], $row))
        ->values()
        ->all();
}

/**
 * @param array{solicitantes: Collection<int, User>, ait: User} $context
 */
function createSupportTickets(array $context, string $runTag, int $desiredCount): void
{
    $requesters = $context['solicitantes']->values();
    $aitUser = $context['ait'];

    $scenarios = [
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_SOPORTE_IT,
            'tipo_problema' => 'Conectividad de red',
            'nivel_urgencia' => 'Alta',
            'equipo_afectado' => 'Laptop Dell Latitude 5420',
            'descripcion_problema' => 'La estación de trabajo del supervisor de operaciones pierde acceso al ERP cada 15 minutos durante el turno nocturno.',
            'estado' => 'Abierto',
            'comentarios_ait' => null,
        ],
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_SOPORTE_IT,
            'tipo_problema' => 'Correo corporativo',
            'nivel_urgencia' => 'Media',
            'equipo_afectado' => 'Outlook - Equipo administrativo',
            'descripcion_problema' => 'No sincroniza la bandeja compartida de comprobantes de pago desde esta mañana.',
            'estado' => 'En Proceso',
            'comentarios_ait' => 'Se reconfiguró el perfil y quedó pendiente validar credenciales de Exchange al cierre del día.',
        ],
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_CAMBIO_TONER,
            'codigo_impresora' => 'RICOH-ADM-01',
            'color_toner' => 'NEGRO',
            'estado' => 'Resuelto',
            'comentarios_ait' => 'Tóner reemplazado y contador de impresión reiniciado.',
        ],
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_SOPORTE_IT,
            'tipo_problema' => 'Escáner de almacén',
            'nivel_urgencia' => 'Alta',
            'equipo_afectado' => 'Colector Zebra TC21',
            'descripcion_problema' => 'El lector de código de barras no reconoce etiquetas térmicas impresas durante el despacho matutino.',
            'estado' => 'En Proceso',
            'comentarios_ait' => 'Se actualizó el perfil del lector; falta validar sensibilidad con etiquetas nuevas.',
        ],
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_SOPORTE_IT,
            'tipo_problema' => 'Impresora de etiquetas',
            'nivel_urgencia' => 'Baja',
            'equipo_afectado' => 'Zebra ZT230',
            'descripcion_problema' => 'La impresión sale desalineada al generar etiquetas de inventario para materiales eléctricos.',
            'estado' => 'Resuelto',
            'comentarios_ait' => 'Se calibró el cabezal y se ajustó tamaño de etiqueta desde el driver.',
        ],
        [
            'tipo_solicitud' => Ticket::TIPO_SOLICITUD_CAMBIO_TONER,
            'codigo_impresora' => 'HP-FIN-02',
            'color_toner' => 'CYAN',
            'estado' => 'Cancelado',
            'comentarios_ait' => 'La solicitud fue anulada porque el cartucho disponible correspondía a otro equipo.',
        ],
    ];

    for ($i = 0; $i < $desiredCount; $i++) {
        $scenario = $scenarios[$i % count($scenarios)];
        /** @var User $requester */
        $requester = $requesters->get($i % $requesters->count()) ?? $context['ait'];

        Ticket::query()->create([
            'user_id' => $requester->id,
            'nombre_solicitante' => (string) $requester->name,
            'departamento' => (string) ($requester->departamento?->nombre ?? 'OPERACIONES'),
            'tipo_solicitud' => Ticket::normalizeTipoSolicitud((string) $scenario['tipo_solicitud']),
            'nivel_urgencia' => $scenario['nivel_urgencia'] ?? null,
            'equipo_afectado' => ($scenario['equipo_afectado'] ?? null) !== null ? (string) $scenario['equipo_afectado'] . ' [' . $runTag . ']' : null,
            'descripcion_problema' => buildTicketDescription($scenario, $runTag),
            'codigo_impresora' => $scenario['codigo_impresora'] ?? null,
            'color_toner' => $scenario['color_toner'] ?? null,
            'estado' => $scenario['estado'],
        ]);
    }

    fwrite(STDOUT, 'Tickets creados: ' . $desiredCount . PHP_EOL);
}

/**
 * @param array<string, mixed> $scenario
 */
function buildTicketDescription(array $scenario, string $runTag): ?string
{
    if (isset($scenario['descripcion_problema'])) {
        $prefix = isset($scenario['tipo_problema']) ? 'Categoria: ' . $scenario['tipo_problema'] . '. ' : '';
        $suffix = isset($scenario['comentarios_ait']) ? ' Seguimiento A.I.T.: ' . $scenario['comentarios_ait'] : '';

        return $prefix . (string) $scenario['descripcion_problema'] . ' Referencia documental ' . $runTag . '.' . $suffix;
    }

    if (($scenario['tipo_solicitud'] ?? null) === Ticket::TIPO_SOLICITUD_CAMBIO_TONER) {
        $color = (string) ($scenario['color_toner'] ?? 'NEGRO');
        $codigo = (string) ($scenario['codigo_impresora'] ?? 'IMPRESORA');
        $suffix = isset($scenario['comentarios_ait']) ? ' Seguimiento A.I.T.: ' . $scenario['comentarios_ait'] : '';

        return 'Solicitud de cambio de toner ' . $color . ' para el equipo ' . $codigo . '. Referencia documental ' . $runTag . '.' . $suffix;
    }

    return null;
}

/**
 * @param array{solicitantes: Collection<int, User>, almacen: User} $context
 */
function createDailyWithdrawalsDemo(array $context, string $runTag, int $pendingCount, int $approvedCount, int $rejectedCount): void
{
    $products = Product::query()
        ->where('is_archived', false)
        ->where('stock_actual', '>', 5)
        ->orderByDesc('stock_actual')
        ->limit(max(10, $pendingCount + $approvedCount + $rejectedCount))
        ->get();

    if ($products->isEmpty()) {
        throw new RuntimeException('No hay productos suficientes para crear retiros diarios. Ejecuta primero el bloque de inventario.');
    }

    $destinations = [
        'Planta de llenado - Línea 2',
        'Taller de mantenimiento mecánico',
        'Cuarto eléctrico principal',
        'Laboratorio de control de calidad',
        'Área de despacho de procura',
        'Patio de tanques - estación norte',
    ];

    $requesters = $context['solicitantes']->values();
    $warehouseUser = $context['almacen'];
    $sequence = 0;

    foreach (buildWithdrawalStatuses($pendingCount, $approvedCount, $rejectedCount) as $status) {
        /** @var User $requester */
        $requester = $requesters->get($sequence % $requesters->count()) ?? $warehouseUser;
        /** @var Product $product */
        $product = $products->get($sequence % $products->count());

        $requiresReturn = ($sequence % 3) === 0;
        $requestedAt = now()->subHours(($sequence + 1) * 3);
        $destination = $destinations[$sequence % count($destinations)] . ' [' . $runTag . ']';
        $quantity = (float) min(max(1, ($sequence % 4) + 1), max(1, (int) $product->stock_actual));
        $rejectionReason = $status === 'rechazado'
            ? 'Solicitud rechazada por stock reservado para mantenimiento mayor programado.'
            : null;

        $request = DailyWithdrawalRequest::query()->create([
            'user_id' => $requester->id,
            'destination' => $destination,
            'requires_return' => $requiresReturn,
            'return_date' => $requiresReturn ? now()->addDays(5 + ($sequence % 4)) : null,
            'status' => $status,
            'requested_at' => $requestedAt,
        ]);

        DailyWithdrawal::query()->create([
            'daily_withdrawal_request_id' => $request->id,
            'user_id' => $requester->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'destination' => $destination,
            'requires_return' => $requiresReturn,
            'return_date' => $requiresReturn ? now()->addDays(5 + ($sequence % 4)) : null,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
            'warehouse_user_id' => $status === 'pendiente' ? null : $warehouseUser->id,
            'requested_at' => $requestedAt,
        ]);

        $sequence++;
    }

    fwrite(STDOUT, 'Retiros diarios creados: ' . ($pendingCount + $approvedCount + $rejectedCount) . PHP_EOL);
}

/**
 * @return array<int, string>
 */
function buildWithdrawalStatuses(int $pending, int $approved, int $rejected): array
{
    return array_merge(
        array_fill(0, $pending, 'pendiente'),
        array_fill(0, $approved, 'aprobado'),
        array_fill(0, $rejected, 'rechazado'),
    );
}