<?php
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$g = new Picqer\Barcode\BarcodeGeneratorSVG();
$s = $g->getBarcode('CON-0001', $g::TYPE_CODE_128, 1.6, 42);
echo substr($s, 0, 700), PHP_EOL;
