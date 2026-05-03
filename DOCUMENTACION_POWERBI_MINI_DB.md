# Mini DB para Actividad Power BI

Este modelo es una version resumida del flujo de compras para demostracion academica.

Objetivo:
- Explicar el proceso de punta a punta con pocas tablas.
- Facilitar la construccion de dashboards de gerencia en Power BI.
- Mantener separadas estas tablas del sistema principal usando prefijo `pbi_`.

## Tablas maestras

1. pbi_roles
- Define los roles (Solicitante, Almacen, Procura, Finanzas, Gerencia).

2. pbi_departamentos
- Define areas organizativas (Almacen, Procura, Finanzas, etc.).

3. pbi_usuarios
- Usuario con rol y departamento.

4. pbi_proveedores
- Catalogo basico de proveedores.

5. pbi_categorias_producto
- Categorias para analisis de consumo.

6. pbi_productos
- Catalogo simplificado de items comprables.

## Tablas de proceso

1. pbi_solicitudes_compra
- Inicio del flujo por parte del solicitante.

2. pbi_solicitud_items
- Detalle de productos solicitados.

3. pbi_sumarios_cotizacion
- Un sumario agrupa cotizaciones de una solicitud.
- Relacion: 1 solicitud puede tener N sumarios.

4. pbi_sumario_items
- Productos y cantidades consideradas en cada sumario.

5. pbi_ordenes_compra
- Ordenes emitidas desde un sumario.
- Relacion: 1 sumario puede tener N ordenes.

6. pbi_orden_compra_items
- Detalle de cada orden por item.

7. pbi_pagos_finanzas
- Registro de pago asociado a la orden de compra.

8. pbi_recepciones_procura
- Recepcion inicial por procura cuando llega el proveedor.

9. pbi_entregas_almacen
- Entrega a almacen para validacion fisica/administrativa.

10. pbi_revision_solicitante
- Conformidad o rechazo del solicitante.

11. pbi_documentacion_administracion
- Entrega de soportes de procura a administracion.

12. pbi_facturas_finanzas
- Control de factura en finanzas.

## Flujo resumido

Solicitante -> Sumario -> Orden de Compra -> Pago -> Recepcion Procura -> Entrega a Almacen -> Revision Solicitante -> Procura entrega documentacion a Administracion -> Factura cargada.

Nota operativa:
- Una solicitud puede cerrarse por etapas, con multiples sumarios y multiples ordenes, hasta completar la cantidad total requerida.

## KPIs sugeridos por area

Solicitante:
- Monto solicitado por periodo.
- Cantidad de solicitudes por prioridad.
- Tiempo promedio solicitud a aprobacion almacen.

Almacen:
- Porcentaje de entregas conformes.
- Tiempo procura a almacen.
- Ordenes en transicion pendientes de revision solicitante.

Procura:
- Tiempo promedio solicitud a sumario.
- Tiempo promedio sumario a orden emitida.
- Monto comprado por proveedor.
- Ordenes emitidas por estado y por sumario.

Finanzas:
- Pagos realizados por mes.
- Tiempo promedio OC a pago.
- Facturas cargadas pendientes por documentacion.

Gerencia:
- Lead time end-to-end (solicitud a factura cargada).
- Top proveedores por monto.
- Top categorias por gasto.
- % solicitudes completadas vs abiertas.
- % cumplimiento SLA por etapa.

## Nota de uso

Esta mini DB es para exposicion Power BI. No reemplaza ni debe mezclarse con el modelo operativo completo del sistema.

## Seeder de prueba

Seeder creado:
- `Database\\Seeders\\PowerBiMiniFlowSeeder`

Comando sugerido:

```bash
php artisan db:seed --class=PowerBiMiniFlowSeeder
```

Que carga este seed:
- 2 solicitudes.
- 3 sumarios (dos para la misma solicitud para mostrar compra parcial).
- 4 ordenes de compra.
- Flujo con casos aceptado, rechazado y pendiente.

## Conexion a Power BI (MySQL)

1. Abrir Power BI Desktop.
2. Obtener datos -> MySQL database.
3. Servidor: `127.0.0.1:3306` (o el host de Laragon que uses).
4. Base de datos: la configurada en tu `.env` (`DB_DATABASE`).
5. Modo: Import.
6. Seleccionar tablas `pbi_*`.
7. Cargar.

Relaciones recomendadas en el modelo:
- `pbi_solicitudes_compra[id]` -> `pbi_sumarios_cotizacion[solicitud_compra_id]`
- `pbi_sumarios_cotizacion[id]` -> `pbi_ordenes_compra[sumario_cotizacion_id]`
- `pbi_ordenes_compra[id]` -> `pbi_pagos_finanzas[orden_compra_id]`
- `pbi_ordenes_compra[id]` -> `pbi_recepciones_procura[orden_compra_id]`
- `pbi_ordenes_compra[id]` -> `pbi_entregas_almacen[orden_compra_id]`
- `pbi_ordenes_compra[id]` -> `pbi_revision_solicitante[orden_compra_id]`
- `pbi_ordenes_compra[id]` -> `pbi_documentacion_administracion[orden_compra_id]`
- `pbi_ordenes_compra[id]` -> `pbi_facturas_finanzas[orden_compra_id]`

## KPIs base (medidas DAX)

```DAX
Monto OC = SUM ( pbi_ordenes_compra[monto_total] )

Monto Pagado = SUM ( pbi_pagos_finanzas[monto_pagado] )

Solicitudes Totales = COUNTROWS ( pbi_solicitudes_compra )

Solicitudes Completadas =
CALCULATE (
	COUNTROWS ( pbi_solicitudes_compra ),
	pbi_solicitudes_compra[estado] = "COMPLETADA"
)

% Solicitudes Completadas =
DIVIDE ( [Solicitudes Completadas], [Solicitudes Totales], 0 )

Ordenes Totales = COUNTROWS ( pbi_ordenes_compra )

Ordenes Pagadas =
CALCULATE (
	COUNTROWS ( pbi_pagos_finanzas ),
	pbi_pagos_finanzas[estado_pago] = "PAGADO"
)

% Ordenes Pagadas = DIVIDE ( [Ordenes Pagadas], [Ordenes Totales], 0 )

Lead Time Solicitud a OC (dias) =
AVERAGEX (
	pbi_ordenes_compra,
	DATEDIFF (
		RELATED ( pbi_sumarios_cotizacion[fecha_sumario] ),
		pbi_ordenes_compra[fecha_emision],
		DAY
	)
)

Lead Time OC a Pago (dias) =
AVERAGEX (
	FILTER ( pbi_pagos_finanzas, NOT ISBLANK ( pbi_pagos_finanzas[fecha_pago] ) ),
	DATEDIFF (
		RELATED ( pbi_ordenes_compra[fecha_emision] ),
		pbi_pagos_finanzas[fecha_pago],
		DAY
	)
)

Lead Time E2E Solicitud a Factura (dias) =
AVERAGEX (
	pbi_facturas_finanzas,
	DATEDIFF (
		RELATED ( pbi_solicitudes_compra[fecha_solicitud] ),
		pbi_facturas_finanzas[fecha_carga_factura],
		DAY
	)
)

% Conformidad Solicitante =
DIVIDE (
	CALCULATE (
		COUNTROWS ( pbi_revision_solicitante ),
		pbi_revision_solicitante[decision] = "ACEPTADO"
	),
	COUNTROWS ( pbi_revision_solicitante ),
	0
)
```

Nota de modelado DAX:
- Si `RELATED` da error en alguna medida, revisa que la relacion entre tablas este activa y con direccion correcta.
