<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$options = getopt('', [
    'prefijo::',
    'password::',
    'cleanup',
]);

$prefix = trim((string) ($options['prefijo'] ?? 'CAPTURA-DEMO'));
$password = (string) ($options['password'] ?? 'Demo12345.');
$cleanup = array_key_exists('cleanup', $options);

if ($prefix === '') {
    fwrite(STDERR, 'El prefijo no puede estar vacio.' . PHP_EOL);
    exit(1);
}

$normalizedPrefix = Str::upper(Str::slug($prefix, '-'));

$departamentosData = [
    'Administracion',
    'Compras',
    'Logistica',
    'Tecnologia',
];

$cargosData = [
    'Analista Administrativo',
    'Coordinador de Compras',
    'Supervisor de Logistica',
    'Especialista de Soporte',
    'Jefe de Departamento',
    'Asistente Operativo',
];

$rolesData = [
    'Administrador Demo',
    'Supervisor Demo',
    'Analista Demo',
    'Consulta Demo',
];

$usersData = [
    [
        'code' => 'admin',
        'name' => 'Administrador General',
        'departamento' => 'Administracion',
        'cargo' => 'Jefe de Departamento',
        'role' => 'Administrador Demo',
    ],
    [
        'code' => 'compras',
        'name' => 'Coordinador de Compras',
        'departamento' => 'Compras',
        'cargo' => 'Coordinador de Compras',
        'role' => 'Supervisor Demo',
    ],
    [
        'code' => 'logistica',
        'name' => 'Supervisor de Logistica',
        'departamento' => 'Logistica',
        'cargo' => 'Supervisor de Logistica',
        'role' => 'Supervisor Demo',
    ],
    [
        'code' => 'soporte',
        'name' => 'Especialista de Soporte',
        'departamento' => 'Tecnologia',
        'cargo' => 'Especialista de Soporte',
        'role' => 'Analista Demo',
    ],
    [
        'code' => 'asistente1',
        'name' => 'Asistente Administrativo',
        'departamento' => 'Administracion',
        'cargo' => 'Asistente Operativo',
        'role' => 'Consulta Demo',
    ],
    [
        'code' => 'analista1',
        'name' => 'Analista de Compras',
        'departamento' => 'Compras',
        'cargo' => 'Analista Administrativo',
        'role' => 'Analista Demo',
    ],
    [
        'code' => 'analista2',
        'name' => 'Analista de Logistica',
        'departamento' => 'Logistica',
        'cargo' => 'Analista Administrativo',
        'role' => 'Analista Demo',
    ],
    [
        'code' => 'consulta1',
        'name' => 'Usuario Consulta',
        'departamento' => 'Tecnologia',
        'cargo' => 'Asistente Operativo',
        'role' => 'Consulta Demo',
    ],
];

app(PermissionRegistrar::class)->forgetCachedPermissions();

try {
    DB::transaction(function () use (
        $cleanup,
        $prefix,
        $normalizedPrefix,
        $password,
        $departamentosData,
        $cargosData,
        $rolesData,
        $usersData
    ): void {
        if ($cleanup) {
            cleanupDemoData($prefix, $normalizedPrefix);
            return;
        }

        $departamentos = [];
        foreach ($departamentosData as $name) {
            $demoName = demoLabel($prefix, $name);
            $departamento = Departamento::query()->updateOrCreate(
                ['nombre' => $demoName],
                ['nombre' => $demoName]
            );
            $departamentos[$name] = $departamento;
        }

        $cargos = [];
        foreach ($cargosData as $name) {
            $demoName = demoLabel($prefix, $name);
            $cargo = Cargo::query()->updateOrCreate(
                ['nombre' => $demoName],
                ['nombre' => $demoName]
            );
            $cargos[$name] = $cargo;
        }

        $roles = [];
        foreach ($rolesData as $name) {
            $demoName = demoLabel($prefix, $name);
            $roles[$name] = Role::findOrCreate($demoName, 'web');
        }

        foreach ($usersData as $index => $userData) {
            $email = sprintf('%s-%02d@example.test', Str::lower($normalizedPrefix), $index + 1);
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => demoLabel($prefix, $userData['name']),
                    'email' => $email,
                    'password' => $password,
                    'firma_password' => $password,
                    'withdrawal_password' => $password,
                    'departamento_id' => $departamentos[$userData['departamento']]->id,
                    'cargo_id' => $cargos[$userData['cargo']]->id,
                ]
            );

            $user->syncRoles([$roles[$userData['role']]]);
        }
    });

    if ($cleanup) {
        fwrite(STDOUT, 'Datos demo eliminados para el prefijo: ' . $prefix . PHP_EOL);
        exit(0);
    }

    fwrite(STDOUT, 'Datos demo creados o actualizados correctamente.' . PHP_EOL);
    fwrite(STDOUT, 'Prefijo de filtrado: ' . $prefix . PHP_EOL);
    fwrite(STDOUT, 'Password comun para usuarios demo: ' . $password . PHP_EOL);
    fwrite(STDOUT, 'Usuarios demo: ' . count($usersData) . PHP_EOL);
    fwrite(STDOUT, 'Roles demo: ' . count($rolesData) . PHP_EOL);
    fwrite(STDOUT, 'Departamentos demo: ' . count($departamentosData) . PHP_EOL);
    fwrite(STDOUT, 'Cargos demo: ' . count($cargosData) . PHP_EOL);
    fwrite(STDOUT, 'Usa el buscador de cada tabla con el texto: ' . $prefix . PHP_EOL);
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error generando datos demo: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function demoLabel(string $prefix, string $value): string
{
    return trim($prefix) . ' - ' . $value;
}

function cleanupDemoData(string $prefix, string $normalizedPrefix): void
{
    $emailPattern = Str::lower($normalizedPrefix) . '-%@example.test';

    $users = User::query()
        ->where('email', 'like', $emailPattern)
        ->get();

    foreach ($users as $user) {
        $user->syncRoles([]);
        $user->delete();
    }

    Role::query()
        ->where('guard_name', 'web')
        ->where('name', 'like', $prefix . ' - %')
        ->delete();

    Departamento::query()
        ->where('nombre', 'like', $prefix . ' - %')
        ->delete();

    Cargo::query()
        ->where('nombre', 'like', $prefix . ' - %')
        ->delete();
}