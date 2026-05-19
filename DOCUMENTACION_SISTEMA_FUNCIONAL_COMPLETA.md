# DOCUMENTACION FUNCIONAL COMPLETA DEL SISTEMA AGARCORP

Fecha de elaboracion: 18-05-2026

## 1. Objetivo y alcance
Este documento describe funcionalmente todo el sistema AGARCORP (ERP de compras, almacen, pagos y soporte), tomando como fuente el comportamiento real implementado en recursos Filament, modelos y flujo operativo.

Incluye:
- Roles operativos y modulos por rol.
- Ventanas/pestanas de cada modulo.
- Botones y acciones visibles de usuario por modulo.
- Estados visibles para el solicitante.
- Estados por item en trazabilidad (lo mas importante solicitado).
- Dashboards, notificaciones y configuraciones.

No incluye detalle de flujo interno tecnico no visible al usuario, salvo donde impacta en etiquetas de UI.

## 2. Glosario operativo
- Bandeja de entrada/operativa: lista de registros pendientes que requieren accion para que el proceso avance.
- Historial: lista de registros procesados para consulta y trazabilidad (sin edicion operativa normal).
- Borrador: registro guardado sin envio formal.
- Correccion: registro devuelto por una instancia superior para ajustar datos.
- Validacion/inspeccion: revision tecnica/documental previa a aprobacion.
- Aprobacion: decision jerarquica que habilita el siguiente paso del proceso.
- Trazabilidad: vista de avance de la solicitud por fases y por item.
- Conformidad por item: decision del solicitante por cada item recibido (aceptar o rechazar).

## 3. Mapa general del sistema
El sistema se organiza en bloques funcionales:
- Escritorio
- Solicitudes de Compra
- Aprobaciones (compras, sumarios, ODC)
- Compras (sumarios, ordenes, recepcion)
- Pagos
- Facturas y Retenciones
- Inventario
- Retiros y Compras (almacen)
- Configuraciones
- Tickets de Soporte
- Dashboard (Finanzas, Procura, Almacen)
- Centro de Notificaciones

## 4. Roles operativos y modulos
Resumen funcional por rol (consolidando configuracion real de recursos + seeders):

### 4.1 Gerencia de Finanzas
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Aprobaciones de Compra (tab de aprobacion/historial segun asignacion)
- Aprobacion de Sumarios
- Aprobacion de ODC
- Dashboard de Finanzas

### 4.2 Procura
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Aprobaciones de Compra (bandeja y su historial por rol)
- Proveedores
- Sumario Cotizaciones
- Ordenes de Compra
- Recepcion de Productos
- Dashboard de Procura

### 4.3 Almacen
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Aprobaciones de Compra (bandeja/historial almacen)
- Inventario:
  - Dashboard de Almacen
  - Consultar Entradas
  - Consultar Salidas
  - Registro de Materiales
  - Almacen ADV
  - Categorias
  - Codificacion SKU
- Retiros y Compras:
  - Bandeja de Retiros Diarios
  - Recepcion de Materiales Nuevos

### 4.4 A.I.T
- Escritorio
- Tickets de Soporte (gestion global)
- Solicitudes de Compra
- Configuraciones:
  - Usuarios
  - Roles y Permisos
  - Departamentos
  - Cargos
  - Impresoras
  - Informacion AGARCORP

### 4.5 Validador Finanzas
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Validaciones:
  - Inspeccion de Sumarios
  - Inspeccion de ODC

### 4.6 Finanzas Pagos
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Pagos:
  - Realizacion de Pagos ODC
  - Facturas de Compra

### 4.7 Administracion
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Facturas y Retenciones:
  - Administracion de Facturas

### 4.8 Gerencia de Operaciones
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra
- Aprobaciones de Compra

### 4.9 Alta Gerencia
- Escritorio
- Tickets de Soporte (gestion)
- Solicitudes de Compra
- Dashboards ejecutivos:
  - Dashboard de Finanzas
  - Dashboard de Procura
  - Dashboard de Almacen
- Configuraciones (segun permisos asignados)

### 4.10 Roles solicitantes (Talento Humano / Mantenimiento / S.I.H.O)
- Escritorio
- Tickets de Soporte
- Solicitudes de Compra

## 5. Modulos comunes transversales

### 5.1 Escritorio
Pantalla de bienvenida con accesos rapidos por rol y recordatorio de modulos disponibles.

### 5.2 Centro de Notificaciones
- Menu: Notificaciones.
- Muestra pendientes por modulo y ventana.
- Boton: Limpiar Historial (requiere clave de inicio de sesion).

### 5.3 Tickets de Soporte
- Vista usuario: ve y crea solo sus tickets.
- Vista gestor (permiso Manage:Ticket): ve todos, agrupa por departamento y actualiza estado.
- Estados ticket visibles:
  - Abierto
  - En Proceso
  - Resuelto
  - Cancelado
- Tipos visibles:
  - Soporte IT
  - Cambio de toner
- Accion adicional para gestor: Exportar CSV.

### 5.4 Solicitudes de Compra (base)
Pestanas visibles para solicitante:
- Mis solicitudes
- Historial de Solicitudes
- Borradores

Acciones principales del solicitante:
- Crear solicitud
- Ver
- Editar (solo borrador o correccion habilitada)
- Imprimir / Guardar PDF
- Trazabilidad
- Conformidad de Materiales (cuando hay items pendientes)

## 6. Detalle funcional por modulo (ventanas + botones)

## 6.1 Aprobaciones de Compra
Pestanas:
- Bandeja de revision (o Bandeja de aprobaciones segun rol)
- Historial almacen
- Historial aprobacion
- Historial procura

Botones/acciones:
- Imprimir / Guardar PDF
- Crear Sumario (si aplica)
- Trazabilidad
- Ver Archivos (historial procura)
- Ver (modal completo)
- Firmar almacen
- Rechazar almacen
- Firmar aprobacion
- Rechazar aprobacion
- Firmar recepcion procura
- Rechazar procura

Notas:
- Rechazos piden comentario y clave de firma.
- Firma exige confirmacion de clave (password y confirmacion).

## 6.2 Sumario Cotizaciones (Procura)
Pestanas:
- Creacion de Sumarios
- Sumarios en correccion
- Historial de sumarios
- Borradores

Botones/acciones relevantes:
- Ver (solicitud en creacion)
- Ver items
- Realizar sumario
- Ver sumario (resumen)
- Vista PDF Sumario
- Ver solicitud asociada
- Sumario en correccion (tablero)
- Enviar a Validacion Finanzas
- Validar Finanzas: Aceptar (cuando aplica)
- Validar Finanzas: Rechazar (cuando aplica)
- Revisiones de Gerencia Finanzas sobre items (en modulo de aprobacion de sumarios)

## 6.3 Inspeccion de Sumarios (Validador Finanzas)
Bandeja de sumarios en estado pendiente de validacion.

Acciones:
- Ver sumario
- Ver solicitud asociada
- Enviar a Gerencia Finanzas
- Rechazar (motivo obligatorio)

## 6.4 Aprobacion de Sumarios (Gerencia Finanzas)
Pestanas:
- Bandeja de aprobacion
- Historial de aprobacion

Acciones:
- Ver sumario
- Ver solicitud asociada
- Aprobacion/rechazo gerencial de sumario (incluye validacion por items segun reglas)

## 6.5 Ordenes de Compra (Procura)
Pestanas:
- Creacion de ODC
- ODC en correcciones
- Pagos de ODC
- Historial de ODC

Acciones principales:
- Ver sumario
- Creacion de ODC
- Vista PDF ODC
- Ver resumen ODC
- Editar ODC
- Ver solicitud
- Aprobar para pago (Gerencia Finanzas cuando aplica en flujo)
- Registrar Pago (Finanzas)
- Confirmar pago y en transito (Procura)
- Cargar Factura/Nota de recepcion (Procura)
- Pasar a Zona de Transicion (Almacen)
- Enviar factura a Administracion (Finanzas)
- Conformidad de Materiales por item (Solicitante)
- Entrada/Registro Nuevo por item (Almacen)

Acciones auxiliares de control en listado:
- Conformidades de Usuarios (vista consolidada)
- Marcar devolucion planificada
- Marcar devolucion realizada
- Marcar ODC resuelta

## 6.6 Aprobacion de ODC (Gerencia Finanzas)
Pestanas:
- Bandeja de aprobacion
- Historial de aprobacion

Acciones:
- Ver solicitud asociada
- Ver sumario
- Ver ODC
- Aprobacion de ODC (modal)
  - Rechazar ODC
  - Enviar a Pago Finanzas

## 6.7 Inspeccion de ODC (Validador Finanzas)
Bandeja en estado pendiente de validacion finanzas.

Acciones:
- Revisar ODC
- Enviar a Gerencia Finanzas
- Rechazar (con motivo)

## 6.8 Realizacion de Pagos ODC (Finanzas Pagos)
Pestanas:
- Pagos Pendientes
- Pagos Registrados

Acciones:
- Ver resumen ODC
- Ver/editar tasa BCV
- Subir imagen y marcar pagado

## 6.9 Facturas de Compra (Finanzas Pagos)
Pestanas:
- Facturas por enviar
- Facturas enviadas

Acciones:
- Ver factura (descargar)
- Enviar Factura a Administracion
- Abrir imagen de factura (si aplica)

## 6.10 Administracion de Facturas (Administracion)
Pestanas:
- Facturas recibidas
- Facturas cargadas

Acciones:
- Ver factura
- Cargar en DB / respaldo administrativo (accion de administracion en tabla)

## 6.11 Recepcion de Productos (Procura)
Listado operativo de ODC pagadas/en transito o con factura pendiente.

Acciones:
- Cargar Factura o Nota de Entrega y enviar a Almacen
- Vista previa ODC

## 6.12 Recepcion de Materiales Nuevos (Almacen)
Pestanas:
- Recibidos en Almacen
- En zona de transicion
- Pendiente de entrada final

Acciones:
- Ver solicitud
- Marcar en Zona de transicion
- Realizar entrada
- Realizar registro nuevo
- Vista previa ODC

## 6.13 Bandeja de Retiros Diarios (Almacen)
Pestanas:
- Pendientes
- Historial

Acciones:
- Aprobar Retiro
- Rechazar
- Exportar Control de Despacho
- Exportar por rango

Estados visibles de retiro:
- pendiente
- aprobado
- rechazado

## 6.14 Consultar Entradas (Almacen)
Acciones:
- Importar y procesar CSV
- Exportar Excel
- Exportar CSV
- Ver detalle
- Editar
- Ver formato

## 6.15 Consultar Salidas (Almacen)
Acciones:
- Importar y procesar CSV
- Exportar Excel
- Exportar CSV
- Ver detalle
- Editar
- Ver formato

## 6.16 Registro de Materiales (Almacen)
Acceso de consulta historica de movimientos con filtros por tipo, fecha y control.

## 6.17 Almacen ADV (catalogo de productos)
Acciones destacadas:
- Editar
- Etiqueta Barra
- Etiqueta QR
- Archivar / Reactivar
- Eliminar permanentemente (solo archivados y con restricciones de historial)
- Toolbar:
  - Imprimir Etiquetas de Barra
  - Imprimir Etiquetas QR
  - Vaciar archivados

## 6.18 Proveedores / Categorias / SKU / Configuracion
Modulos de mantenimiento para datos maestros y estructura de inventario/usuarios.

## 7. Estados visibles del solicitante (prioridad alta)
Estados visibles en la columna Estado del modulo Solicitudes de Compra para el solicitante:
- Borrador
- En espera de almacen
- En espera de aprobador
- En espera de procura
- Recibido por procura
- Disponible en zona de transicion
- Productos en camino
- Pago registrado
- En proceso administrativo
- Pendiente de devolucion
- Completada parcialmente
- Completada
- Rechazada

Interpretacion funcional:
- Borrador: aun no enviada formalmente.
- En espera de almacen/aprobador/procura: pendiente de firma en la etapa correspondiente.
- Recibido por procura: procura ya recibio; aun sin avance de cotizacion/ODC.
- Disponible en zona de transicion: item(s) llegaron a almacen y esperan conformidad.
- Productos en camino: compra pagada y en traslado.
- Pago registrado: finanzas ya registro desembolso.
- En proceso administrativo: fase de documentos/pagos/factura/administracion.
- Pendiente de devolucion: hubo rechazo de item pendiente de devolucion.
- Completada parcialmente: se entrego parte de lo solicitado.
- Completada: cobertura total aceptada por items.
- Rechazada: solicitud devuelta/cerrada por rechazo.

## 8. Estados por item en trazabilidad (prioridad maxima)
En el modal Trazabilidad de solicitud, por cada item se muestran estados y cobertura.

### 8.1 Estados de item visibles
- Sin procesar
- En Cotizacion
- En Orden de Compra
- ODC Pagada
- Disponible en Almacen
- Entregado parcial
- Entregado
- Rechazado por solicitante
- Devolucion planificada
- Devolucion realizada

Subetiquetas visibles (secundarias segun caso):
- En espera devolucion
- Devolucion planificada
- Devolucion realizada

### 8.2 Cobertura visible del item
- Pendiente
- Parcial
- Completo

### 8.3 Metricas visibles por item en trazabilidad
- En Cotizacion (cantidad)
- En ODC (cantidad)
- Cantidad pedida
- Entregados
- Faltantes
- Avance (%)

## 9. Conformidad por item del solicitante
Cuando hay ODC en transicion y items sin decision, aparece la accion Conformidad de Materiales.

Opciones por item:
- Aceptar
- Rechazar a Devoluciones

Campos relevantes en rechazo:
- Cantidad rechazada
- Motivo

Resultado funcional:
- Aceptado: item queda apto para entrada final de almacen.
- Rechazado: item pasa a flujo de devolucion y puede habilitar nueva conformidad luego de gestion.

## 10. Dashboards

### 10.1 Dashboard de Finanzas
Widgets de resumen, pagos por proveedor, pagadas vs pendientes, facturas cargadas vs pendientes.
Acceso: Gerencia de Finanzas y Alta Gerencia.

### 10.2 Dashboard de Procura
Widgets de eficiencia de procura: tiempos solicitud->sumario, sumario->ODC, volumenes.
Acceso: Procura y Alta Gerencia.

### 10.3 Dashboard de Almacen
Widgets de stock/consumo por categoria y departamento.
Acceso: Almacen y Alta Gerencia.

## 11. Notificaciones del flujo
El Centro de Notificaciones consolida pendientes por modulo/ventana para:
- Tickets
- Solicitudes
- Aprobaciones de Compra
- Sumarios
- Inspeccion/Aprobacion de Sumarios
- ODC
- Aprobacion de ODC
- Pagos
- Recepcion
- Facturas
- Administracion de Facturas

## 12. Reglas de firma y seguridad operativa
- Acciones criticas de aprobacion/rechazo usan clave de firma.
- Varias acciones piden doble confirmacion de clave (password + confirmacion).
- En aprobaciones de ODC por Gerencia de Finanzas se valida firma y rol.

## 13. Resumen rapido de estados visibles adicionales

### 13.1 Tickets
- Abierto
- En Proceso
- Resuelto
- Cancelado

### 13.2 Retiros diarios
- pendiente
- aprobado
- rechazado

### 13.3 Estados visibles frecuentes ODC (segun modulo)
- PENDIENTE APROBACION GERENCIA FINANZAS
- ENVIADA A PAGOS
- PAGO REGISTRADO
- PAGADA Y EN TRANSITO
- ENTREGADA A ALMACEN
- EN TRANSICION ALMACEN
- PENDIENTE ENTRADA FINAL
- FACTURA ENVIADA A ADMINISTRACION
- FACTURA CARGADA
- CERRADA CONFORME
- RECHAZADA POR GERENCIA FINANZAS

## 14. Observaciones de consistencia funcional
- El catalogo de ayuda interno menciona Historial de Conformidades en Solicitudes, pero en la vista actual del solicitante se muestran 3 pestanas: Mis solicitudes, Historial de Solicitudes y Borradores.
- Existe recurso Pagos Procura con tabs definidos, pero actualmente esta deshabilitado para menu (canAccess false).

## 15. Referencias tecnicas base usadas para esta documentacion
Fuentes principales de verdad funcional:
- app/Support/Filament/ModuleHelp.php
- database/seeders/DatabaseSeeder.php
- app/Support/SolicitudCompraFlow.php
- app/Filament/Resources/**/Pages/*.php
- app/Filament/Resources/**/Tables/*.php
- app/Filament/Pages/*.php
- app/Support/Filament/FlowInboxNotificationService.php

---

Documento elaborado para uso funcional operativo, capacitacion y trazabilidad de usuarios por rol.
