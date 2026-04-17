# Manual de pruebas - Flujo ODC post-compra

## 1) Objetivo
Este manual te guia para probar todo lo nuevo del flujo post-compra:
- Pago en Finanzas con comprobante
- Confirmacion de pago por Procura
- Recepcion por Procura (NOTA o FACTURA)
- Envio de factura a Administracion
- Placeholder de carga manual contable (Proximamente)
- Cierre conforme por Solicitante
- Rechazo por Solicitante (devolucion)

## 2) Precondiciones
1. Tener migraciones al dia.
2. Tener usuarios con roles:
   - Procura
   - Finanzas
   - Gerencia de Finanzas
   - Almacen
   - Solicitante
   - Administracion (departamento ADMINISTRACION o ADMINISTRACION)
3. Tener storage publico enlazado.

## 3) Comandos recomendados de preparacion
1. Migrar:
php artisan migrate --force --no-interaction

2. Generar un caso base en pago registrado:
php scripts/generar_odc_flujo_prueba.php --stage=pago_registrado --items=2

3. Generar escenario feliz completo:
php scripts/generar_odc_flujo_prueba.php --stage=cerrada_conforme --items=2

4. Generar escenario de rechazo:
php scripts/generar_odc_flujo_prueba.php --stage=rechazo_solicitante --items=2

## 4) Stages soportados por script
- odc_generada
- pago_registrado
- pago_confirmado
- recepcion_nota
- recepcion_factura
- factura_enviada_admin
- factura_procesada
- cerrada_conforme
- rechazo_solicitante

Tambien puedes avanzar una ODC existente:
php scripts/generar_odc_flujo_prueba.php --odc_id=ID --stage=STAGE

## 5) Flujo funcional en UI (paso a paso)

### Paso A - Finanzas registra pago
Ubicacion: Tabla de Ordenes de Compra
Accion: Finanzas: Registrar Pago
Validar:
- Guarda monto_pagado, referencia_pago, comprobante_pago_path
- Marca pago_registrado_at y pago_por_user_id
- Cambia estado a PAGADA
- Cambia workflow_post_compra a PAGO_REGISTRADO_FINANZAS
- Notifica a usuarios de Procura

### Paso B - Procura confirma pago recibido
Ubicacion: Tabla de Ordenes de Compra
Accion: Procura: Confirmar pago recibido
Validar:
- Guarda confirmado_procura_at y confirmado_por_user_id
- Cambia estado a EN_ESPERA_DE_PRODUCTO
- Cambia workflow_post_compra a ESPERANDO_PRODUCTO

### Paso C - Procura procesa recepcion
Ubicacion: Tabla de Ordenes de Compra
Accion: Procesar Recepcion
Reglas:
- Solo se habilita luego de confirmar pago
- Si elige FACTURA, exige imagen factura
Validar:
- Guarda tipo_documento_recepcion
- Si FACTURA, guarda factura_path
- Marca recepcion_procesada_at y recibido_por_user_id
- Cambia workflow_post_compra a EN_TRANSICION_ALMACEN
- Items pasan a ZONA_TRANSICION
- Notifica al solicitante
- Si FACTURA, notifica a Finanzas

### Paso D - Rama factura (si aplica)
1) Finanzas envia a Administracion
Accion: Finanzas: Enviar factura a Administracion
Validar:
- Guarda factura_enviada_administracion_at y factura_enviada_por_user_id
- Cambia workflow_post_compra a FACTURA_ENVIADA_ADMINISTRACION
- Notifica a Administracion

2) Administracion abre carga manual
Accion: Administracion: Cargar factura manual
Validar:
- Se abre modal Proximamente

3) Administracion marca factura procesada
Accion: Marcar Factura Procesada
Validar:
- Guarda factura_procesada_administracion_at
- Cambia workflow_post_compra a BACKUP_FACTURA_COMPLETADO

### Paso E - Decision del solicitante
Caso 1: Aceptar
Accion: Aceptar Conformidad
Validar:
- Guarda conformidad_solicitante_at y conformidad_por_user_id
- Ejecuta entrada oficial de inventario
- Items pasan a ENTREGADO_SOLICITANTE
- Cambia workflow_post_compra a CERRADA_CONFORME

Caso 2: Rechazar
Accion: Solicitante: Rechazar producto
Validar:
- Guarda devolucion_solicitada_at, devolucion_solicitada_por_user_id, devolucion_motivo
- Cambia workflow_post_compra a RECHAZADA_SOLICITANTE
- Notifica a Procura, Finanzas y Gerencia de Finanzas

## 6) Checklist rapido por pantalla

### En Ordenes de Compra (tabla)
- Ver columna Flujo post-compra
- Ver boton Finanzas: Registrar Pago
- Ver boton Procura: Confirmar pago recibido
- Ver boton Procesar Recepcion
- Ver boton Finanzas: Enviar factura a Administracion
- Ver boton Administracion: Cargar factura manual (Proximamente)
- Ver boton Solicitante: Rechazar producto

### En Editar ODC (formulario)
- Ver campo workflow_post_compra
- Ver seccion Pagos y confirmaciones
- Ver bloque Retenciones y comprobantes (Proximamente)

## 7) Archivos clave tocados
- scripts/generar_odc_flujo_prueba.php
- app/Filament/Resources/OrdenesCompra/Tables/OrdenesCompraTable.php
- app/Filament/Resources/OrdenesCompra/Schemas/OrdenCompraForm.php
- app/Support/OrdenCompraRecepcionService.php
- app/Support/OrdenCompraConformidadService.php
- app/Support/SumarioFinanceApprovalService.php
- app/Models/OrdenCompra.php
- app/Models/SumarioItemOpcion.php
- database/migrations/2026_04_17_130000_add_post_odc_workflow_fields_to_ordenes_compra_table.php

## 8) Troubleshooting
1. Si falla el script por tabla de opciones:
Verifica que SumarioItemOpcion use tabla sumario_item_opciones.

2. Si falla por columnas workflow_post_compra:
Ejecuta migracion pendiente:
php artisan migrate --path=database/migrations/2026_04_17_130000_add_post_odc_workflow_fields_to_ordenes_compra_table.php --force --no-interaction

3. Si no aparecen botones por rol:
- Revisar role y departamento del usuario
- Cerrar sesion y volver a entrar
- Revisar permisos de Shield/Spatie
