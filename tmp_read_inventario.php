<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/storage/app/templates/INVENTARIO.xlsx';
$spreadsheet = IOFactory::load($file);

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    $title = $sheet->getTitle();
    echo "===== HOJA: {$title} =====\n";

    $rows = $sheet->toArray(null, true, true, false);
    $nonEmpty = [];

    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }

        $clean = array_map(function ($v) {
            $v = is_null($v) ? '' : trim((string) $v);
            return str_replace(["\r", "\n", "\t"], ' ', $v);
        }, $row);

        $isEmpty = count(array_filter($clean, fn ($v) => $v !== '')) === 0;
        if (! $isEmpty) {
            $nonEmpty[] = $clean;
        }
    }

    $limit = min(count($nonEmpty), 11);
    for ($i = 0; $i < $limit; $i++) {
        $fila = $i + 1;
        echo 'Fila ' . $fila . ': ' . implode(' | ', $nonEmpty[$i]) . "\n";
    }

    if ($limit === 0) {
        echo "(Sin filas con datos)\n";
    }

    echo "\n";
}
