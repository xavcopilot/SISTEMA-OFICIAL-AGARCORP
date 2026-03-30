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

## Firmas PNG en PDF/Excel

- Si el usuario firma subiendo un PNG, el sistema busca las celdas que contengan los placeholders de firma.
- En esas celdas elimina el texto del placeholder y coloca la imagen PNG encima.
- Si no hay PNG cargado y solo existe una firma logica, el bloque de firma queda sin imagen en la planilla.
- Recomendacion: deja cada placeholder de firma en una celda dedicada dentro del recuadro visual de firma.
- Fecha receptor: `{{fecha_receptor}}`

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

## Placeholders oficiales - Formato Entrada Material

Este bloque aplica al archivo:

- `storage/app/templates/FORMATO ENTRADA MATERIAL.xlsx`

### Placeholders globales (encabezado / resumen)

- `movimiento_id`
- `nro_control`
- `tipo`
- `fecha` (formato `d/m/Y`)
- `almacenista` (responsable de almacen seleccionado)
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
- {{almacenista}}
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
