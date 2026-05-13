# Placeholders oficiales - Planilla de Formato de Compra

Este documento define los placeholders que reconoce el generador de la planilla en:

- `app/Http/Controllers/SolicitudCompraFormatoController.php`

## Formatos de placeholder soportados

Puedes usar cualquiera de estas variantes para cada token:

- `{{token}}`
- `[[token]]`
- `{token}`
- `%token%`
- `__token__`

## Placeholders globales (encabezado / pie)

- `codigo_control`
- `codigo_control_procura`
- `fecha_solicitud` (formato `d/m/Y`)
- `prioridad_alta` (marca `X` si aplica)
- `prioridad_media` (marca `X` si aplica)
- `prioridad_baja` (marca `X` si aplica)
- `prioridad_alta_x` (alias de `prioridad_alta`)
- `prioridad_media_x` (alias de `prioridad_media`)
- `prioridad_baja_x` (alias de `prioridad_baja`)
- `departamento_solicitante`
- `para_ser_usado_en`
- `para_ser_usado_en_1` (primeros 90 caracteres)
- `para_ser_usado_en_2` (resto del texto)
- `para_uso_linea1` (alias de `para_ser_usado_en_1`)
- `para_uso_linea2` (alias de `para_ser_usado_en_2`)
- `centro`
- `elemento`
- `cuenta`
- `contrato`
- `solicitado_por`
- `por_almacen`
- `aprobado_por`
- `recibido_por`
- `cargo_solicitante`
- `cargo_almacen`
- `cargo_aprobador`
- `cargo_receptor`
- `firma_solicitante` (si existe PNG firmado, se incrusta la imagen sobre esa celda)
- `firma_almacen` (si existe PNG firmado, se incrusta la imagen sobre esa celda)
- `firma_aprobador` (si existe PNG firmado, se incrusta la imagen sobre esa celda)
- `firma_receptor` (si existe PNG firmado, se incrusta la imagen sobre esa celda)
- `fecha_solicitante` (formato `d/m/Y`)
- `fecha_almacen` (formato `d/m/Y`)
- `fecha_aprobador` (formato `d/m/Y`)
- `fecha_receptor` (formato `d/m/Y`)
- `hora_receptor` (formato `H:i:s`)
- `hora` (alias de `hora_receptor`)

## Placeholders de detalle (items)

Debes colocar estos placeholders en una sola fila plantilla del detalle.
Esa fila será detectada y repetida automáticamente para cada item.

- `item`
- `item_n` (alias de `item`)
- `descripcion`
- `item_descripcion` (alias de `descripcion`)
- `unidad_medida`
- `item_und` (alias de `unidad_medida`)
- `cantidad_solicitada`
- `item_solicitada` (alias de `cantidad_solicitada`)
- `cantidad_existencia`
- `item_existencia` (alias de `cantidad_existencia`)
- `cantidad_a_comprar`
- `item_a_comprar` (alias de `cantidad_a_comprar`)

## Recomendación de uso en el Excel

1. En el área de encabezado y pie, reemplaza textos fijos por placeholders globales.
2. En la tabla de detalle, deja una sola fila con placeholders de item.
3. Mantén el formato visual (bordes, alturas, fuentes) en esa fila plantilla.
4. Evita mezclar placeholders de item en varias filas para no duplicar secciones incorrectas.

## Mapeo rápido para bloque de firmas

- Solicitado por: `{{solicitado_por}}`
- Cargo solicitante: `{{cargo_solicitante}}`
- Firma solicitante: usar `{{firma_solicitante}}` para que el sistema coloque allí el PNG si existe
- Fecha solicitante: `{{fecha_solicitante}}`

- Por almacén: `{{por_almacen}}`
- Cargo almacén: `{{cargo_almacen}}`
- Firma almacén: usar `{{firma_almacen}}` para que el sistema coloque allí el PNG si existe
- Fecha almacén: `{{fecha_almacen}}`

- Aprobado por: `{{aprobado_por}}`
- Cargo aprobador: `{{cargo_aprobador}}`
- Firma aprobador: usar `{{firma_aprobador}}` para que el sistema coloque allí el PNG si existe
- Fecha aprobador: `{{fecha_aprobador}}`

- Recibido por: `{{recibido_por}}`
- Cargo receptor: `{{cargo_receptor}}`
- Firma receptor: usar `{{firma_receptor}}` para que el sistema coloque allí el PNG si existe
- Hora receptor: `{{hora_receptor}}`

## Firmas PNG en PDF/Excel

- Si el usuario firma subiendo un PNG, el sistema busca las celdas que contengan los placeholders de firma.
- En esas celdas elimina el texto del placeholder y coloca la imagen PNG encima.
- Si no hay PNG cargado y solo existe una firma logica, el bloque de firma queda sin imagen en la planilla.
- Recomendacion: deja cada placeholder de firma en una celda dedicada dentro del recuadro visual de firma.
- Fecha receptor: `{{fecha_receptor}}`
- Hora receptor: `{{hora_receptor}}`

## Nota importante

El sistema ahora genera la planilla únicamente con placeholders.
Si un dato no aparece, revisa que el token esté escrito exactamente igual.

## Límite de "Para ser usado en"

- `para_uso_linea1` y `para_uso_linea2` usan corte automático por palabra.
- Límite actual: 80 caracteres por línea.
- Si el texto supera ambas líneas, la segunda línea se recorta con `…`.

Uso recomendado en plantilla:

- Para línea 1: `{{para_uso_linea1}}`
- Para línea 2: `{{para_uso_linea2}}`

Alias equivalentes (mismo resultado):

- `{{para_ser_usado_en_1}}` = línea 1
- `{{para_ser_usado_en_2}}` = línea 2
- `{{para_ser_usado_en}}` = texto completo (solo usar si tienes una sola línea/celda amplia)

## Placeholders para ODC

### Información de la Orden de Compra
- `correlativo_odc`: Correlativo de la orden de compra.
- `fecha_odc`: Fecha de creación de la orden de compra.
- `proveedor_nombre`: Nombre del proveedor.
- `rif_proveedor`: RIF del proveedor.
- `telefono_proveedor`: Teléfono del proveedor.
- `direccion_proveedor`: Dirección del proveedor.
- `tiempo_entrega`: Tiempo de entrega especificado en el sumario.
- `ciudad_proveedor`: Ciudad del proveedor.
- `email_proveedor`: Email del proveedor.
- `contacto_proveedor`: Contacto del proveedor.

### Montos y Totales
- `monto_exento`: Monto exento de impuestos.
- `sub_total`: Subtotal de la orden de compra.
- `iva_16`: Monto del IVA (16%).
- `gastos_adicionales`: Gastos adicionales.
- `total_general`: Total general de la orden de compra.
- `total_en_letras`: Total general en letras.

### Información de la Empresa
- `empresa_razon_social`: Razón social de la empresa.
- `empresa_rif`: RIF de la empresa.
- `empresa_direccion_fiscal`: Dirección fiscal de la empresa.
- `empresa_telefono_principal`: Teléfono principal de la empresa.

### Otros Detalles
- `sitio_entrega`: Sitio de entrega de los productos.
- `condicion_pago`: Condiciones de pago.
- `comentarios`: Comentarios adicionales.
- `tasa_bcv`: Tasa de cambio del BCV.
- `departamento_solicitante`: Departamento solicitante.
- `correlativo_sdc`: Correlativo del sumario de compra.

### Firmas
- `firma_elaborado`: Inserta la firma PNG del usuario que elaboró.
- `elaborado_por_nombre`: Nombre de la persona que elaboró la orden.
- `elaborado_por_cargo`: Cargo de la persona que elaboró la orden.
- `firma_aprobado`: Inserta la firma PNG del usuario que aprobó de Gerencia Finanzas.
- `aprobado_por_nombre`: Nombre de la persona que aprobó la orden.
- `aprobado_por_cargo`: Cargo de la persona que aprobó la orden.

### Placeholders de detalle (items)

Coloca estos placeholders en las filas de detalle del formato ODC. Cada fila con placeholders de item será llenada en orden con los items de la orden de compra.

- `item`
- `item_n` (alias de `item`)
- `descripcion`
- `item_descripcion` (alias de `descripcion`)
- `unidad_medida`
- `item_unidad_medida` (alias de `unidad_medida`)
- `cantidad`
- `item_cantidad` (alias de `cantidad`)
- `precio_unitario`
- `item_precio_unitario` (alias de `precio_unitario`)
- `precio_total`
- `item_precio_total` (alias de `precio_total`)

## Nota para detalle ODC

Si tu plantilla tiene una columna llamada `CODIGO`, hoy el formato ODC no expone un campo de código o SKU propio por item.

Por ahora, en esa columna debes usar:

- `{{item}}`

Y en montos usa exactamente estos tokens:

- `{{monto_exento}}`
- `{{precio_unitario}}`
- `{{precio_total}}`

No uses placeholders con espacios como `{{monto exento}}` porque no serán reconocidos.

## Bloque copiable - Formato ODC (con llaves)

Este bloque es solo para copiar y pegar directamente en la plantilla.

### Globales

- {{correlativo_odc}}
- {{fecha_odc}}
- {{proveedor_nombre}}
- {{rif_proveedor}}
- {{telefono_proveedor}}
- {{direccion_proveedor}}
- {{tiempo_entrega}}
- {{ciudad_proveedor}}
- {{email_proveedor}}
- {{contacto_proveedor}}
- {{monto_exento}}
- {{sub_total}}
- {{iva_16}}
- {{gastos_adicionales}}
- {{total_general}}
- {{total_en_letras}}
- {{empresa_razon_social}}
- {{empresa_rif}}
- {{empresa_direccion_fiscal}}
- {{empresa_telefono_principal}}
- {{sitio_entrega}}
- {{condicion_pago}}
- {{comentarios}}
- {{tasa_bcv}}
- {{departamento_solicitante}}
- {{correlativo_sdc}}
- {{firma_elaborado}}
- {{elaborado_por_nombre}}
- {{elaborado_por_cargo}}
- {{firma_aprobado}}
- {{aprobado_por_nombre}}
- {{aprobado_por_cargo}}

### Detalle

- {{item}}
- {{item_n}}
- {{descripcion}}
- {{item_descripcion}}
- {{unidad_medida}}
- {{item_unidad_medida}}
- {{cantidad}}
- {{item_cantidad}}
- {{precio_unitario}}
- {{item_precio_unitario}}
- {{precio_total}}
- {{item_precio_total}}

## Placeholders oficiales - Formato Entrada Material

Este bloque aplica al archivo:

- `storage/app/templates/FORMATO ENTRADA MATERIAL.xlsx`

### Placeholders globales (encabezado / resumen)

- `movimiento_id`
- `nro_control`
- `tipo`
- `fecha` (formato `d/m/Y`)
- `entregado_por`
- `almacenista` (responsable de almacen seleccionado)
- `firma_almacen` (si el almacenista tiene PNG de firma cargado, se incrusta la misma firma usada en formato de compra)
- `recibido_por` (alias visual de `almacenista`)
- `firma_entregado` (si el usuario entregado por tiene PNG de firma cargado, se incrusta su firma)
- `creado_por` (usuario que registro el movimiento)
- `orden_compra`
- `nro_solicitud`
- `factura_nota`
- `nro_doc_legal`
- `proveedor`
- `comentarios`
- `total_items`
- `total_cantidad`
- `total_monto`

### Placeholders de detalle (items)

Coloca estos placeholders en las filas de detalle de productos. Cada fila con placeholders de item sera llenada en orden con los productos de la entrada.

- `item`
- `item_n` (alias de `item`)
- `sku`
- `codigo` (usa `cod_ingreso` del producto)
- `descripcion`
- `marca`
- `categoria`
- `subcategoria`
- `serial`
- `estado`
- `medida`
- `cantidad`
- `precio`
- `subtotal`
- `ubicacion`
- `dpto_responsable`

## Bloque copiable - Formato Entrada Material (con llaves)

Este bloque es solo para copiar y pegar directamente en la plantilla.

### Globales

- {{movimiento_id}}
- {{nro_control}}
- {{tipo}}
- {{fecha}}
- {{entregado_por}}
- {{almacenista}}
- {{firma_almacen}}
- {{recibido_por}}
- {{firma_entregado}}
- {{creado_por}}
- {{orden_compra}}
- {{nro_solicitud}}
- {{factura_nota}}
- {{nro_doc_legal}}
- {{proveedor}}
- {{comentarios}}
- {{total_items}}
- {{total_cantidad}}
- {{total_monto}}

### Detalle

- {{item}}
- {{item_n}}
- {{sku}}
- {{codigo}}
- {{descripcion}}
- {{marca}}
- {{categoria}}
- {{subcategoria}}
- {{serial}}
- {{estado}}
- {{medida}}
- {{cantidad}}
- {{precio}}
- {{subtotal}}
- {{ubicacion}}
- {{dpto_responsable}}

## Placeholders oficiales - Formato Salida Material

Este bloque aplica al archivo:

- `storage/app/templates/FORMATO SALIDA MATERIAL.xlsx`

### Placeholders globales (encabezado / resumen)

- `movimiento_id`
- `nro_control`
- `tipo`
- `fecha` (formato `d/m/Y`)
- `almacenista` (quien entrega)
- `creado_por` (usuario que registro el movimiento)
- `responsable_destino`
- `dpto_destino`
- `comentarios`
- `total_items`
- `total_cantidad`
- `total_monto`

### Placeholders de detalle (items)

Coloca estos placeholders en las filas de detalle del formato. Cada fila con placeholders de item sera llenada en orden con los productos de la salida.

- `item`
- `item_n` (alias de `item`)
- `sku`
- `codigo` (usa `cod_ingreso` del producto)
- `descripcion`
- `marca`
- `categoria`
- `subcategoria`
- `serial`
- `estado`
- `medida`
- `cantidad`
- `precio`
- `subtotal`
- `ubicacion`
- `dpto_responsable`
- `estado_nuevo_x` (coloca `X` si el estado del producto es `NUEVO`)
- `estado_usado_x` (coloca `X` si el estado del producto es `USADO`)
- `retorna`
- `observaciones_item`

## Bloque copiable - Formato Salida Material (con llaves)

Este bloque es solo para copiar y pegar directamente en la plantilla.

### Globales

- {{movimiento_id}}
- {{nro_control}}
- {{tipo}}
- {{fecha}}
- {{almacenista}}
- {{creado_por}}
- {{responsable_destino}}
- {{dpto_destino}}
- {{comentarios}}
- {{total_items}}
- {{total_cantidad}}
- {{total_monto}}

### Detalle

- {{item}}
- {{item_n}}
- {{sku}}
- {{codigo}}
- {{descripcion}}
- {{marca}}
- {{categoria}}
- {{subcategoria}}
- {{serial}}
- {{estado}}
- {{medida}}
- {{cantidad}}
- {{precio}}
- {{subtotal}}
- {{ubicacion}}
- {{dpto_responsable}}
- {{estado_nuevo_x}}
- {{estado_usado_x}}
- {{retorna}}
- {{observaciones_item}}

## Nota para Formato Salida Material

Estos campos no existen hoy como datos propios del movimiento de salida, por lo que no tienen placeholder oficial mientras no se agreguen al formulario o al modelo:

- `frente`
- `proyecto`
- `contrato`
- `placa`
- `conductor`
- `autorizado_por`
- `despachado_por`

Si alguno de esos campos debe salir en la plantilla, primero hay que incorporarlo al flujo de captura o definir una regla de negocio para derivarlo.

## Placeholders oficiales - Formato SUM COTIZACIONES

Este bloque aplica al archivo:

- `storage/app/templates/FORMATO SUM COTIZACIONES.xlsx`

Nota: este formato ahora funciona en modo estricto por placeholders.
Si falta alguno de los placeholders requeridos, el sistema respondera con error 422 y listara los tokens faltantes.

Tokens opcionales de informacion impresa (no son requeridos y aplican tambien a ODC):

- `empresa_razon_social` (alias: `empresa_nombre`)
- `empresa_rif`
- `empresa_direccion_fiscal` (alias: `empresa_direccion`)
- `empresa_telefono_principal` (alias: `empresa_telefono`)

Estos valores se administran desde el modulo de escritorio `Informacion AGARCORP` (acceso A.I.T).

Formatos soportados de token:

- `{{token}}`
- `[[token]]`
- `{token}`
- `%token%`
- `__token__`

### Placeholders globales (encabezado / pie)

- `sumario_numero`
- `correlativo_sdc` (alias opcional)
- `fecha_sumario` (formato `d/m/Y`)
- `departamento_solicitante`
- `procedencia_local` (ejemplo generado: `Local ■`)
- `procedencia_importado` (ejemplo generado: `Importado □`)
- `tipo_orden_compra` (ejemplo generado: `■ COMPRA`)
- `tipo_orden_servicios` (ejemplo generado: `□ SERVICIOS`)
- `proveedor_1_nombre`
- `proveedor_2_nombre`
- `proveedor_3_nombre`
- `condiciones_pago_1`
- `condiciones_pago_2`
- `condiciones_pago_3`
- `tiempo_entrega_1`
- `tiempo_entrega_2`
- `tiempo_entrega_3`
- `total_compra_prov1`
- `total_compra_prov2`
- `total_compra_prov3`
- `observaciones`
- `prioridad_mejor_precio` (ejemplo generado: `MEJOR PRECIO ■`)
- `prioridad_mejor_servicio` (ejemplo generado: `MEJOR SERVICIO/CALIDAD □`)
- `firma_elaborado` (inserta la firma PNG del elaborador)
- `firma_aprobado` (inserta la firma PNG de Gerencia de Finanzas)
- `firma_revisado` (inserta la firma PNG del Validador de Finanzas)
- `elaborado_por_nombre`
- `elaborado_por_cargo`
- `elaborado_fecha` (formato `d/m/Y`)
- `revisado_por_nombre`
- `revisado_por_cargo`
- `revisado_fecha` (formato `d/m/Y`)

### Placeholders de detalle (items)

Coloca estos placeholders en una sola fila plantilla del detalle.
Esa fila se repetira por cada item del sumario.

- `item`
- `descripcion`
- `unidad_medida`
- `cantidad`

- `marca_prov1`
- `precio_unitario_prov1`
- `precio_total_prov1`

- `marca_prov2`
- `precio_unitario_prov2`
- `precio_total_prov2`

- `marca_prov3`
- `precio_unitario_prov3`
- `precio_total_prov3`

## Bloque copiable - Formato SUM COTIZACIONES (con llaves)

### Globales

- {{sumario_numero}}
- {{correlativo_sdc}}
- {{fecha_sumario}}
- {{departamento_solicitante}}
- {{procedencia_local}}
- {{procedencia_importado}}
- {{tipo_orden_compra}}
- {{tipo_orden_servicios}}
- {{proveedor_1_nombre}}
- {{proveedor_2_nombre}}
- {{proveedor_3_nombre}}
- {{condiciones_pago_1}}
- {{condiciones_pago_2}}
- {{condiciones_pago_3}}
- {{tiempo_entrega_1}}
- {{tiempo_entrega_2}}
- {{tiempo_entrega_3}}
- {{total_compra_prov1}}
- {{total_compra_prov2}}
- {{total_compra_prov3}}
- {{observaciones}}
- {{prioridad_mejor_precio}}
- {{prioridad_mejor_servicio}}
- {{firma_elaborado}}
- {{firma_aprobado}}
- {{firma_revisado}}
- {{elaborado_por_nombre}}
- {{elaborado_por_cargo}}
- {{elaborado_fecha}}
- {{revisado_por_nombre}}
- {{revisado_por_cargo}}
- {{revisado_fecha}}

### Detalle

- {{item}}
- {{descripcion}}
- {{unidad_medida}}
- {{cantidad}}
- {{marca_prov1}}
- {{precio_unitario_prov1}}
- {{precio_total_prov1}}
- {{marca_prov2}}
- {{precio_unitario_prov2}}
- {{precio_total_prov2}}
- {{marca_prov3}}
- {{precio_unitario_prov3}}
- {{precio_total_prov3}}

## Recomendacion rapida para empezar en tu Excel de Sumario

1. Encabezado:
	- Reemplaza `Sumario N°` por `{{sumario_numero}}`.
	- Reemplaza `Fecha` por `{{fecha_sumario}}`.
	- Reemplaza departamento por `{{departamento_solicitante}}`.

2. Procedencia y tipo:
	- Usa `{{procedencia_local}}` y `{{procedencia_importado}}` en sus dos lineas.
	- Usa `{{tipo_orden_compra}}` y `{{tipo_orden_servicios}}` para las casillas del bloque tipo de orden.

3. Proveedores y condiciones:
	- Encabezados de proveedor: `{{proveedor_1_nombre}}`, `{{proveedor_2_nombre}}`, `{{proveedor_3_nombre}}`.
	- Condiciones y entrega por columna: `{{condiciones_pago_1..3}}` y `{{tiempo_entrega_1..3}}`.

4. Fila plantilla de items (una sola fila):
	- Coloca ahi todos los placeholders de detalle.
	- El sistema detecta esa fila y la repite automaticamente.

5. Pie:
	- Observaciones: `{{observaciones}}`.
	- Prioridad: `{{prioridad_mejor_precio}}` y `{{prioridad_mejor_servicio}}`.
	- Firmas/cargos/fechas: `{{elaborado_por_*}}` y `{{revisado_por_*}}`.
