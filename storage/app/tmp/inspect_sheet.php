<?php

require __DIR__ . '/../../../vendor/autoload.php';

$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__ . '/../templates/PLANILLA DE FORMATO DE COMPRA.xlsx')->getActiveSheet();

echo 'HIGHEST_COLUMN=' . $sheet->getHighestColumn() . PHP_EOL;
echo 'HIGHEST_ROW=' . $sheet->getHighestRow() . PHP_EOL;
echo 'HIGHEST_DATA_COLUMN=' . $sheet->getHighestDataColumn() . PHP_EOL;
echo 'HIGHEST_DATA_ROW=' . $sheet->getHighestDataRow() . PHP_EOL;
echo 'PRINT_AREA=' . ($sheet->getPageSetup()->getPrintArea() ?: 'EMPTY') . PHP_EOL;
echo 'ORIENTATION=' . ($sheet->getPageSetup()->getOrientation() ?: 'EMPTY') . PHP_EOL;
echo 'PAPER_SIZE=' . $sheet->getPageSetup()->getPaperSize() . PHP_EOL;
