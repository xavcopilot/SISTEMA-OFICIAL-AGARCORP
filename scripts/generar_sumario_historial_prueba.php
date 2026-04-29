<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Models\User;
use App\Support\ControlCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

$opts = getopt('', [
    'estado::',
    'prefijo::',
]);

$estadoArg = strtoupper(trim((string) ($opts['estado'] ?? 'APROBADO')));
$prefijo = trim((string) ($opts['prefijo'] ?? 'PRUEBA-HISTORIAL-SUMARIO'));

$workflowMap = [
    'APROBADO' => ['workflow' => 'APROBADO_GERENCIA_FINANZAS', 'resultado' => 'APROBADO', 'comentario' => 'Aprobado para historial.'],
    'ODC_GENERADA' => ['workflow' => 'ODC_GENERADA', 'resultado' => 'APROBADO', 'comentario' => 'Flujo cerrado a ODC generada para historial.'],
    'RECHAZADO' => ['workflow' => 'RECHAZADO', 'resultado' => 'RECHAZADO', 'comentario' => 'Rechazado definitivo enviado a historial.'],
    'RECHAZADO_GERENCIA' => ['workflow' => 'RECHAZADO_GERENCIA_FINANZAS', 'resultado' => 'RECHAZADO', 'comentario' => 'Rechazado por Gerencia de Finanzas.'],
];

if (! array_key_exists($estadoArg, $workflowMap)) {
    fwrite(STDERR, 'Estado invalido. Usa --estado=APROBADO|ODC_GENERADA|RECHAZADO|RECHAZADO_GERENCIA' . PHP_EOL);
    exit(1);
}

try {
    $context = resolveUsersContext();
    $flow = $workflowMap[$estadoArg];

    $result = DB::transaction(function () use ($context, $prefijo, $flow): array {
        $providers = ensureProviders();

        $solicitud = createSolicitudCompraBase(
            $context['solicitante'],
            $context['almacen'],
            $context['aprobador'],
            $context['procura'],
            $prefijo
        );

        $solicitudItems = createSolicitudItems($solicitud);
        $sumario = createHistorySumario($solicitud, $context, $flow);
        createComparativeRows($sumario, $solicitudItems, $providers, $flow['resultado']);

        return [
            'sumario' => $sumario->fresh(['solicitudCompra', 'items.opciones']),
        ];
    });

    /** @var Sumario $sumario */
    $sumario = $result['sumario'];

    echo 'OK: Sumario de historial creado.' . PHP_EOL;
    echo 'Sumario ID: ' . $sumario->id . PHP_EOL;
    echo 'Correlativo: ' . (string) $sumario->correlativo_sdc . PHP_EOL;
    echo 'Solicitud: ' . (string) ($sumario->solicitudCompra?->codigo_control ?: $sumario->solicitud_compra_id) . PHP_EOL;
    echo 'workflow_estado: ' . (string) $sumario->workflow_estado . PHP_EOL;
    echo 'decision_gerencia_resultado: ' . (string) $sumario->decision_gerencia_resultado . PHP_EOL;
    echo PHP_EOL . 'Debe aparecer en: Sumarios > Historial de sumarios.' . PHP_EOL;

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return array{solicitante: User, almacen: User, aprobador: User, procura: User, validador_finanzas: User, gerencia_finanzas: User}
 */
function resolveUsersContext(): array
{
    $solicitante = User::query()->orderBy('id')->first();
    $almacen = userByRole('Almacen');
    $aprobador = userByRole('Gerencia de Operaciones')
        ?? userByRole('Alta Gerencia');
    $procura = userByRole('Procura');
    $validador = userByRole('Validador Finanzas')
        ?? userByRole('Finanzas Pagos')
        ?? userByRole('Finanzas');
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $validador || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver usuarios requeridos por rol.');
    }

    return [
        'solicitante' => $solicitante,
        'almacen' => $almacen,
        'aprobador' => $aprobador,
        'procura' => $procura,
        'validador_finanzas' => $validador,
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
    $rows = [
        [
            'nombre' => 'Tecno Suministros Orion',
            'rif' => 'J-42000001-1',
            'direccion' => 'Zona Industrial Norte',
            'ciudad' => 'Valencia',
            'email' => 'orion.historial@example.com',
            'contacto' => 'Ana Orion',
            'telefono' => '04140002001',
        ],
        [
            'nombre' => 'Insumos Delta 360 C.A.',
            'rif' => 'J-42000002-2',
            'direccion' => 'Av. Principal Centro',
            'ciudad' => 'Caracas',
            'email' => 'delta.historial@example.com',
            'contacto' => 'Bruno Delta',
            'telefono' => '04140002002',
        ],
        [
            'nombre' => 'Comercial Sigma Integral',
            'rif' => 'J-42000003-3',
            'direccion' => 'Parque Logistico Este',
            'ciudad' => 'Maracay',
            'email' => 'sigma.historial@example.com',
            'contacto' => 'Carla Sigma',
            'telefono' => '04140002003',
        ],
    ];

    return collect($rows)
        ->map(fn (array $data): Proveedor => Proveedor::query()->updateOrCreate(['rif' => $data['rif']], $data))
        ->values()
        ->all();
}

function createSolicitudCompraBase(User $solicitante, User $almacen, User $aprobador, User $procura, string $prefijo): SolicitudCompra
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
        'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'A.I.T'),
        'para_ser_usado_en' => 'Prueba de historial de sumario (' . $prefijo . ').',
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
        'fecha_solicitante' => now()->subDays(3)->toDateString(),
        'fecha_almacen' => now()->subDays(2)->toDateString(),
        'fecha_aprobador' => now()->subDay()->toDateString(),
        'fecha_receptor' => now()->toDateString(),
        'hora_receptor' => now()->format('H:i:s'),
        'estado' => 'SUMARIO_EN_REVISION',
    ]);
}

/**
 * @return array<int, SolicitudCompraItem>
 */
function createSolicitudItems(SolicitudCompra $solicitud): array
{
    $rows = [
        ['item' => 1, 'descripcion' => 'Mouse', 'cantidad' => 8],
        ['item' => 2, 'descripcion' => 'Teclado', 'cantidad' => 6],
        ['item' => 3, 'descripcion' => 'Monitor', 'cantidad' => 4],
    ];

    return collect($rows)
        ->map(function (array $row) use ($solicitud): SolicitudCompraItem {
            $cantidad = (float) $row['cantidad'];

            return SolicitudCompraItem::query()->create([
                'solicitud_compra_id' => $solicitud->id,
                'item' => (int) $row['item'],
                'descripcion' => (string) $row['descripcion'],
                'unidad_medida' => 'UND',
                'cantidad_solicitada' => $cantidad,
                'cantidad_existencia' => 0,
                'cantidad_a_comprar' => $cantidad,
                'cantidad_pedida' => $cantidad,
                'estado_item' => 'EN_SUMARIO',
                'cantidad_en_sumario' => $cantidad,
            ]);
        })
        ->values()
        ->all();
}

/**
 * @param array{solicitante: User, almacen: User, aprobador: User, procura: User, validador_finanzas: User, gerencia_finanzas: User} $context
 * @param array{workflow: string, resultado: string, comentario: string} $flow
 */
function createHistorySumario(SolicitudCompra $solicitud, array $context, array $flow): Sumario
{
    return Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
        'fecha' => now()->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) ($solicitud->departamento_solicitante ?? 'A.I.T'),
        'total_compra_prov1' => 0,
        'total_compra_prov2' => 0,
        'total_compra_prov3' => 0,
        'condiciones_pago' => 'Contado',
        'tiempo_entrega' => '48 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'observaciones' => 'Generado por script para pruebas de historial y exportacion PDF.',
        'elaborado_por_user_id' => $context['procura']->id,
        'revisado_por_user_id' => $context['gerencia_finanzas']->id,
        'estado' => $flow['resultado'] === 'RECHAZADO' ? 'RECHAZADO' : 'APROBADO',
        'workflow_estado' => $flow['workflow'],
        'enviado_validacion_finanzas_at' => now()->subHours(5),
        'enviado_por_user_id' => $context['procura']->id,
        'validado_finanzas_at' => now()->subHours(4),
        'validado_por_user_id' => $context['validador_finanzas']->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(3),
        'decision_gerencia_por_user_id' => $context['gerencia_finanzas']->id,
        'decision_gerencia_resultado' => $flow['resultado'],
        'decision_gerencia_comentario' => $flow['comentario'],
    ]);
}

/**
 * @param array<int, SolicitudCompraItem> $solicitudItems
 * @param array<int, Proveedor> $providers
 */
function createComparativeRows(Sumario $sumario, array $solicitudItems, array $providers, string $resultadoGerencia): void
{
    $totalProv = [1 => 0.0, 2 => 0.0, 3 => 0.0];

    foreach ($solicitudItems as $index => $item) {
        $providerNumber = ($index % 3) + 1;

        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $item->id,
            'item' => $item->item,
            'descripcion' => (string) $item->descripcion,
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0),
            'validacion_gerencia_resultado' => $resultadoGerencia === 'RECHAZADO' ? 'RECHAZADO' : 'CORRECTO',
            'validacion_gerencia_comentario' => $resultadoGerencia === 'RECHAZADO' ? 'Item de prueba rechazado para historial.' : null,
            'sub_estado' => $resultadoGerencia === 'RECHAZADO' ? 'RECHAZADO_GERENCIA' : 'PENDIENTE_OC',
        ]);

        foreach ([1, 2, 3] as $opcionNum) {
            $provider = $providers[$opcionNum - 1];
            $precioUnitario = match ($opcionNum) {
                1 => 18.50,
                2 => 23.80,
                default => 41.20,
            };

            $cantidad = (float) ($sumarioItem->cantidad ?? 0);
            $precioTotal = round($cantidad * $precioUnitario, 2);

            SumarioItemOpcion::query()->create([
                'sumario_item_id' => $sumarioItem->id,
                'opcion_numero' => $opcionNum,
                'proveedor_id' => $provider->id,
                'proveedor_nombre' => (string) $provider->nombre,
                'marca' => 'GENERICA',
                'precio_unitario' => $precioUnitario,
                'precio_total' => $precioTotal,
                'seleccionada' => $opcionNum === $providerNumber,
            ]);

            if ($opcionNum === $providerNumber) {
                $totalProv[$opcionNum] += $precioTotal;
            }
        }
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($totalProv[1], 2),
        'total_compra_prov2' => round($totalProv[2], 2),
        'total_compra_prov3' => round($totalProv[3], 2),
    ])->save();
}
