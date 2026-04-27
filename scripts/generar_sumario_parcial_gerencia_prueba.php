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

try {
    $context = resolveUsersContext();

    $result = DB::transaction(function () use ($context): array {
        $providers = ensureProviders();

        $solicitud = createSolicitud($context);
        $solicitudItems = createSolicitudItems($solicitud);

        $sumario = createPartialGerenciaSumario($solicitud, $context, $providers, $solicitudItems);

        return [
            'solicitud' => $solicitud,
            'sumario' => $sumario,
            'providers' => $providers,
        ];
    });

    /** @var SolicitudCompra $solicitud */
    $solicitud = $result['solicitud'];
    /** @var Sumario $sumario */
    $sumario = $result['sumario'];

    fwrite(STDOUT, PHP_EOL . '=== SUMARIO DE PRUEBA CREADO ===' . PHP_EOL);
    fwrite(STDOUT, 'Solicitud ID: ' . $solicitud->id . ' | Codigo: ' . (string) $solicitud->codigo_control . PHP_EOL);
    fwrite(STDOUT, 'Sumario ID: ' . $sumario->id . ' | Correlativo: ' . (string) $sumario->correlativo_sdc . PHP_EOL);
    fwrite(STDOUT, 'Workflow: ' . (string) $sumario->workflow_estado . PHP_EOL);
    fwrite(STDOUT, 'Decision Gerencia: ' . (string) $sumario->decision_gerencia_resultado . PHP_EOL);
    fwrite(STDOUT, PHP_EOL . 'Items y seleccion:' . PHP_EOL);
    fwrite(STDOUT, '- Mouse (CORRECTO) -> Proveedor 1 seleccionado' . PHP_EOL);
    fwrite(STDOUT, '- Monitor (RECHAZADO) -> Proveedor 2 seleccionado' . PHP_EOL);
    fwrite(STDOUT, '- Teclado (RECHAZADO) -> Proveedor 3 seleccionado' . PHP_EOL);
    fwrite(STDOUT, PHP_EOL . 'Listo para pruebas en Sumarios en correccion.' . PHP_EOL);

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al generar sumario de prueba: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return array{solicitante: User, almacen: User, aprobador: User, procura: User, validador_finanzas: User, gerencia_finanzas: User}
 */
function resolveUsersContext(): array
{
    $solicitante = User::query()->orderBy('id')->first();
    $almacen = userByRole('Almacen');
    $aprobador = userByRole('Gerencia de Operaciones') ?? userByRole('Alta Gerencia');
    $procura = userByRole('Procura');
    $validador = userByRole('Validador Finanzas') ?? userByRole('Finanzas');
    $gerenciaFinanzas = userByRole('Gerencia de Finanzas');

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $validador || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver todos los usuarios por rol. Revisa seeders/roles.');
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
    $base = [
        [
            'nombre' => 'Tecno Suministros Orion',
            'rif' => 'J-41000011-1',
            'direccion' => 'Zona Industrial Norte, Galpon 3',
            'ciudad' => 'Valencia',
            'email' => 'ventas.orion@example.com',
            'contacto' => 'Luis Romero',
            'telefono' => '0414-5551101',
        ],
        [
            'nombre' => 'Distribuidora Maxis C.A.',
            'rif' => 'J-41000022-2',
            'direccion' => 'Av. Comercio, Torre Empresarial 4',
            'ciudad' => 'Caracas',
            'email' => 'cotizaciones.maxis@example.com',
            'contacto' => 'Maria Gamboa',
            'telefono' => '0412-5552202',
        ],
        [
            'nombre' => 'Insumos Delta 360 C.A.',
            'rif' => 'J-41000033-3',
            'direccion' => 'Centro Logistico Sur, Local 12',
            'ciudad' => 'Maracay',
            'email' => 'ventas.delta@example.com',
            'contacto' => 'Rafael Brito',
            'telefono' => '0424-5553303',
        ],
    ];

    return collect($base)
        ->map(fn (array $provider): Proveedor => Proveedor::query()->updateOrCreate(['rif' => $provider['rif']], $provider))
        ->values()
        ->all();
}

/**
 * @param array{solicitante: User, almacen: User, aprobador: User, procura: User, validador_finanzas: User, gerencia_finanzas: User} $context
 */
function createSolicitud(array $context): SolicitudCompra
{
    $solicitante = $context['solicitante'];
    $almacen = $context['almacen'];
    $aprobador = $context['aprobador'];
    $procura = $context['procura'];

    $numeroUsuario = ((int) SolicitudCompra::query()
        ->where('solicitado_por_user_id', $solicitante->id)
        ->max('numero_solicitud_usuario')) + 1;

    return SolicitudCompra::query()->create([
        'codigo_control' => ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control'),
        'numero_solicitud_usuario' => $numeroUsuario,
        'codigo_control_procura' => ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
        'fecha_solicitud' => now()->toDateString(),
        'tipo_solicitud' => 'Consumo',
        'prioridad' => 'Alta',
        'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'A.I.T'),
        'para_ser_usado_en' => 'Equipamiento de puestos administrativos y soporte.',
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
 * @return array<string, SolicitudCompraItem>
 */
function createSolicitudItems(SolicitudCompra $solicitud): array
{
    $rows = [
        'Mouse' => ['item' => 1, 'cantidad' => 10.0],
        'Monitor' => ['item' => 2, 'cantidad' => 4.0],
        'Teclado' => ['item' => 3, 'cantidad' => 8.0],
    ];

    $created = [];

    foreach ($rows as $descripcion => $data) {
        $cantidad = (float) $data['cantidad'];

        $created[$descripcion] = SolicitudCompraItem::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'item' => (int) $data['item'],
            'descripcion' => $descripcion,
            'unidad_medida' => 'UND',
            'cantidad_solicitada' => $cantidad,
            'cantidad_existencia' => 0,
            'cantidad_a_comprar' => $cantidad,
            'cantidad_en_sumario' => $cantidad,
            'estado_item' => 'EN_SUMARIO',
        ]);
    }

    return $created;
}

/**
 * @param array{solicitante: User, almacen: User, aprobador: User, procura: User, validador_finanzas: User, gerencia_finanzas: User} $context
 * @param array<int, Proveedor> $providers
 * @param array<string, SolicitudCompraItem> $solicitudItems
 */
function createPartialGerenciaSumario(SolicitudCompra $solicitud, array $context, array $providers, array $solicitudItems): Sumario
{
    $sumario = Sumario::query()->create([
        'solicitud_compra_id' => $solicitud->id,
        'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
        'fecha' => now()->toDateString(),
        'procedencia' => 'LOCAL',
        'tipo_orden' => 'COMPRA',
        'departamento_solicitante' => (string) $solicitud->departamento_solicitante,
        'total_compra_prov1' => 0,
        'total_compra_prov2' => 0,
        'total_compra_prov3' => 0,
        'condiciones_pago' => 'Credito 15 dias',
        'tiempo_entrega' => '48 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'proveedor_ganador_id' => $providers[0]->id,
        'observaciones' => 'Prueba realista de rechazo parcial en Gerencia de Finanzas.',
        'elaborado_por_user_id' => $context['procura']->id,
        'revisado_por_user_id' => $context['gerencia_finanzas']->id,
        'estado' => 'EN_CORRECCION_PROCURA',
        'workflow_estado' => 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL',
        'enviado_validacion_finanzas_at' => now()->subHours(8),
        'enviado_por_user_id' => $context['procura']->id,
        'validado_finanzas_at' => now()->subHours(6),
        'validado_por_user_id' => $context['validador_finanzas']->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(2),
        'decision_gerencia_por_user_id' => $context['gerencia_finanzas']->id,
        'decision_gerencia_resultado' => 'PARCIAL',
        'decision_gerencia_comentario' => 'Se aprueba Mouse; Monitor y Teclado requieren correccion de cotizacion y criterio tecnico.',
    ]);

    $specs = [
        [
            'name' => 'Mouse',
            'resultado' => 'CORRECTO',
            'comentario' => null,
            'sub_estado' => 'PENDIENTE_OC',
            'selected_option' => 1,
            'prices' => [18.50, 19.20, 20.00],
            'brands' => ['LogiGo M120', 'MaxMouse Pro', 'Delta Click S'],
        ],
        [
            'name' => 'Monitor',
            'resultado' => 'RECHAZADO',
            'comentario' => 'Mejorar relacion precio/garantia. Se solicita nueva oferta competitiva.',
            'sub_estado' => 'RECHAZADO_GERENCIA',
            'selected_option' => 2,
            'prices' => [139.00, 132.50, 145.00],
            'brands' => ['ViewTech 24N', 'MaxVision 24F', 'DeltaScreen 24H'],
        ],
        [
            'name' => 'Teclado',
            'resultado' => 'RECHAZADO',
            'comentario' => 'La opcion seleccionada no cumple ergonomia requerida. Re-cotizar.',
            'sub_estado' => 'RECHAZADO_GERENCIA',
            'selected_option' => 3,
            'prices' => [26.00, 24.50, 23.80],
            'brands' => ['KeyBoard Plus', 'MaxKeys Office', 'Delta Type Pro'],
        ],
    ];

    $totalsByProviderColumn = [1 => 0.0, 2 => 0.0, 3 => 0.0];

    foreach ($specs as $idx => $spec) {
        $sourceItem = $solicitudItems[$spec['name']];
        $cantidad = (float) ($sourceItem->cantidad_a_comprar ?? $sourceItem->cantidad_solicitada ?? 1);

        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $sourceItem->id,
            'item' => $sourceItem->item,
            'descripcion' => $spec['name'],
            'unidad_medida' => $sourceItem->unidad_medida,
            'cantidad' => $cantidad,
            'validacion_gerencia_resultado' => $spec['resultado'],
            'validacion_gerencia_comentario' => $spec['comentario'],
            'sub_estado' => $spec['sub_estado'],
        ]);

        for ($col = 1; $col <= 3; $col++) {
            $precioUnitario = (float) $spec['prices'][$col - 1];
            $precioTotal = round($precioUnitario * $cantidad, 2);

            SumarioItemOpcion::query()->create([
                'sumario_item_id' => $sumarioItem->id,
                'opcion_numero' => $col,
                'proveedor_id' => $providers[$col - 1]->id,
                'proveedor_nombre' => $providers[$col - 1]->nombre,
                'marca' => $spec['brands'][$col - 1],
                'precio_unitario' => $precioUnitario,
                'precio_total' => $precioTotal,
                'seleccionada' => $spec['selected_option'] === $col,
            ]);

            $totalsByProviderColumn[$col] += $precioTotal;
        }
    }

    $sumario->forceFill([
        'total_compra_prov1' => round($totalsByProviderColumn[1], 2),
        'total_compra_prov2' => round($totalsByProviderColumn[2], 2),
        'total_compra_prov3' => round($totalsByProviderColumn[3], 2),
    ])->save();

    return $sumario;
}
