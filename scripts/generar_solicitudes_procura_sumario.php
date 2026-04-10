<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\User;
use App\Support\SolicitudCompraFlow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

$opts = getopt('', [
    'cantidad::',
    'items::',
    'prefijo::',
    'solicitante_id::',
    'almacen_id::',
    'aprobador_id::',
    'procura_id::',
]);

$cantidad = max(1, (int) ($opts['cantidad'] ?? 3));
$itemsPorSolicitud = max(1, (int) ($opts['items'] ?? 4));
$prefijo = trim((string) ($opts['prefijo'] ?? 'SC-PROC-SUMARIO'));

$solicitanteId = resolveUserId((int) ($opts['solicitante_id'] ?? 0), fn (): ?int => User::query()->orderBy('id')->value('id'));
$almacenId = resolveUserId((int) ($opts['almacen_id'] ?? 0), fn (): ?int => SolicitudCompraFlow::defaultAlmacenUserId());
$aprobadorId = resolveUserId((int) ($opts['aprobador_id'] ?? 0), fn (): ?int => User::query()
    ->whereHas('roles', function (Builder $roleQuery): void {
        $roleQuery->whereIn('name', SolicitudCompraFlow::APPROVER_ROLES);
    })
    ->orderBy('name')
    ->value('id'));
$procuraId = resolveUserId((int) ($opts['procura_id'] ?? 0), fn (): ?int => SolicitudCompraFlow::defaultProcuraUserId());

if (! $solicitanteId || ! $almacenId || ! $aprobadorId || ! $procuraId) {
    fwrite(STDERR, "No se pudieron resolver todos los usuarios requeridos.\n");
    fwrite(STDERR, "Revisa roles o pasa IDs manuales con --solicitante_id --almacen_id --aprobador_id --procura_id\n");
    exit(1);
}

$solicitante = User::query()->with('cargo')->find($solicitanteId);
$almacen = User::query()->with('cargo')->find($almacenId);
$aprobador = User::query()->with('cargo')->find($aprobadorId);
$procura = User::query()->with('cargo')->find($procuraId);

if (! $solicitante || ! $almacen || ! $aprobador || ! $procura) {
    fwrite(STDERR, "Uno o mas usuarios no existen.\n");
    exit(1);
}

echo "Generando {$cantidad} solicitudes listas para firma de Procura..." . PHP_EOL;
echo "Solicitante: {$solicitante->id} {$solicitante->name}" . PHP_EOL;
echo "Almacen: {$almacen->id} {$almacen->name}" . PHP_EOL;
echo "Aprobador: {$aprobador->id} {$aprobador->name}" . PHP_EOL;
echo "Procura: {$procura->id} {$procura->name}" . PHP_EOL;

$createdIds = [];

for ($i = 1; $i <= $cantidad; $i++) {
    $control = sprintf('%s-%s-%03d', $prefijo, now()->format('YmdHis'), $i);

    DB::transaction(function () use (
        $control,
        $itemsPorSolicitud,
        $solicitante,
        $almacen,
        $aprobador,
        $procura,
        &$createdIds,
        $i
    ): void {
        $solicitud = SolicitudCompra::query()->create([
            'codigo_control' => $control,
            'codigo_control_procura' => 'PROC-' . now()->format('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'fecha_solicitud' => now()->toDateString(),
            'tipo_solicitud' => 'Consumo',
            'prioridad' => 'Media',
            'departamento_solicitante' => (string) ($solicitante->departamento?->nombre ?? 'PRUEBA'),
            'para_ser_usado_en' => 'Prueba de flujo hacia sumarios',
            'solicitado_por_user_id' => $solicitante->id,
            'por_almacen_user_id' => $almacen->id,
            'aprobado_por_user_id' => $aprobador->id,
            'recibido_por_user_id' => $procura->id,
            'cargo_solicitante' => (string) ($solicitante->cargo?->nombre ?? 'SOLICITANTE'),
            'cargo_almacen' => (string) ($almacen->cargo?->nombre ?? 'ALMACEN'),
            'cargo_aprobador' => (string) ($aprobador->cargo?->nombre ?? 'APROBADOR'),
            'cargo_receptor' => (string) ($procura->cargo?->nombre ?? 'PROCURA'),
            'firma_solicitante' => '__ENVIADA_TEST__',
            'firma_almacen' => '__ALMACEN_TEST__',
            'firma_aprobador' => '__APROBADOR_TEST__',
            'firma_receptor' => null,
            'fecha_solicitante' => now()->subDays(3)->toDateString(),
            'fecha_almacen' => now()->subDays(2)->toDateString(),
            'fecha_aprobador' => now()->subDay()->toDateString(),
            'fecha_receptor' => null,
            'hora_receptor' => null,
            'estado' => 'EN_ESPERA_DE_COTIZACION',
        ]);

        for ($item = 1; $item <= $itemsPorSolicitud; $item++) {
            $cantidadSolicitada = random_int(2, 8);
            $existencia = random_int(0, $cantidadSolicitada - 1);

            SolicitudCompraItem::query()->create([
                'solicitud_compra_id' => $solicitud->id,
                'item' => $item,
                'descripcion' => "ITEM PRUEBA {$i}-{$item} PARA SUMARIO",
                'unidad_medida' => 'UND',
                'cantidad_solicitada' => $cantidadSolicitada,
                'cantidad_existencia' => $existencia,
                'cantidad_a_comprar' => max(1, $cantidadSolicitada - $existencia),
                'estado_item' => 'SIN_PROCESAR',
            ]);
        }

        $createdIds[] = $solicitud->id;
    });
}

echo PHP_EOL . 'Solicitudes creadas (pendientes de firma de Procura):' . PHP_EOL;

SolicitudCompra::query()
    ->whereIn('id', $createdIds)
    ->orderBy('id')
    ->get(['id', 'codigo_control', 'fecha_aprobador', 'fecha_receptor', 'estado', 'recibido_por_user_id'])
    ->each(function (SolicitudCompra $solicitud): void {
        $line = sprintf(
            '- ID %d | %s | fecha_aprobador=%s | fecha_receptor=%s | estado=%s | procura_user_id=%s',
            $solicitud->id,
            (string) $solicitud->codigo_control,
            (string) $solicitud->fecha_aprobador,
            (string) ($solicitud->fecha_receptor ?? 'NULL'),
            (string) $solicitud->estado,
            (string) $solicitud->recibido_por_user_id
        );

        echo $line . PHP_EOL;
    });

echo PHP_EOL . 'Listo. En Aprobaciones de Compra (rol Procura) deben aparecer para firmar etapa Procura.' . PHP_EOL;
echo 'Tras firmarlas en Procura, ya puedes usarlas para crear/continuar sumarios.' . PHP_EOL;

/**
 * Resolve un ID explicito o cae en un resolvedor automatico.
 */
function resolveUserId(int $explicitId, callable $resolver): ?int
{
    if ($explicitId > 0) {
        return $explicitId;
    }

    $resolved = $resolver();

    return $resolved ? (int) $resolved : null;
}
