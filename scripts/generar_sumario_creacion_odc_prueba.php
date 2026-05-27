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
    'prefijo::',
]);

$prefijo = trim((string) ($opts['prefijo'] ?? 'PRUEBA-CREACION-ODC'));

try {
    $context = resolveUsersContext();

    $result = DB::transaction(function () use ($context, $prefijo): array {
        $providers = ensureProviders();

        $solicitud = createSolicitudCompraBase($context['solicitante'], $context['almacen'], $context['aprobador'], $context['procura'], $prefijo);
        $solicitudItems = createSolicitudItems($solicitud, 3);

        $sumario = createPendingOdcSumario($solicitud, $context['procura'], $context['gerencia_finanzas']);
        createComparativeRows($sumario, $solicitudItems, $providers);

        return [
            'sumario' => $sumario->fresh(['solicitudCompra', 'items.opciones']),
            'providers' => $providers,
        ];
    });

    /** @var Sumario $sumario */
    $sumario = $result['sumario'];

    echo 'OK: Se creo sumario para Creacion de ODC.' . PHP_EOL;
    echo 'Sumario ID: ' . $sumario->id . PHP_EOL;
    echo 'Correlativo: ' . (string) $sumario->correlativo_sdc . PHP_EOL;
    echo 'Solicitud: ' . (string) ($sumario->solicitudCompra?->codigo_control ?: $sumario->solicitud_compra_id) . PHP_EOL;
    echo 'workflow_estado: ' . (string) $sumario->workflow_estado . PHP_EOL;
    echo 'estado: ' . (string) $sumario->estado . PHP_EOL;
    echo 'Detalle seleccionado por item:' . PHP_EOL;

    foreach ($sumario->items as $item) {
        $selected = $item->opciones->firstWhere('seleccionada', true);
        echo '- Item #' . (string) ($item->item ?: $item->id)
            . ' | ' . (string) $item->descripcion
            . ' | Proveedor: ' . (string) ($selected?->proveedor_nombre ?? '-')
            . ' | P/T: ' . number_format((float) ($selected?->precio_total ?? 0), 2, ',', '.')
            . PHP_EOL;
    }

    echo PHP_EOL . 'Abre modulo Ordenes de Compra > Creacion de ODC para probar la creacion manual de ODC por proveedor.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return array{solicitante: User, almacen: User, aprobador: User, procura: User, gerencia_finanzas: User}
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
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver usuarios requeridos por rol.');
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
            'rif' => 'J-39000001-1',
            'direccion' => 'Zona Industrial Norte',
            'ciudad' => 'Valencia',
            'email' => 'orion@example.com',
            'contacto' => 'Ana Orion',
            'telefono' => '04140001001',
        ],
        [
            'nombre' => 'Insumos Delta 360 C.A.',
            'rif' => 'J-39000002-2',
            'direccion' => 'Av. Principal Centro',
            'ciudad' => 'Valencia',
            'email' => 'delta@example.com',
            'contacto' => 'Bruno Delta',
            'telefono' => '04140001002',
        ],
        [
            'nombre' => 'Comercial Sigma Integral',
            'rif' => 'J-39000003-3',
            'direccion' => 'Parque Logistico Este',
            'ciudad' => 'Valencia',
            'email' => 'sigma@example.com',
            'contacto' => 'Carla Sigma',
            'telefono' => '04140001003',
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
        'para_ser_usado_en' => 'Prueba directa para Creacion de ODC (' . $prefijo . ').',
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
function createSolicitudItems(SolicitudCompra $solicitud, int $itemsCount): array
{
    $items = [];

    for ($i = 1; $i <= $itemsCount; $i++) {
        $cantidad = 5 + $i;

        $items[] = SolicitudCompraItem::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'item' => $i,
            'descripcion' => match ($i) {
                1 => 'Mouse',
                2 => 'Teclado',
                default => 'Monitor',
            },
            'unidad_medida' => 'UND',
            'cantidad_solicitada' => $cantidad,
            'cantidad_existencia' => 0,
            'cantidad_a_comprar' => $cantidad,
            'cantidad_pedida' => $cantidad,
            'estado_item' => 'EN_SUMARIO',
            'cantidad_en_sumario' => $cantidad,
        ]);
    }

    return $items;
}

function createPendingOdcSumario(SolicitudCompra $solicitud, User $procura, User $gerenciaFinanzas): Sumario
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
        'observaciones' => 'Prueba de Creacion de ODC con 3 proveedores pendientes.',
        'elaborado_por_user_id' => $procura->id,
        'revisado_por_user_id' => $gerenciaFinanzas->id,
        'estado' => 'PENDIENTE_CREACION_ODC',
        'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
        'enviado_validacion_finanzas_at' => now()->subHours(5),
        'enviado_por_user_id' => $procura->id,
        'validado_finanzas_at' => now()->subHours(4),
        'validado_por_user_id' => $gerenciaFinanzas->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(3),
        'decision_gerencia_por_user_id' => $gerenciaFinanzas->id,
        'decision_gerencia_resultado' => 'APROBADO',
        'decision_gerencia_comentario' => 'Aprobado para crear ODC.',
    ]);
}

/**
 * @param array<int, SolicitudCompraItem> $solicitudItems
 * @param array<int, Proveedor> $providers
 */
function createComparativeRows(Sumario $sumario, array $solicitudItems, array $providers): void
{
    $totalProv = [1 => 0.0, 2 => 0.0, 3 => 0.0];

    foreach ($solicitudItems as $index => $item) {
        $providerNumber = ($index % 3) + 1;
        $provider = $providers[$providerNumber - 1];

        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $item->id,
            'item' => $item->item,
            'descripcion' => (string) $item->descripcion,
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0),
            'validacion_gerencia_resultado' => 'CORRECTO',
            'validacion_gerencia_comentario' => null,
            'sub_estado' => 'PENDIENTE_OC',
        ]);

        $precioUnitario = match ($providerNumber) {
            1 => 18.50,
            2 => 23.80,
            default => 41.20,
        };

        $cantidad = (float) ($sumarioItem->cantidad ?? 0);
        $precioTotal = round($cantidad * $precioUnitario, 2);

        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => $providerNumber,
            'proveedor_id' => $provider->id,
            'proveedor_nombre' => (string) $provider->nombre,
            'marca' => 'GENERICA',
            'precio_unitario' => $precioUnitario,
            'precio_total' => $precioTotal,
            'seleccionada' => true,
        ]);

        $totalProv[$providerNumber] += $precioTotal;
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($totalProv[1], 2),
        'total_compra_prov2' => round($totalProv[2], 2),
        'total_compra_prov3' => round($totalProv[3], 2),
    ])->save();
}
