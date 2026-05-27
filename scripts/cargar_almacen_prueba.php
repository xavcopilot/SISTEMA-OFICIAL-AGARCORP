<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$defaults = [
    'batches' => 1,
    'productos' => 100,
    'entradas' => 100,
    'salidas' => 100,
    'max-items' => 3,
];

$callOptions = [
    '--batches' => $defaults['batches'],
    '--productos' => $defaults['productos'],
    '--entradas' => $defaults['entradas'],
    '--salidas' => $defaults['salidas'],
    '--max-items' => $defaults['max-items'],
];

foreach (array_slice($argv, 1) as $arg) {
    if (! str_starts_with($arg, '--')) {
        continue;
    }

    $raw = substr($arg, 2);

    if ($raw === 'cleanup') {
        $callOptions['--cleanup'] = true;

        continue;
    }

    if (! str_contains($raw, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $raw, 2);

    if ($key === 'user-id') {
        $callOptions['--user-id'] = max(1, (int) $value);

        continue;
    }

    if (array_key_exists($key, $defaults)) {
        $normalized = (int) $value;
        $callOptions['--' . $key] = $key === 'max-items' ? max(1, $normalized) : max(0, $normalized);
    }
}

fwrite(STDOUT, 'Ejecutando inventario:stress-test con wrapper scripts/cargar_almacen_prueba.php' . PHP_EOL);

$exitCode = $kernel->call('inventario:stress-test', $callOptions);

fwrite(STDOUT, $kernel->output());

exit($exitCode);
