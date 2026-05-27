# Documentacion breve de scripts

Este documento resume para que sirve cada script disponible actualmente en la carpeta `scripts`.

## Scripts principales

### `scripts/cargar_almacen_prueba.php`
Wrapper del comando `inventario:stress-test`.
Sirve para poblar inventario de prueba con productos, entradas y salidas en lote para pruebas de almacen, dashboards y rendimiento.

### `scripts/generar_aprobaciones_procura_prueba.php`
Alias directo de `generar_solicitudes_compra_prueba.php`.
Sirve para generar solicitudes de compra de prueba orientadas a la bandeja de aprobaciones/revision en compras.

### `scripts/generar_demo_documentacion.php`
Script integral de demo documental.
Sirve para poblar en una sola corrida solicitudes, sumarios, ODC, tickets, retiros diarios, inventario y varios estados de flujo para capturas, manuales y demostraciones funcionales.

### `scripts/generar_historial_firmas_procura_prueba.php`
Genera un escenario completo con firmas visibles en solicitud, sumario y ODC.
Sirve para probar historial documental, firmas registradas y vistas imprimibles o de auditoria.

### `scripts/generar_odc_flujo_prueba.php`
Genera una ODC de prueba y la deja en un estado concreto del flujo post-compra.
Sirve para poblar bandejas de validacion, aprobacion, pagos, recepcion, transicion, conformidad, administracion y correcciones segun el `stage` indicado.

### `scripts/generar_solicitudes_compra_prueba.php`
Genera solicitudes de compra con items de prueba.
Sirve para poblar la bandeja de aprobaciones de compra y simular solicitudes listas para revision por almacen, aprobador o procura segun los usuarios que se indiquen.

### `scripts/generar_solicitud_trazabilidad_prueba.php`
Genera una solicitud con items en distintos puntos del flujo.
Sirve para demostrar trazabilidad: item sin procesar, item en sumario, item en ODC, entrega parcial y entrega completa dentro de una misma solicitud.

### `scripts/generar_sumario_creacion_odc_prueba.php`
Genera un sumario aprobado y pendiente de crear ODC.
Sirve para poblar el modulo `Ordenes de Compra > Creacion de ODC` con comparativas reales por proveedor.

### `scripts/generar_sumario_historial_prueba.php`
Genera sumarios historicos en distintos resultados.
Sirve para poblar el historial de sumarios con casos aprobados, ODC generada o rechazados, segun el estado que se pase por parametro.

### `scripts/generar_sumario_parcial_gerencia_prueba.php`
Genera un sumario rechazado parcialmente por gerencia.
Sirve para probar `Sumarios en correccion`, mostrando items correctos y otros que requieren ajuste o nueva decision.

### `scripts/probar_metricas_dashboards.php`
Genera datos de prueba y compara KPIs antes y despues.
Sirve para validar dashboards de Procura, Finanzas y Almacen, incluyendo detalle de metricas y carga opcional de inventario.

### `scripts/setup-libreoffice-ubuntu.sh`
Instala LibreOffice y fuentes base en Ubuntu.
Sirve para preparar entornos Linux donde se necesite conversion o generacion de documentos apoyados en LibreOffice.

## Scripts auxiliares de desarrollo

Los scripts dentro de `scripts/dev` son utilitarios temporales de apoyo tecnico. No forman parte del flujo normal de negocio.

### `scripts/dev/tmp_barcode_check.php`
Utilidad temporal para revisar o depurar comportamiento relacionado con codigos o lectura de barras.

### `scripts/dev/tmp_check_sumario_placeholders.php`
Utilidad temporal para validar placeholders o reemplazos dentro de plantillas de sumario.

### `scripts/dev/tmp_read_inventario.php`
Utilidad temporal para inspeccionar datos o lecturas rapidas del modulo de inventario.

### `scripts/dev/tmp_sumario_template_cells.php`
Utilidad temporal para revisar celdas o estructura de plantillas asociadas a sumarios.

## Recomendacion rapida

Si necesitas una sola poblacion amplia para demostraciones, usa `scripts/generar_demo_documentacion.php`.
Si necesitas poblar solo una bandeja o modulo especifico, usa el script tematico correspondiente.