<?php
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$template = $root . '/storage/app/templates/FORMATO SUM COTIZACIONES.xlsx';
$sheet = IOFactory::load($template)->getActiveSheet();

$required = [
  'sumario_numero','fecha_sumario','departamento_solicitante','procedencia_local','procedencia_importado',
  'tipo_orden_compra','tipo_orden_servicios','proveedor_1_nombre','proveedor_2_nombre','proveedor_3_nombre',
  'condiciones_pago_1','condiciones_pago_2','condiciones_pago_3','tiempo_entrega_1','tiempo_entrega_2','tiempo_entrega_3',
  'total_compra_prov1','total_compra_prov2','total_compra_prov3','observaciones','prioridad_mejor_precio','prioridad_mejor_servicio',
  'elaborado_por_nombre','elaborado_por_cargo','elaborado_fecha','revisado_por_nombre','revisado_por_cargo','revisado_fecha',
  'item','item_n','descripcion','item_descripcion','unidad_medida','item_unidad_medida','cantidad','item_cantidad',
  'marca_prov1','precio_unitario_prov1','precio_total_prov1','marca_prov2','precio_unitario_prov2','precio_total_prov2',
  'marca_prov3','precio_unitario_prov3','precio_total_prov3'
];

$pending = array_fill_keys($required, true);
$highestRow = $sheet->getHighestRow();
$highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

$contains = function(string $value, string $token): bool {
  $q = preg_quote($token, '/');
  return (bool) preg_match('/(\{\{\s*'.$q.'\s*\}\}|\[\[\s*'.$q.'\s*\]\]|\{\s*'.$q.'\s*\}|%\s*'.$q.'\s*%|__\s*'.$q.'\s*__)/u', $value);
};

for ($r=1; $r<=$highestRow; $r++) {
  for ($c=1; $c<=$highestCol; $c++) {
    $v = (string) $sheet->getCellByColumnAndRow($c,$r)->getValue();
    if ($v === '' || empty($pending)) continue;
    foreach (array_keys($pending) as $t) {
      if ($contains($v, $t)) unset($pending[$t]);
    }
    if (empty($pending)) break 2;
  }
}

echo "FALTANTES (" . count($pending) . ")" . PHP_EOL;
foreach (array_keys($pending) as $t) echo "- $t" . PHP_EOL;
