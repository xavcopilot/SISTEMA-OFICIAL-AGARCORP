<?php
require 'vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('storage/app/templates/FORMATO SUM COTIZACIONES.xlsx')->getActiveSheet();
foreach ($sheet->getCoordinates() as $coord) {
    $value = $sheet->getCell($coord)->getValue();
    if ($value === null || $value === '') {
        continue;
    }
    $text = trim((string) $value);
    if ($text === '') {
        continue;
    }
    echo $coord . ': ' . $text . PHP_EOL;
}
