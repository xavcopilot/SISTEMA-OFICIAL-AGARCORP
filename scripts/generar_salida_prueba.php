<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$source = __DIR__ . '/../storage/app/templates/INVENTARIO.xlsx';
$target = __DIR__ . '/../storage/app/templates/INVENTARIO_FLUJO.xlsx';

$spreadsheet = IOFactory::load($source);
$inventario = $spreadsheet->getSheetByName('INVENTARIO');
$salida = $spreadsheet->getSheetByName('SALIDA');

if (! $inventario || ! $salida) {
    throw new RuntimeException('No se encontraron hojas INVENTARIO o SALIDA');
}

// Limpia filas de prueba previas (A:R desde fila 3)
for ($row = 3; $row <= 200; $row++) {
    for ($col = 1; $col <= 18; $col++) {
        $salida->setCellValueByColumnAndRow($col, $row, null);
    }
}

$targetRow = 3;

for ($i = 0; $i < 10; $i++) {
    $invRow = 2 + $i;

    $sku = trim((string) $inventario->getCell('C' . $invRow)->getValue());
    if ($sku === '') {
        continue;
    }

    $descripcion = trim((string) $inventario->getCell('D' . $invRow)->getValue());
    $marca = trim((string) $inventario->getCell('E' . $invRow)->getValue());
    $categoria = trim((string) $inventario->getCell('F' . $invRow)->getValue());
    $subcat = trim((string) $inventario->getCell('G' . $invRow)->getValue());
    $serial = trim((string) $inventario->getCell('H' . $invRow)->getValue());
    $estado = trim((string) $inventario->getCell('I' . $invRow)->getValue());
    $medida = trim((string) $inventario->getCell('J' . $invRow)->getValue());
    $ubicacion = trim((string) $inventario->getCell('K' . $invRow)->getValue());

    $control = $i < 5 ? 'SAL-PRUEBA-0001' : 'SAL-PRUEBA-0002';

    $salida->setCellValue('A' . $targetRow, $control);
    $salida->setCellValue('B' . $targetRow, '24/03/2026');
    $salida->setCellValue('C' . $targetRow, 'March');
    $salida->setCellValue('D' . $targetRow, $i < 5 ? 'Carlos Perez' : 'Ana Rivas');
    $salida->setCellValue('E' . $targetRow, $i < 5 ? 'OPERACIONES' : 'AIT');
    $salida->setCellValue('F' . $targetRow, 'Daniela Carrasco');
    $salida->setCellValue('G' . $targetRow, $sku);
    $salida->setCellValue('H' . $targetRow, $descripcion !== '' ? $descripcion : 'SIN DESCRIPCION');
    $salida->setCellValue('I' . $targetRow, $marca !== '' ? $marca : 'N/A');
    $salida->setCellValue('J' . $targetRow, $categoria !== '' ? $categoria : 'SIN_CATEGORIA');
    $salida->setCellValue('K' . $targetRow, $subcat !== '' ? $subcat : 'SIN SUBCATEGORIA');
    $salida->setCellValue('L' . $targetRow, $serial !== '' ? $serial : 'N/A');
    $salida->setCellValue('M' . $targetRow, $estado !== '' ? $estado : 'NUEVO');
    $salida->setCellValue('N' . $targetRow, $medida !== '' ? $medida : 'UND');
    $salida->setCellValue('O' . $targetRow, 1);
    $salida->setCellValue('P' . $targetRow, $ubicacion !== '' ? $ubicacion : 'SIN UBICACION');
    $salida->setCellValue('Q' . $targetRow, $i % 2 === 0 ? 'NO' : 'SI');
    $salida->setCellValue('R' . $targetRow, 'Salida de prueba para validar flujo');

    $targetRow++;
}

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->setPreCalculateFormulas(false);
$writer->save($target);

echo 'Archivo generado: ' . basename($target) . PHP_EOL;
