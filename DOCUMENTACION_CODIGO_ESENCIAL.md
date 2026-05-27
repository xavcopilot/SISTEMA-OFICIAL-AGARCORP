# Documentacion esencial del codigo (Laravel + Filament)

Objetivo: ubicar rapido donde tocar el sistema sin revisar todas las carpetas que genera el framework.

## 1) Mapa rapido: donde esta cada cosa importante

### 1.1 Logica visual del panel (Filament)
- app/Providers/Filament/AgarcorpPanelProvider.php
  - Configura panel agarcorp, ruta base /agarcorp, logo, colores, widgets y middlewares.
  - Ejemplo real: brandName, brandLogo y hooks visuales del topbar.

- app/Filament/Resources/
  - Cada modulo del panel vive aqui.
  - Ejemplo: Departamentos, Cargos, Tickets, SolicitudesCompra, Impresoras, Users, Roles.

- app/Filament/Resources/*/Schemas/*Form.php
  - Define campos del formulario (crear/editar).
  - Ejemplo: app/Filament/Resources/Departamentos/Schemas/DepartamentoForm.php

- app/Filament/Resources/*/Tables/*Table.php
  - Define columnas, filtros y acciones de la tabla del listado.
  - Ejemplo: app/Filament/Resources/Departamentos/Tables/DepartamentosTable.php

- app/Filament/Resources/*/Pages/
  - Paginas del recurso (List, Create, Edit, View).
  - Ejemplo: app/Filament/Resources/Departamentos/Pages/ListDepartamentos.php

### 1.2 Vistas Blade (fuera del CRUD de Filament)
- resources/views/
  - Plantillas html/blade personalizadas.
  - Ejemplos reales:
    - resources/views/filament/login-header.blade.php (cabecera del login)
    - resources/views/solicitudes-compra/formato-pdf.blade.php (formato de salida)

### 1.3 Rutas web
- routes/web.php
  - Define endpoints HTTP, redirecciones y rutas para exportaciones o formatos.
  - Ejemplo real: /tickets/export y /solicitudes-compra/{solicitudCompra}/formato

### 1.4 Logica de negocio
- app/Models/
  - Modelos Eloquent con fillable, relaciones y casts.
  - Ejemplo: app/Models/Departamento.php

- app/Http/Controllers/
  - Controladores para procesos especiales (por ejemplo generar PDF).
  - Ejemplo: app/Http/Controllers/SolicitudCompraFormatoController.php

- app/Providers/AppServiceProvider.php
  - Arranque global de la app (observers, reglas globales, hooks de Filament, notificaciones).

### 1.5 Base de datos
- database/migrations/
  - Estructura de tablas y cambios de esquema.
  - Ejemplo: database/migrations/2026_03_03_100000_create_departamentos_table.php

- database/seeders/
  - Datos iniciales o de reinicio (catalogos, roles base, admin base).
  - Ejemplo: database/seeders/DatabaseSeeder.php

### 1.6 Seguridad y permisos
- app/Policies/
  - Reglas de autorizacion por accion (view, create, update, etc.).
  - Ejemplo: app/Policies/DepartamentoPolicy.php

- config/filament-shield.php
  - Config de Filament Shield (super admin, generacion de permisos/policies).

### 1.7 Conexion a base de datos
- .env (archivo local, no se sube al repo)
  - Credenciales reales de conexion.

- .env.example
  - Plantilla de variables para nuevos entornos.
  - Claves importantes: DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.

### 1.8 Reinicio de codigos/base
- reinicio.bat
  - Ejecuta reinicio completo: migrate:fresh --seed, shield:generate --all y limpieza de cache de permisos.


## 2) Como crear un modulo nuevo (flujo minimo recomendado)

Ejemplo: modulo Proveedores.

### Paso 1. Crear migracion
Comando:

```bash
php artisan make:migration create_proveedores_table
```

Luego editar archivo en database/migrations para definir columnas.

### Paso 2. Ejecutar migracion

```bash
php artisan migrate
```

### Paso 3. Crear modelo
Comando:

```bash
php artisan make:model Proveedor
```

Luego completar en app/Models/Proveedor.php:
- fillable
- relaciones
- casts (si aplica)

### Paso 4. Crear resource de Filament
Comando sugerido:

```bash
php artisan make:filament-resource Proveedor
```

Se generaran archivos en app/Filament/Resources/Proveedores/:
- ProveedorResource.php
- Schemas/ProveedorForm.php
- Tables/ProveedoresTable.php
- Pages/ListProveedores.php
- Pages/CreateProveedor.php
- Pages/EditProveedor.php

### Paso 5. Definir formulario y tabla
- En Schemas/ProveedorForm.php: campos, validaciones, labels.
- En Tables/ProveedoresTable.php: columnas, busqueda, filtros, acciones.

### Paso 6. Permisos y policy
Si usan Shield, generar permisos/policies:

```bash
php artisan shield:generate --all
```

Verificar policy en app/Policies/ProveedorPolicy.php.

### Paso 7. Datos base (opcional)
Si el modulo necesita catalogo inicial:

1. Crear seeder:

```bash
php artisan make:seeder ProveedorSeeder
```

2. Registrarlo en database/seeders/DatabaseSeeder.php con $this->call(ProveedorSeeder::class);

3. Correr seed:

```bash
php artisan db:seed
```


## 3) Ejemplo real del proyecto: modulo Departamentos

- Migracion:
  - database/migrations/2026_03_03_100000_create_departamentos_table.php

- Modelo:
  - app/Models/Departamento.php

- Resource Filament:
  - app/Filament/Resources/Departamentos/DepartamentoResource.php

- Formulario:
  - app/Filament/Resources/Departamentos/Schemas/DepartamentoForm.php

- Tabla:
  - app/Filament/Resources/Departamentos/Tables/DepartamentosTable.php

- Paginas:
  - app/Filament/Resources/Departamentos/Pages/

- Permisos:
  - app/Policies/DepartamentoPolicy.php

- Seeder:
  - database/seeders/DepartamentoSeeder.php


## 4) Comandos utiles para operacion diaria

- Levantar app local (si ya esta configurada):

```bash
php artisan serve
```

- Ejecutar migraciones pendientes:

```bash
php artisan migrate
```

- Reiniciar base y sembrar datos:

```bash
php artisan migrate:fresh --seed
```

- Regenerar permisos de Shield:

```bash
php artisan shield:generate --all
```

- Limpiar cache de permisos:

```bash
php artisan permission:cache-reset
```


## 5) Regla practica para ubicar cambios rapido

- Si cambia como se ve o funciona un CRUD del panel admin:
  - app/Filament/Resources/... (Resource, Form, Table, Pages)

- Si cambia configuracion visual global del panel:
  - app/Providers/Filament/AgarcorpPanelProvider.php

- Si cambia estructura de datos:
  - database/migrations + app/Models

- Si cambia datos base al reiniciar:
  - database/seeders + reinicio.bat

- Si cambia acceso/roles/permisos:
  - app/Policies + config/filament-shield.php

- Si cambia una vista blade especifica:
  - resources/views/...


## 6) Recomendacion para tu jefe (lectura corta)

Orden sugerido de lectura para supervisar cambios:
1. app/Filament/Resources (que modulo fue tocado)
2. database/migrations (si hubo cambios de tabla)
3. database/seeders (si hubo cambios de datos base)
4. app/Policies y config/filament-shield.php (si hubo cambios de permisos)
5. app/Providers/Filament/AgarcorpPanelProvider.php (si hubo cambios visuales globales)

Con eso se cubre la mayor parte de cambios funcionales del sistema sin entrar a todas las carpetas del framework.
