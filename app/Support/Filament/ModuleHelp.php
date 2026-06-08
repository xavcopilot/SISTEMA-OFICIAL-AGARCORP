<?php

namespace App\Support\Filament;

class ModuleHelp
{
    /**
     * @return array{title:string,description:string,windows:array<int,array{title:string,description:string}>}
     */
    public static function current(): array
    {
        $segment2 = (string) request()->segment(2);

        $catalog = self::catalog();

        if (array_key_exists($segment2, $catalog)) {
            return $catalog[$segment2];
        }

        return [
            'title' => self::humanize($segment2 !== '' ? $segment2 : 'modulo actual'),
            'description' => 'Este modulo centraliza operaciones y seguimiento del proceso asociado.',
            'windows' => [
                [
                    'title' => 'Listado principal',
                    'description' => 'Aqui puedes consultar, filtrar y ejecutar las acciones disponibles de este modulo.',
                ],
            ],
        ];
    }

    /**
     * @return array<string,array{title:string,description:string,windows:array<int,array{title:string,description:string}>}>
     */
    private static function catalog(): array
    {
        return [
            'solicitudes-compra' => [
                'title' => 'Solicitudes de Compra',
                'description' => 'Gestiona solicitudes desde su creacion hasta su historial completo.',
                'windows' => [
                    ['title' => 'Mis solicitudes', 'description' => 'Solicitudes activas del solicitante con seguimiento en curso.'],
                    ['title' => 'Historial de Solicitudes', 'description' => 'Solicitudes finalizadas o completadas.'],
                    ['title' => 'Borradores', 'description' => 'Solicitudes guardadas sin enviar.'],
                ],
            ],
            'sumarios' => [
                'title' => 'Sumario de Cotizaciones',
                'description' => 'Consolida cotizaciones y decisiones de validacion/aprobacion financiera.',
                'windows' => [
                    ['title' => 'Creacion de Sumarios', 'description' => 'Prepara nuevos sumarios a partir de solicitudes aprobadas en flujo.'],
                    ['title' => 'Sumarios en correccion', 'description' => 'Sumarios devueltos para ajustes antes de nueva revision.'],
                    ['title' => 'Historial de sumarios', 'description' => 'Sumarios aprobados por gerencia o rechazados.'],
                    ['title' => 'Borradores', 'description' => 'Sumarios en etapa de preparacion.'],
                ],
            ],
            'ordenes-compra' => [
                'title' => 'Ordenes de Compra',
                'description' => 'Controla creacion, aprobacion, pago, recepcion y cierre de ODC.',
                'windows' => [
                    ['title' => 'Creacion de ODC', 'description' => 'Genera ordenes pendientes desde sumarios aprobados.'],
                    ['title' => 'ODC en correcciones', 'description' => 'ODC pendientes de aprobacion o rechazadas corregibles.'],
                    ['title' => 'Pagos de ODC', 'description' => 'Bandeja para registro y control de pagos.'],
                    ['title' => 'Historial de ODC', 'description' => 'Seguimiento post-aprobacion y consulta de soportes.'],
                ],
            ],
            'aprobacion-sumarios' => [
                'title' => 'Aprobacion de Sumarios',
                'description' => 'Evalua y aprueba/rechaza sumarios para su siguiente etapa.',
                'windows' => [
                    ['title' => 'Listado de aprobacion', 'description' => 'Revision de sumarios pendientes de decision.'],
                ],
            ],
            'aprobacion-odcs' => [
                'title' => 'Aprobacion de ODC',
                'description' => 'Permite aprobar o rechazar ODC antes de pasar a pagos.',
                'windows' => [
                    ['title' => 'Listado de aprobacion', 'description' => 'ODC pendientes de decision por gerencia.'],
                ],
            ],
            'administracion-pagos-odc' => [
                'title' => 'Realizacion de Pagos ODC',
                'description' => 'Registra pagos y comprobantes de ordenes aprobadas para pago.',
                'windows' => [
                    ['title' => 'Pendientes', 'description' => 'ODC listas para ejecutar pago.'],
                    ['title' => 'Pagadas', 'description' => 'ODC con pago ya registrado.'],
                ],
            ],
            'facturas-compra' => [
                'title' => 'Facturas de Compra',
                'description' => 'Modulo de Finanzas para enviar factura a Administracion.',
                'windows' => [
                    ['title' => 'Facturas por enviar', 'description' => 'Facturas recibidas aun no enviadas a Administracion.'],
                    ['title' => 'Facturas enviadas', 'description' => 'Facturas ya remitidas a Administracion.'],
                    ['title' => 'Todas', 'description' => 'Vista global de facturas del modulo.'],
                ],
            ],
            'administracion-facturas' => [
                'title' => 'Administracion de Facturas',
                'description' => 'Modulo de Administracion para carga contable y respaldo.',
                'windows' => [
                    ['title' => 'Facturas recibidas', 'description' => 'Facturas pendientes de carga en base de datos.'],
                    ['title' => 'Facturas cargadas', 'description' => 'Facturas ya registradas en sistema.'],
                ],
            ],
            'pagos-procura' => [
                'title' => 'Pagos Procura',
                'description' => 'Consulta y seguimiento de estatus de pago en el flujo de procura.',
                'windows' => [
                    ['title' => 'Pendientes', 'description' => 'Documentos en espera de pago.'],
                    ['title' => 'Pagados', 'description' => 'Pagos confirmados por Finanzas.'],
                    ['title' => 'En transito', 'description' => 'Pagos que avanzaron a logistica/recepcion.'],
                ],
            ],
            'recepcion-materiales-nuevos' => [
                'title' => 'Recepcion de Materiales Nuevos',
                'description' => 'Registra entrada final de materiales en almacen.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'ODC listas para entrada o registro nuevo de productos.'],
                ],
            ],
            'recepcion-nuevos-materiales' => [
                'title' => 'Recepcion de Nuevos Materiales',
                'description' => 'Controla recepcion de materiales nuevos en flujo operativo.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Seguimiento de recepcion y transicion de materiales.'],
                ],
            ],
            'recepcion-productos-procura' => [
                'title' => 'Recepcion de Productos',
                'description' => 'Procura carga documento de recepcion y entrega a siguiente etapa.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Registros de recepcion de productos por procura.'],
                ],
            ],
            'inventory-movements' => [
                'title' => 'Registro de Materiales',
                'description' => 'Consulta movimientos de inventario de entradas y salidas.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Historico de movimientos con detalle y trazabilidad.'],
                ],
            ],
            'consultar-entradas' => [
                'title' => 'Consultar Entradas',
                'description' => 'Consulta historica de entradas registradas en almacen.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Filtros y detalle de entradas por fecha, usuario y producto.'],
                ],
            ],
            'consultar-salidas' => [
                'title' => 'Consultar Salidas',
                'description' => 'Consulta historica de salidas de materiales.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Filtros y detalle de salidas por area y responsable.'],
                ],
            ],
            'daily-withdrawals' => [
                'title' => 'Bandeja de Retiros Diarios',
                'description' => 'Gestiona solicitudes y ejecucion de retiros diarios.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Control de retiros por estatus, fecha y solicitante.'],
                ],
            ],
            'products' => [
                'title' => 'Almacen ADV',
                'description' => 'Catalogo de productos y existencias del almacen.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Gestion de productos, busqueda y mantenimiento de datos.'],
                ],
            ],
            'proveedores' => [
                'title' => 'Proveedores',
                'description' => 'Administra datos y contactos de proveedores.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Alta, consulta y actualizacion de proveedores.'],
                ],
            ],
            'categories' => [
                'title' => 'Categorias',
                'description' => 'Organiza clasificacion general de productos.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Gestion de categorias disponibles en catalogo.'],
                ],
            ],
            'departamentos' => [
                'title' => 'Departamentos',
                'description' => 'Configura departamentos de la organizacion.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Alta y mantenimiento de departamentos.'],
                ],
            ],
            'cargos' => [
                'title' => 'Cargos',
                'description' => 'Configura cargos para usuarios del sistema.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Alta y mantenimiento de cargos.'],
                ],
            ],
            'users' => [
                'title' => 'Usuarios',
                'description' => 'Gestion de cuentas de usuario y datos de acceso.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Crear, editar y administrar usuarios del sistema.'],
                ],
            ],
            'shield' => [
                'title' => 'Roles y Permisos',
                'description' => 'Administra roles, permisos y acceso por modulo.',
                'windows' => [
                    ['title' => 'Roles', 'description' => 'Configuracion de permisos por rol de usuario.'],
                ],
            ],
            'tickets' => [
                'title' => 'Tickets de Soporte',
                'description' => 'Registra incidencias y solicitudes de soporte tecnico.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Seguimiento de tickets por estado y prioridad.'],
                ],
            ],
            'impresoras' => [
                'title' => 'Impresoras',
                'description' => 'Configura y consulta impresoras disponibles.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Alta y mantenimiento de impresoras.'],
                ],
            ],
            'sku-code-rules' => [
                'title' => 'Codificacion SKU',
                'description' => 'Define reglas para la generacion de codigos SKU.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Gestion de reglas de codificacion de productos.'],
                ],
            ],
            'inspeccion-sumarios' => [
                'title' => 'Inspeccion de Sumarios',
                'description' => 'Valida y revisa sumarios en etapa de inspeccion.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Revision de sumarios antes de decisiones posteriores.'],
                ],
            ],
            'aprobaciones-compra' => [
                'title' => 'Aprobaciones de Compra',
                'description' => 'Aprueba solicitudes de compra antes de pasar a procura.',
                'windows' => [
                    ['title' => 'Listado principal', 'description' => 'Solicitudes pendientes de aprobacion.'],
                ],
            ],
        ];
    }

    private static function humanize(string $slug): string
    {
        $slug = str_replace(['-', '_'], ' ', $slug);

        return mb_strtoupper(mb_substr($slug, 0, 1)) . mb_substr($slug, 1);
    }
}
