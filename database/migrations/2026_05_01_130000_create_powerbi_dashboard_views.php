<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS pbi_v_finanzas_pagos_proveedor;
DROP VIEW IF EXISTS pbi_v_finanzas_facturas_vs_documentacion;
DROP VIEW IF EXISTS pbi_v_finanzas_documentacion_carga;
DROP VIEW IF EXISTS pbi_v_finanzas_ordenes_pago_estado;
DROP VIEW IF EXISTS pbi_v_almacen_rechazos_solicitante;
DROP VIEW IF EXISTS pbi_v_almacen_transicion_pendiente;
DROP VIEW IF EXISTS pbi_v_almacen_cumplimiento_entregas;
DROP VIEW IF EXISTS pbi_v_almacen_productos_departamento;
DROP VIEW IF EXISTS pbi_v_procura_ordenes_por_sumario;
DROP VIEW IF EXISTS pbi_v_procura_sumarios_por_solicitud;
DROP VIEW IF EXISTS pbi_v_procura_tiempo_sumario_a_oc;
DROP VIEW IF EXISTS pbi_v_procura_tiempo_solicitud_a_sumario;
DROP VIEW IF EXISTS pbi_v_gerencia_monto_por_departamento;
DROP VIEW IF EXISTS pbi_v_gerencia_solicitudes_por_etapa;
DROP VIEW IF EXISTS pbi_v_gerencia_resumen;
DROP VIEW IF EXISTS pbi_v_dashboard_solicitudes;
DROP VIEW IF EXISTS pbi_v_dashboard_items;
DROP VIEW IF EXISTS pbi_v_kpi_finanzas;
DROP VIEW IF EXISTS pbi_v_kpi_almacen;
DROP VIEW IF EXISTS pbi_v_kpi_procura;
DROP VIEW IF EXISTS pbi_v_kpi_gerencia;
DROP VIEW IF EXISTS pbi_v_kpis_resumen;
DROP VIEW IF EXISTS pbi_v_dashboard_ordenes;

CREATE VIEW pbi_v_dashboard_ordenes AS
SELECT
    oc.id AS orden_id,
    oc.codigo AS orden_codigo,
    oc.estado AS estado_orden,
    oc.fecha_emision,
    oc.fecha_compromiso_entrega,
    oc.fecha_entrega_real,
    oc.monto_subtotal,
    oc.monto_impuestos,
    oc.monto_total,
    su.id AS sumario_id,
    su.codigo AS sumario_codigo,
    su.fecha_sumario,
    su.estado AS sumario_estado,
    su.monto_referencial_total,
    sc.id AS solicitud_id,
    sc.codigo AS solicitud_codigo,
    sc.fecha_solicitud,
    sc.prioridad AS solicitud_prioridad,
    sc.estado AS solicitud_estado,
    sc.monto_estimado_total,
    dep.id AS departamento_solicitante_id,
    dep.nombre AS departamento_solicitante,
    solicitante.id AS solicitante_id,
    solicitante.nombre AS solicitante_nombre,
    pr.id AS proveedor_id,
    pr.nombre AS proveedor_nombre,
    pr.categoria AS proveedor_categoria,
    pa.id AS pago_id,
    pa.estado_pago,
    pa.fecha_programada_pago,
    pa.fecha_pago,
    pa.monto_pagado,
    pa.metodo_pago,
    rp.id AS recepcion_procura_id,
    rp.fecha_recepcion_procura,
    rp.estado_recepcion_procura,
    ea.id AS entrega_almacen_id,
    ea.fecha_entrega_almacen,
    ea.estado_entrega_almacen,
    ea.porcentaje_cumplimiento,
    rs.id AS revision_id,
    rs.fecha_revision,
    rs.decision AS decision_solicitante,
    rs.motivo_rechazo,
    da.id AS documentacion_id,
    da.fecha_entrega_administracion,
    da.estado_documentacion,
    ff.id AS factura_id,
    ff.numero_factura,
    ff.fecha_factura,
    ff.fecha_recepcion_finanzas,
    ff.fecha_carga_factura,
    ff.estado_finanzas,
    ff.monto_total AS factura_monto_total,
    CASE WHEN pa.fecha_pago IS NOT NULL THEN 1 ELSE 0 END AS flag_pagada,
    CASE WHEN ff.fecha_carga_factura IS NOT NULL THEN 1 ELSE 0 END AS flag_factura_cargada,
    CASE WHEN rs.decision = 'ACEPTADO' THEN 1 ELSE 0 END AS flag_aceptada,
    CASE WHEN rs.decision = 'RECHAZADO' THEN 1 ELSE 0 END AS flag_rechazada,
    CASE
        WHEN pa.fecha_pago IS NOT NULL THEN (pa.fecha_pago - oc.fecha_emision)
        ELSE NULL
    END AS dias_oc_a_pago,
    CASE
        WHEN su.fecha_sumario IS NOT NULL THEN (su.fecha_sumario - sc.fecha_solicitud)
        ELSE NULL
    END AS dias_solicitud_a_sumario,
    CASE
        WHEN oc.fecha_emision IS NOT NULL THEN (oc.fecha_emision - su.fecha_sumario)
        ELSE NULL
    END AS dias_sumario_a_oc,
    CASE
        WHEN ff.fecha_carga_factura IS NOT NULL THEN (ff.fecha_carga_factura - sc.fecha_solicitud)
        ELSE NULL
    END AS dias_solicitud_a_factura_cargada,
    CASE
        WHEN da.fecha_entrega_administracion IS NOT NULL AND ff.fecha_carga_factura IS NOT NULL
            THEN (ff.fecha_carga_factura - da.fecha_entrega_administracion)
        ELSE NULL
    END AS dias_documentacion_a_factura,
    CASE
        WHEN oc.fecha_entrega_real IS NOT NULL
            AND oc.fecha_compromiso_entrega IS NOT NULL
            AND oc.fecha_entrega_real <= oc.fecha_compromiso_entrega THEN 1
        ELSE 0
    END AS flag_entrega_a_tiempo,
    CASE
        WHEN ea.estado_entrega_almacen = 'EN_TRANSICION'
            AND COALESCE(rs.decision, 'PENDIENTE') = 'PENDIENTE' THEN 1
        ELSE 0
    END AS flag_transicion_pendiente,
    CASE
        WHEN ff.fecha_carga_factura IS NOT NULL THEN 'FACTURA_CARGADA'
        WHEN da.fecha_entrega_administracion IS NOT NULL THEN 'DOCUMENTACION'
        WHEN rs.id IS NOT NULL AND COALESCE(rs.decision, 'PENDIENTE') <> 'PENDIENTE' THEN 'REVISION_SOLICITANTE'
        WHEN ea.fecha_entrega_almacen IS NOT NULL THEN 'ALMACEN'
        WHEN rp.fecha_recepcion_procura IS NOT NULL THEN 'RECEPCION_PROCURA'
        ELSE 'ORDEN_EMITIDA'
    END AS etapa_orden
FROM pbi_ordenes_compra oc
JOIN pbi_sumarios_cotizacion su ON su.id = oc.sumario_cotizacion_id
JOIN pbi_solicitudes_compra sc ON sc.id = su.solicitud_compra_id
JOIN pbi_departamentos dep ON dep.id = sc.departamento_solicitante_id
JOIN pbi_usuarios solicitante ON solicitante.id = sc.solicitante_user_id
LEFT JOIN pbi_proveedores pr ON pr.id = oc.proveedor_id
LEFT JOIN pbi_pagos_finanzas pa ON pa.orden_compra_id = oc.id
LEFT JOIN pbi_recepciones_procura rp ON rp.orden_compra_id = oc.id
LEFT JOIN pbi_entregas_almacen ea ON ea.orden_compra_id = oc.id
LEFT JOIN pbi_revision_solicitante rs ON rs.orden_compra_id = oc.id
LEFT JOIN pbi_documentacion_administracion da ON da.orden_compra_id = oc.id
LEFT JOIN pbi_facturas_finanzas ff ON ff.orden_compra_id = oc.id;

CREATE VIEW pbi_v_dashboard_items AS
SELECT
    si.id AS solicitud_item_id,
    sc.id AS solicitud_id,
    sc.codigo AS solicitud_codigo,
    sc.fecha_solicitud,
    dep.nombre AS departamento_solicitante,
    p.id AS producto_id,
    p.codigo AS producto_codigo,
    p.nombre AS producto_nombre,
    cp.nombre AS categoria_producto,
    si.descripcion,
    si.cantidad,
    si.costo_estimado_unitario,
    si.subtotal_estimado
FROM pbi_solicitud_items si
JOIN pbi_solicitudes_compra sc ON sc.id = si.solicitud_compra_id
JOIN pbi_departamentos dep ON dep.id = sc.departamento_solicitante_id
LEFT JOIN pbi_productos p ON p.id = si.producto_id
LEFT JOIN pbi_categorias_producto cp ON cp.id = p.categoria_id;

CREATE VIEW pbi_v_dashboard_solicitudes AS
SELECT
    sc.id AS solicitud_id,
    sc.codigo AS solicitud_codigo,
    sc.fecha_solicitud,
    sc.estado AS solicitud_estado,
    dep.nombre AS departamento_solicitante,
    sc.monto_estimado_total,
    COALESCE(stats.cantidad_sumarios, 0)::int AS cantidad_sumarios,
    COALESCE(stats.cantidad_ordenes_compra, 0)::int AS cantidad_ordenes_compra,
    COALESCE(stats.monto_total_ordenado, 0)::numeric(14,2) AS monto_total_ordenado,
    stats.primera_fecha_sumario,
    stats.primera_fecha_orden,
    stats.ultima_fecha_factura_cargada,
    COALESCE(stats.ordenes_recibidas_procura, 0)::int AS ordenes_recibidas_procura,
    COALESCE(stats.ordenes_entregadas_almacen, 0)::int AS ordenes_entregadas_almacen,
    COALESCE(stats.ordenes_revisadas_solicitante, 0)::int AS ordenes_revisadas_solicitante,
    COALESCE(stats.ordenes_con_documentacion, 0)::int AS ordenes_con_documentacion,
    COALESCE(stats.ordenes_con_factura_cargada, 0)::int AS ordenes_con_factura_cargada,
    CASE
        WHEN COALESCE(stats.cantidad_ordenes_compra, 0) = 0 THEN 0
        WHEN COALESCE(stats.ordenes_con_factura_cargada, 0) = COALESCE(stats.cantidad_ordenes_compra, 0) THEN 1
        ELSE 0
    END AS flag_solicitud_completada,
    CASE
        WHEN COALESCE(stats.cantidad_sumarios, 0) = 0 THEN 'SOLICITUD'
        WHEN COALESCE(stats.cantidad_ordenes_compra, 0) = 0 THEN 'SUMARIO'
        WHEN COALESCE(stats.ordenes_recibidas_procura, 0) < COALESCE(stats.cantidad_ordenes_compra, 0) THEN 'ORDEN_COMPRA'
        WHEN COALESCE(stats.ordenes_entregadas_almacen, 0) < COALESCE(stats.cantidad_ordenes_compra, 0) THEN 'RECEPCION_PROCURA'
        WHEN COALESCE(stats.ordenes_revisadas_solicitante, 0) < COALESCE(stats.cantidad_ordenes_compra, 0) THEN 'ALMACEN_TRANSICION'
        WHEN COALESCE(stats.ordenes_con_documentacion, 0) < COALESCE(stats.cantidad_ordenes_compra, 0) THEN 'REVISION_SOLICITANTE'
        WHEN COALESCE(stats.ordenes_con_factura_cargada, 0) < COALESCE(stats.cantidad_ordenes_compra, 0) THEN 'FINANZAS'
        ELSE 'COMPLETADA'
    END AS etapa_actual,
    CASE
        WHEN stats.ultima_fecha_factura_cargada IS NOT NULL THEN (stats.ultima_fecha_factura_cargada - sc.fecha_solicitud)
        ELSE NULL
    END AS dias_solicitud_a_factura_cargada
FROM pbi_solicitudes_compra sc
JOIN pbi_departamentos dep ON dep.id = sc.departamento_solicitante_id
LEFT JOIN (
    SELECT
        sc_inner.id AS solicitud_id,
        COUNT(DISTINCT su.id) AS cantidad_sumarios,
        COUNT(DISTINCT oc.id) AS cantidad_ordenes_compra,
        COALESCE(SUM(oc.monto_total), 0) AS monto_total_ordenado,
        MIN(su.fecha_sumario) AS primera_fecha_sumario,
        MIN(oc.fecha_emision) AS primera_fecha_orden,
        MAX(ff.fecha_carga_factura) AS ultima_fecha_factura_cargada,
        COUNT(DISTINCT rp.orden_compra_id) AS ordenes_recibidas_procura,
        COUNT(DISTINCT ea.orden_compra_id) AS ordenes_entregadas_almacen,
        COUNT(DISTINCT CASE
            WHEN rs.id IS NOT NULL AND COALESCE(rs.decision, 'PENDIENTE') <> 'PENDIENTE' THEN rs.orden_compra_id
        END) AS ordenes_revisadas_solicitante,
        COUNT(DISTINCT da.orden_compra_id) AS ordenes_con_documentacion,
        COUNT(DISTINCT CASE
            WHEN ff.fecha_carga_factura IS NOT NULL THEN ff.orden_compra_id
        END) AS ordenes_con_factura_cargada
    FROM pbi_solicitudes_compra sc_inner
    LEFT JOIN pbi_sumarios_cotizacion su ON su.solicitud_compra_id = sc_inner.id
    LEFT JOIN pbi_ordenes_compra oc ON oc.sumario_cotizacion_id = su.id
    LEFT JOIN pbi_recepciones_procura rp ON rp.orden_compra_id = oc.id
    LEFT JOIN pbi_entregas_almacen ea ON ea.orden_compra_id = oc.id
    LEFT JOIN pbi_revision_solicitante rs ON rs.orden_compra_id = oc.id
    LEFT JOIN pbi_documentacion_administracion da ON da.orden_compra_id = oc.id
    LEFT JOIN pbi_facturas_finanzas ff ON ff.orden_compra_id = oc.id
    GROUP BY sc_inner.id
) stats ON stats.solicitud_id = sc.id;

CREATE VIEW pbi_v_kpis_resumen AS
SELECT
    COUNT(*)::int AS ordenes_totales,
    COALESCE(SUM(monto_total), 0)::numeric(14,2) AS monto_oc_total,
    COALESCE(SUM(monto_pagado), 0)::numeric(14,2) AS monto_pagado_total,
    COALESCE(AVG(dias_oc_a_pago), 0)::numeric(10,2) AS promedio_dias_oc_a_pago,
    COALESCE(AVG(dias_solicitud_a_factura_cargada), 0)::numeric(10,2) AS promedio_dias_e2e,
    COALESCE(AVG(porcentaje_cumplimiento), 0)::numeric(10,2) AS promedio_cumplimiento_almacen,
    COALESCE(100.0 * SUM(flag_pagada)::numeric / NULLIF(COUNT(*), 0), 0)::numeric(10,2) AS pct_ordenes_pagadas,
    COALESCE(100.0 * SUM(flag_factura_cargada)::numeric / NULLIF(COUNT(*), 0), 0)::numeric(10,2) AS pct_factura_cargada,
    COALESCE(100.0 * SUM(flag_aceptada)::numeric / NULLIF(COUNT(*), 0), 0)::numeric(10,2) AS pct_aceptadas,
    COALESCE(100.0 * SUM(flag_entrega_a_tiempo)::numeric / NULLIF(COUNT(*), 0), 0)::numeric(10,2) AS pct_entregas_a_tiempo
FROM pbi_v_dashboard_ordenes;

CREATE VIEW pbi_v_gerencia_resumen AS
SELECT
    COALESCE(AVG(dias_solicitud_a_factura_cargada), 0)::numeric(10,2) AS lead_time_end_to_end_dias,
    COALESCE(100.0 * SUM(flag_solicitud_completada)::numeric / NULLIF(COUNT(*), 0), 0)::numeric(10,2) AS pct_solicitudes_completadas
FROM pbi_v_dashboard_solicitudes;

CREATE VIEW pbi_v_gerencia_solicitudes_por_etapa AS
SELECT
    etapa_actual,
    COUNT(*)::int AS total_solicitudes
FROM pbi_v_dashboard_solicitudes
WHERE flag_solicitud_completada = 0
GROUP BY etapa_actual
ORDER BY total_solicitudes DESC, etapa_actual;

CREATE VIEW pbi_v_gerencia_monto_por_departamento AS
SELECT
    departamento_solicitante,
    COUNT(*)::int AS total_solicitudes,
    COALESCE(SUM(monto_total_ordenado), 0)::numeric(14,2) AS monto_total_comprado
FROM pbi_v_dashboard_solicitudes
GROUP BY departamento_solicitante
ORDER BY monto_total_comprado DESC, departamento_solicitante;

CREATE VIEW pbi_v_procura_tiempo_solicitud_a_sumario AS
SELECT
    solicitud_id,
    solicitud_codigo,
    departamento_solicitante,
    fecha_solicitud,
    primera_fecha_sumario AS fecha_sumario,
    (primera_fecha_sumario - fecha_solicitud) AS dias_solicitud_a_sumario
FROM pbi_v_dashboard_solicitudes
WHERE primera_fecha_sumario IS NOT NULL;

CREATE VIEW pbi_v_procura_tiempo_sumario_a_oc AS
SELECT
    orden_id,
    orden_codigo,
    solicitud_codigo,
    sumario_codigo,
    proveedor_nombre,
    fecha_sumario,
    fecha_emision,
    dias_sumario_a_oc
FROM pbi_v_dashboard_ordenes
WHERE dias_sumario_a_oc IS NOT NULL;

CREATE VIEW pbi_v_procura_sumarios_por_solicitud AS
SELECT
    solicitud_id,
    solicitud_codigo,
    departamento_solicitante,
    cantidad_sumarios
FROM pbi_v_dashboard_solicitudes
ORDER BY cantidad_sumarios DESC, solicitud_codigo;

CREATE VIEW pbi_v_procura_ordenes_por_sumario AS
SELECT
    su.id AS sumario_id,
    su.codigo AS sumario_codigo,
    sc.codigo AS solicitud_codigo,
    COUNT(oc.id)::int AS cantidad_ordenes_compra,
    COALESCE(SUM(oc.monto_total), 0)::numeric(14,2) AS monto_total_ordenado
FROM pbi_sumarios_cotizacion su
JOIN pbi_solicitudes_compra sc ON sc.id = su.solicitud_compra_id
LEFT JOIN pbi_ordenes_compra oc ON oc.sumario_cotizacion_id = su.id
GROUP BY su.id, su.codigo, sc.codigo
ORDER BY cantidad_ordenes_compra DESC, su.codigo;

CREATE VIEW pbi_v_almacen_productos_departamento AS
SELECT
    departamento_solicitante,
    producto_codigo,
    producto_nombre,
    categoria_producto,
    COALESCE(SUM(cantidad), 0)::numeric(12,2) AS cantidad_solicitada,
    COALESCE(SUM(subtotal_estimado), 0)::numeric(14,2) AS monto_estimado
FROM pbi_v_dashboard_items
GROUP BY departamento_solicitante, producto_codigo, producto_nombre, categoria_producto
ORDER BY departamento_solicitante, cantidad_solicitada DESC, producto_nombre;

CREATE VIEW pbi_v_almacen_cumplimiento_entregas AS
SELECT
    orden_id,
    orden_codigo,
    solicitud_codigo,
    proveedor_nombre,
    fecha_entrega_almacen,
    porcentaje_cumplimiento
FROM pbi_v_dashboard_ordenes
WHERE entrega_almacen_id IS NOT NULL;

CREATE VIEW pbi_v_almacen_transicion_pendiente AS
SELECT
    orden_id,
    orden_codigo,
    solicitud_codigo,
    solicitante_nombre,
    departamento_solicitante,
    proveedor_nombre,
    fecha_entrega_almacen,
    (CURRENT_DATE - fecha_entrega_almacen) AS dias_en_transicion,
    estado_entrega_almacen,
    COALESCE(decision_solicitante, 'PENDIENTE') AS decision_solicitante
FROM pbi_v_dashboard_ordenes
WHERE flag_transicion_pendiente = 1;

CREATE VIEW pbi_v_almacen_rechazos_solicitante AS
SELECT
    pr.nombre AS proveedor_nombre,
    p.codigo AS producto_codigo,
    p.nombre AS producto_nombre,
    COALESCE(SUM(oci.cantidad_ordenada), 0)::numeric(12,2) AS cantidad_total,
    COALESCE(SUM(CASE WHEN rs.decision = 'RECHAZADO' THEN oci.cantidad_ordenada ELSE 0 END), 0)::numeric(12,2) AS cantidad_rechazada,
    COALESCE(
        100.0 * SUM(CASE WHEN rs.decision = 'RECHAZADO' THEN oci.cantidad_ordenada ELSE 0 END)
        / NULLIF(SUM(oci.cantidad_ordenada), 0),
        0
    )::numeric(10,2) AS pct_productos_rechazados
FROM pbi_orden_compra_items oci
JOIN pbi_ordenes_compra oc ON oc.id = oci.orden_compra_id
JOIN pbi_proveedores pr ON pr.id = oc.proveedor_id
LEFT JOIN pbi_productos p ON p.id = oci.producto_id
LEFT JOIN pbi_revision_solicitante rs ON rs.orden_compra_id = oc.id
GROUP BY pr.nombre, p.codigo, p.nombre
ORDER BY pct_productos_rechazados DESC, cantidad_rechazada DESC, proveedor_nombre;

CREATE VIEW pbi_v_finanzas_ordenes_pago_estado AS
SELECT
    CASE WHEN flag_pagada = 1 THEN 'PAGADA' ELSE 'PENDIENTE_PAGO' END AS estado_pago_resumen,
    COUNT(*)::int AS total_ordenes,
    COALESCE(SUM(monto_total), 0)::numeric(14,2) AS monto_total_ordenes
FROM pbi_v_dashboard_ordenes
GROUP BY CASE WHEN flag_pagada = 1 THEN 'PAGADA' ELSE 'PENDIENTE_PAGO' END
ORDER BY estado_pago_resumen;

CREATE VIEW pbi_v_finanzas_documentacion_carga AS
SELECT
    orden_id,
    orden_codigo,
    solicitud_codigo,
    proveedor_nombre,
    fecha_entrega_administracion,
    fecha_carga_factura,
    dias_documentacion_a_factura,
    CASE WHEN flag_factura_cargada = 1 THEN 'FACTURA_CARGADA' ELSE 'PENDIENTE_CARGA' END AS estado_factura
FROM pbi_v_dashboard_ordenes
WHERE documentacion_id IS NOT NULL OR factura_id IS NOT NULL;

CREATE VIEW pbi_v_finanzas_facturas_vs_documentacion AS
SELECT
    CASE
        WHEN documentacion_id IS NULL THEN 'DOCUMENTACION_PENDIENTE'
        WHEN flag_factura_cargada = 1 THEN 'FACTURA_CARGADA'
        ELSE 'FACTURA_PENDIENTE'
    END AS estado_documental,
    COUNT(*)::int AS total_ordenes,
    COALESCE(SUM(monto_total), 0)::numeric(14,2) AS monto_total_ordenes
FROM pbi_v_dashboard_ordenes
GROUP BY CASE
    WHEN documentacion_id IS NULL THEN 'DOCUMENTACION_PENDIENTE'
    WHEN flag_factura_cargada = 1 THEN 'FACTURA_CARGADA'
    ELSE 'FACTURA_PENDIENTE'
END
ORDER BY estado_documental;

CREATE VIEW pbi_v_finanzas_pagos_proveedor AS
SELECT
    proveedor_nombre,
    COUNT(*)::int AS total_ordenes,
    COALESCE(SUM(monto_pagado), 0)::numeric(14,2) AS monto_pagado_total
FROM pbi_v_dashboard_ordenes
GROUP BY proveedor_nombre
ORDER BY monto_pagado_total DESC, proveedor_nombre;
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS pbi_v_finanzas_pagos_proveedor;
DROP VIEW IF EXISTS pbi_v_finanzas_facturas_vs_documentacion;
DROP VIEW IF EXISTS pbi_v_finanzas_documentacion_carga;
DROP VIEW IF EXISTS pbi_v_finanzas_ordenes_pago_estado;
DROP VIEW IF EXISTS pbi_v_almacen_rechazos_solicitante;
DROP VIEW IF EXISTS pbi_v_almacen_transicion_pendiente;
DROP VIEW IF EXISTS pbi_v_almacen_cumplimiento_entregas;
DROP VIEW IF EXISTS pbi_v_almacen_productos_departamento;
DROP VIEW IF EXISTS pbi_v_procura_ordenes_por_sumario;
DROP VIEW IF EXISTS pbi_v_procura_sumarios_por_solicitud;
DROP VIEW IF EXISTS pbi_v_procura_tiempo_sumario_a_oc;
DROP VIEW IF EXISTS pbi_v_procura_tiempo_solicitud_a_sumario;
DROP VIEW IF EXISTS pbi_v_gerencia_monto_por_departamento;
DROP VIEW IF EXISTS pbi_v_gerencia_solicitudes_por_etapa;
DROP VIEW IF EXISTS pbi_v_gerencia_resumen;
DROP VIEW IF EXISTS pbi_v_kpis_resumen;
DROP VIEW IF EXISTS pbi_v_dashboard_solicitudes;
DROP VIEW IF EXISTS pbi_v_dashboard_items;
DROP VIEW IF EXISTS pbi_v_dashboard_ordenes;
DROP VIEW IF EXISTS pbi_v_kpi_finanzas;
DROP VIEW IF EXISTS pbi_v_kpi_almacen;
DROP VIEW IF EXISTS pbi_v_kpi_procura;
DROP VIEW IF EXISTS pbi_v_kpi_gerencia;
SQL);
    }
};
