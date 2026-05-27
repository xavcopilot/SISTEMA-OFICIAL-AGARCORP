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
use App\Support\SumarioFinanceApprovalService;
use App\Support\UserSignaturePath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

$opts = getopt('', [
    'email::',
    'prefijo::',
]);

$email = trim((string) ($opts['email'] ?? 'xavierdpdev@gmail.com'));
$prefijo = trim((string) ($opts['prefijo'] ?? 'PRUEBA-FIRMAS-PROCURA'));

try {
    $context = resolveUsersContext($email);

    $result = DB::transaction(function () use ($context, $prefijo): array {
        $providers = ensureProviders();

        $solicitud = createSolicitudFirmada($context, $prefijo);
        $solicitudItems = createSolicitudItems($solicitud);

        $sumario = createSignedApprovedSumario($solicitud, $context, $prefijo);
        createComparativeRows($sumario, $solicitudItems, $providers);

        $orders = app(SumarioFinanceApprovalService::class)->generateOrdersFromSelections($sumario, $context['procura']);

        if ($orders === []) {
            throw new RuntimeException('No se pudo generar la ODC de prueba para firmas.');
        }

        $order = OrdenCompra::query()->findOrFail($orders[0]->id);

        $order->forceFill([
            'estado' => 'APROBADA',
            'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
            'elaborado_por_user_id' => $context['procura']->id,
            'elaborado_firmado_at' => now()->subHours(2),
            'aprobado_por_user_id' => $context['gerencia_finanzas']->id,
            'aprobado_firmado_at' => now()->subHour(),
        ])->save();

        $sumario->forceFill([
            'estado' => 'REVISADO_FINANZAS',
            'workflow_estado' => 'ODC_GENERADA',
        ])->save();

        return [
            'solicitud' => $solicitud->fresh(['solicitadoPor', 'porAlmacen', 'aprobadoPor', 'recibidoPor']),
            'sumario' => $sumario->fresh(['elaboradoPor', 'validadoPor', 'decisionGerenciaPor']),
            'odc' => $order->fresh(['elaboradoPor', 'aprobadoPor', 'sumario.solicitudCompra']),
        ];
    });

    printResult($result);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al generar escenario de firmas: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return array{solicitante:User,almacen:User,aprobador:User,procura:User,validador_finanzas:User,gerencia_finanzas:User}
 */
function resolveUsersContext(string $email): array
{
    $solicitante = User::query()
        ->where('email', $email)
        ->first();

    if (! $solicitante || ! hasSignature($solicitante)) {
        $solicitante = firstUserWithSignatureByEmails([
            'xavierdpdev@gmail.com',
            'prueba@gmail.com',
        ]);
    }

    $almacen = userByRoleWithSignature('Almacen');
    $aprobador = userByRoleWithSignature('Gerencia de Operaciones')
        ?? userByRoleWithSignature('Alta Gerencia');
    $procura = userByRoleWithSignature('Procura');
    $validador = userByRoleWithSignature('Validador Finanzas')
        ?? userByRoleWithSignature('Finanzas Pagos')
        ?? userByRoleWithSignature('Finanzas');
    $gerenciaFinanzas = userByRoleWithSignature('Gerencia de Finanzas');

    $usedIds = array_filter([
        $solicitante?->id,
        $almacen?->id,
        $procura?->id,
        $validador?->id,
        $gerenciaFinanzas?->id,
    ]);

    if (! $aprobador) {
        $aprobador = firstSignedUserExcluding($usedIds);
    }

    if (! $solicitante || ! $almacen || ! $aprobador || ! $procura || ! $validador || ! $gerenciaFinanzas) {
        throw new RuntimeException('No se pudieron resolver usuarios con firma para todos los roles requeridos.');
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

function firstUserWithSignatureByEmails(array $emails): ?User
{
    foreach ($emails as $email) {
        $user = User::query()->where('email', $email)->first();

        if ($user && hasSignature($user)) {
            return $user;
        }
    }

    return User::query()
        ->orderBy('id')
        ->get()
        ->first(fn (User $user): bool => hasSignature($user));
}

function userByRoleWithSignature(string $roleName): ?User
{
    return User::query()
        ->whereHas('roles', fn (Builder $query) => $query->where('name', $roleName))
        ->orderBy('id')
        ->get()
        ->first(fn (User $user): bool => hasSignature($user));
}

function hasSignature(User $user): bool
{
    return UserSignaturePath::findByUserId((int) $user->id) !== null;
}

function firstSignedUserExcluding(array $excludeIds = []): ?User
{
    $excluded = array_map('intval', $excludeIds);

    return User::query()
        ->orderBy('id')
        ->get()
        ->first(function (User $user) use ($excluded): bool {
            return ! in_array((int) $user->id, $excluded, true)
                && hasSignature($user);
        });
}

/**
 * @return array<int, Proveedor>
 */
function ensureProviders(): array
{
    $rows = [
        [
            'nombre' => 'Proveedor Firmas Uno',
            'rif' => 'J-48000001-1',
            'direccion' => 'Zona Industrial Firmas 1',
            'ciudad' => 'Valencia',
            'email' => 'firmas-uno@example.com',
            'contacto' => 'Ana Firmas',
            'telefono' => '04140003001',
        ],
        [
            'nombre' => 'Proveedor Firmas Dos',
            'rif' => 'J-48000002-2',
            'direccion' => 'Zona Industrial Firmas 2',
            'ciudad' => 'Valencia',
            'email' => 'firmas-dos@example.com',
            'contacto' => 'Bruno Firmas',
            'telefono' => '04140003002',
        ],
        [
            'nombre' => 'Proveedor Firmas Tres',
            'rif' => 'J-48000003-3',
            'direccion' => 'Zona Industrial Firmas 3',
            'ciudad' => 'Valencia',
            'email' => 'firmas-tres@example.com',
            'contacto' => 'Carla Firmas',
            'telefono' => '04140003003',
        ],
    ];

    return collect($rows)
        ->map(fn (array $data): Proveedor => Proveedor::query()->updateOrCreate(['rif' => $data['rif']], $data))
        ->values()
        ->all();
}

/**
 * @param array{solicitante:User,almacen:User,aprobador:User,procura:User,validador_finanzas:User,gerencia_finanzas:User} $context
 */
function createSolicitudFirmada(array $context, string $prefijo): SolicitudCompra
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
        'prioridad' => 'Media',
        'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'A.I.T'),
        'para_ser_usado_en' => 'Prueba integral de firmas en solicitud, sumario y ODC (' . $prefijo . ').',
        'solicitado_por_user_id' => $solicitante->id,
        'por_almacen_user_id' => $almacen->id,
        'aprobado_por_user_id' => $aprobador->id,
        'recibido_por_user_id' => $procura->id,
        'cargo_solicitante' => (string) ($solicitante->cargo?->nombre ?? 'Solicitante'),
        'cargo_almacen' => (string) ($almacen->cargo?->nombre ?? 'Almacen'),
        'cargo_aprobador' => (string) ($aprobador->cargo?->nombre ?? 'Aprobador'),
        'cargo_receptor' => (string) ($procura->cargo?->nombre ?? 'Procura'),
        'firma_solicitante' => UserSignaturePath::resolveForUser($solicitante),
        'firma_almacen' => UserSignaturePath::resolveForUser($almacen),
        'firma_aprobador' => UserSignaturePath::resolveForUser($aprobador),
        'firma_receptor' => UserSignaturePath::resolveForUser($procura),
        'fecha_solicitante' => now()->subDays(4)->toDateString(),
        'fecha_almacen' => now()->subDays(3)->toDateString(),
        'fecha_aprobador' => now()->subDays(2)->toDateString(),
        'fecha_receptor' => now()->subDay()->toDateString(),
        'hora_receptor' => now()->subDay()->format('H:i:s'),
        'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
    ]);
}

/**
 * @return array<int, SolicitudCompraItem>
 */
function createSolicitudItems(SolicitudCompra $solicitud): array
{
    $rows = [
        ['item' => 1, 'descripcion' => 'KIT DE HERRAMIENTAS DE PRUEBA', 'cantidad' => 4],
        ['item' => 2, 'descripcion' => 'BOBINAS INDUSTRIALES DE PRUEBA', 'cantidad' => 3],
        ['item' => 3, 'descripcion' => 'SENSORES DE CAMPO DE PRUEBA', 'cantidad' => 2],
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
                'cantidad_en_sumario' => $cantidad,
                'estado_item' => 'EN_SUMARIO',
            ]);
        })
        ->values()
        ->all();
}

/**
 * @param array{solicitante:User,almacen:User,aprobador:User,procura:User,validador_finanzas:User,gerencia_finanzas:User} $context
 */
function createSignedApprovedSumario(SolicitudCompra $solicitud, array $context, string $prefijo): Sumario
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
        'tiempo_entrega' => '72 horas',
        'prioridad' => 'MEJOR_PRECIO',
        'observaciones' => 'Escenario de firmas para historial de procura y aprobaciones (' . $prefijo . ').',
        'elaborado_por_user_id' => $context['procura']->id,
        'revisado_por_user_id' => $context['gerencia_finanzas']->id,
        'estado' => 'REVISADO_FINANZAS',
        'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
        'enviado_validacion_finanzas_at' => now()->subHours(6),
        'enviado_por_user_id' => $context['procura']->id,
        'validado_finanzas_at' => now()->subHours(5),
        'validado_por_user_id' => $context['validador_finanzas']->id,
        'validacion_finanzas_resultado' => 'APROBADO',
        'decision_gerencia_finanzas_at' => now()->subHours(4),
        'decision_gerencia_por_user_id' => $context['gerencia_finanzas']->id,
        'decision_gerencia_resultado' => 'APROBADO',
        'decision_gerencia_comentario' => 'Aprobado para prueba visual de firmas.',
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
        $sumarioItem = SumarioItem::query()->create([
            'sumario_id' => $sumario->id,
            'solicitud_compra_item_id' => $item->id,
            'item' => $item->item,
            'descripcion' => (string) $item->descripcion,
            'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
            'cantidad' => (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0),
            'validacion_gerencia_resultado' => 'CORRECTO',
            'sub_estado' => 'PENDIENTE_OC',
        ]);

        $selectedOptionNumber = 1;

        foreach ($providers as $providerIndex => $provider) {
            $optionNumber = $providerIndex + 1;
            $price = round(25 + ($index * 5) + ($providerIndex * 2.5), 2);
            $qty = (float) $sumarioItem->cantidad;
            $total = round($qty * $price, 2);

            SumarioItemOpcion::query()->create([
                'sumario_item_id' => $sumarioItem->id,
                'opcion_numero' => $optionNumber,
                'proveedor_id' => $provider->id,
                'proveedor_nombre' => (string) $provider->nombre,
                'marca' => 'MARCA FIRMA ' . $optionNumber,
                'precio_unitario' => $price,
                'precio_total' => $total,
                'seleccionada' => $optionNumber === $selectedOptionNumber,
            ]);

            $totalProv[$optionNumber] += $total;
        }
    }

    $sumario->forceFill([
        'proveedor_ganador_id' => $providers[0]->id,
        'total_compra_prov1' => round($totalProv[1], 2),
        'total_compra_prov2' => round($totalProv[2], 2),
        'total_compra_prov3' => round($totalProv[3], 2),
    ])->save();
}

/**
 * @param array{solicitud:SolicitudCompra,sumario:Sumario,odc:OrdenCompra} $result
 */
function printResult(array $result): void
{
    $solicitud = $result['solicitud'];
    $sumario = $result['sumario'];
    $odc = $result['odc'];

    fwrite(STDOUT, PHP_EOL . '=== ESCENARIO DE FIRMAS LISTO ===' . PHP_EOL);
    fwrite(STDOUT, 'Solicitud ID: ' . $solicitud->id . ' | Codigo: ' . (string) $solicitud->codigo_control . PHP_EOL);
    fwrite(STDOUT, '  Firmas solicitud:' . PHP_EOL);
    fwrite(STDOUT, '  - Solicitante: ' . (string) $solicitud->firma_solicitante . PHP_EOL);
    fwrite(STDOUT, '  - Almacen: ' . (string) $solicitud->firma_almacen . PHP_EOL);
    fwrite(STDOUT, '  - Aprobador: ' . (string) $solicitud->firma_aprobador . PHP_EOL);
    fwrite(STDOUT, '  - Procura: ' . (string) $solicitud->firma_receptor . PHP_EOL);
    fwrite(STDOUT, 'Sumario ID: ' . $sumario->id . ' | Correlativo: ' . (string) $sumario->correlativo_sdc . ' | Workflow: ' . (string) $sumario->workflow_estado . PHP_EOL);
    fwrite(STDOUT, '  Firmantes sumario:' . PHP_EOL);
    fwrite(STDOUT, '  - Elaborado por: ' . (string) ($sumario->elaboradoPor?->name ?? '-') . PHP_EOL);
    fwrite(STDOUT, '  - Validado por: ' . (string) ($sumario->validadoPor?->name ?? '-') . PHP_EOL);
    fwrite(STDOUT, '  - Aprobado por: ' . (string) ($sumario->decisionGerenciaPor?->name ?? '-') . PHP_EOL);
    fwrite(STDOUT, 'ODC ID: ' . $odc->id . ' | Correlativo: ' . (string) $odc->correlativo_odc . ' | Workflow: ' . (string) $odc->workflow_post_compra . PHP_EOL);
    fwrite(STDOUT, '  Firmantes ODC:' . PHP_EOL);
    fwrite(STDOUT, '  - Elaborado por: ' . (string) ($odc->elaboradoPor?->name ?? '-') . PHP_EOL);
    fwrite(STDOUT, '  - Aprobado por: ' . (string) ($odc->aprobadoPor?->name ?? '-') . PHP_EOL);
    fwrite(STDOUT, PHP_EOL . 'Verifica en UI:' . PHP_EOL);
    fwrite(STDOUT, '- Solicitud de compra: formato/PDF y detalle de firmas.' . PHP_EOL);
    fwrite(STDOUT, '- Sumarios: historial o detalle del sumario para firmas de procura, validacion y gerencia.' . PHP_EOL);
    fwrite(STDOUT, '- Ordenes de compra: historial ODC o formato PDF para firmas de elaborado/aprobado.' . PHP_EOL);
}