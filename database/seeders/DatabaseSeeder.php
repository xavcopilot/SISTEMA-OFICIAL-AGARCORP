<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\ImpresoraSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultWithdrawalPassword = '1726';
        $defaultSignaturePassword = 'firma';

        $executiveUsers = [
            [
                'name' => 'Wilman Fai',
                'email' => 'wilman.fai@agarven.com',
                'password' => 'AltaGerencia.WF_2026',
                'departamento' => 'GERENCIA',
                'cargo' => 'Gerente General',
                'role' => 'Alta Gerencia',
            ],
            [
                'name' => 'Jonny Pateiro',
                'email' => 'jonny.pateiro@agarven.com',
                'password' => 'AltaGerencia.JP_2026',
                'departamento' => 'GERENCIA',
                'cargo' => 'Vicepresidente',
                'role' => 'Alta Gerencia',
            ],
            [
                'name' => 'Richard Marin',
                'email' => 'richard.marin@agarven.com',
                'password' => 'GOperaciones.RM_2026',
                'departamento' => 'OPERACIONES',
                'cargo' => 'Gerente de Operaciones',
                'role' => 'Gerencia de Operaciones',
            ],
            [
                'name' => 'Cristina Fontanilla',
                'email' => 'cristina.fontanilla@agarven.com',
                'password' => 'GFinanzas.CF_2026',
                'departamento' => 'FINANZAS',
                'cargo' => 'Gerente de Finanzas',
                'role' => 'Gerencia de Finanzas',
            ],
        ];

        // 1. Definimos los roles y sus contraseñas.
        $roles = [
            'Procura'     => 'procura',
            'Almacen'     => 'almacen',
            'Talento Humano' => 'talentohumano',
            'A.I.T'       => 'ait',
            'Validador Finanzas' => 'validadorfinanzas',
            'Finanzas Pagos'    => 'finanzas',
            'Administracion' => 'administracion',
            'Mantenimiento' => 'mantenimiento',
            'S.I.H.O'     => 'siho',
        ];

        // creamos departamentos primero
        $this->call(DepartamentoSeeder::class);
        $this->call(CargoSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(SubcategorySeeder::class);
        $this->call(ProveedorSeeder::class);
        $this->call(SkuCodeRuleSeeder::class);
        $this->call(DailyWithdrawalSetupSeeder::class);

        // Genera permisos de Shield para todos los recursos/paginas/widgets
        // antes de asignarlos a roles en una base nueva.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions',
            '--panel' => 'agarcorp',
            '--no-interaction' => true,
        ]);

        // Permisos relacionados a Ticket (se crearán si no existen)
        $ticketPermissions = [
            'ViewAny:Ticket',
            'View:Ticket',
            'Create:Ticket',
            'Update:Ticket',
            'Delete:Ticket',
            'Restore:Ticket',
            'ForceDelete:Ticket',
            'ForceDeleteAny:Ticket',
            'RestoreAny:Ticket',
            'Replicate:Ticket',
            'Reorder:Ticket',
        ];

        foreach ($ticketPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $customControlPermissions = [
            'Manage:Ticket',
            'ProcessReception:OrdenCompra',
            'SubmitValidation:Sumario',
            'ValidateFinance:Sumario',
            'ApprovePayment:Sumario',
            'GenerateOdcs:Sumario',
        ];

        foreach ($customControlPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $solicitudCreatePermissions = Permission::query()
            ->whereIn('name', [
                'ViewAny:SolicitudCompra',
                'View:SolicitudCompra',
                'Create:SolicitudCompra',
            ])
            ->pluck('name')
            ->all();

        $inventoryProductPermissions = Permission::query()
            ->where('name', 'like', '%:Product')
            ->pluck('name')
            ->all();

        $inventoryMovementPermissions = Permission::query()
            ->where('name', 'like', '%:InventoryMovement')
            ->pluck('name')
            ->all();

        $proveedorPermissions = Permission::query()
            ->where('name', 'like', '%:Proveedor')
            ->pluck('name')
            ->all();

        $categoryReadWritePermissions = Permission::query()
            ->whereIn('name', [
                'ViewAny:Category',
                'View:Category',
                'Create:Category',
                'Update:Category',
            ])
            ->pluck('name')
            ->all();

        $categoryDeletePermissions = Permission::query()
            ->whereIn('name', ['Delete:Category'])
            ->pluck('name')
            ->all();

        $inventoryViewPermissions = Permission::query()
            ->whereIn('name', ['ViewAny:InventoryMovement', 'View:InventoryMovement'])
            ->pluck('name')
            ->all();

        $sumarioReviewPermissions = Permission::query()
            ->whereIn('name', ['ViewAny:Sumario', 'View:Sumario', 'Update:Sumario'])
            ->pluck('name')
            ->all();

        $sumarioWriteExtraPermissions = Permission::query()
            ->whereIn('name', ['Create:Sumario', 'Delete:Sumario'])
            ->pluck('name')
            ->all();

        $ordenCompraReadPermissions = Permission::query()
            ->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra'])
            ->pluck('name')
            ->all();

        $ordenCompraEditPermissions = Permission::query()
            ->whereIn('name', ['Update:OrdenCompra'])
            ->pluck('name')
            ->all();

        $ordenCompraDeletePermissions = Permission::query()
            ->whereIn('name', ['Delete:OrdenCompra'])
            ->pluck('name')
            ->all();

        foreach (['Alta Gerencia', 'Gerencia de Operaciones'] as $extraRole) {
            $roleModel = Role::firstOrCreate(['name' => $extraRole]);
            $roleModel->givePermissionTo($ticketPermissions);
        }

        // Mapa desde rol => nombre de departamento (según tu tabla numerada)
        $roleToDept = [
            'Procura' => 'ADMINISTRACIÓN',
            'Almacen' => 'ALMACEN',
            'Talento Humano' => 'TALENTO HUMANO',
            'A.I.T' => 'A.I.T',
            'Validador Finanzas' => 'FINANZAS',
            'Finanzas Pagos' => 'FINANZAS',
            'Administracion' => 'ADMINISTRACIÓN',
            'Mantenimiento' => 'MANTENIMIENTO',
            'S.I.H.O' => 'S.I.H.O',
        ];

        $roleUserOverrides = [
            'Procura' => [
                'name' => 'Hectlys Piña',
                'email' => 'hectlys.pina@agarven.com',
                'cargo' => 'Analista',
            ],
            'Almacen' => [
                'name' => 'Daniela Carrasco',
                'email' => 'daniela.carrasco@agarven.com',
                'cargo' => 'Almacenista',
            ],
            'Finanzas Pagos' => [
                'name' => 'Finanzas Pagos',
                'email' => 'finanzas@agarven.com',
                'cargo' => 'Analista',
            ],
            'Validador Finanzas' => [
                'name' => 'Vanessa',
                'email' => 'vanessa@agarven.com',
                'cargo' => 'Validadora Finanzas',
            ],
            'Administracion' => [
                'name' => 'administracion',
                'email' => 'administracion@agarven.com',
                'cargo' => 'Analista',
            ],
        ];

        foreach ($roles as $rol => $password) {
            $roleModel = Role::firstOrCreate(['name' => $rol]);
            $roleModel->givePermissionTo($ticketPermissions);

            $override = $roleUserOverrides[$rol] ?? null;

            $emailName = str_replace('.', '', strtolower($rol));
            $email = $override['email'] ?? ($emailName . "@agarven.com");
            $name = $override['name'] ?? $rol;
            
            $deptName = $override['departamento'] ?? ($roleToDept[$rol] ?? null);
            $deptId = null;

            if ($deptName) {
                $deptId = Departamento::firstOrCreate(['nombre' => $deptName])->id;
            }
            $cargoId = null;

            if (! empty($override['cargo'])) {
                $cargoId = Cargo::firstOrCreate(['nombre' => $override['cargo']])->id;
            }

            $user = User::updateOrCreate([
                'email' => $email,
            ], [
                'name'            => $name,
                'password'        => Hash::make($password),
                'firma_password'  => Hash::make($password),
                'email_verified_at'=> now(),
                'departamento_id' => $deptId,
                'cargo_id'        => $cargoId,
            ]);

            $user->syncRoles([$roleModel->name]);
        }

        // ── Permisiones exactas por rol según especificaciones ──

        // Helper para obtener permisos de un modelo
        $permsFor = function (string $model, array $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete']): array {
            return Permission::query()
                ->where(function ($q) use ($model, $actions): void {
                    foreach ($actions as $action) {
                        $q->orWhere('name', "{$action}:{$model}");
                    }
                })
                ->pluck('name')
                ->all();
        };

        // ===== GERENCIA DE FINANZAS =====
        // Escritorio, Tickets, Notificaciones, Solicitudes de Compra (crear)
        // Aprobaciones: Aprobación de Solicitudes, Aprobación de Sumarios, Aprobación de ODC
        // Pagos: Administración de Pagos ODC
        // Dashboard: Dashboard de Finanzas
        // NO ve: Sumario de Cotizaciones, Ordenes de Compra (pero sí puede aprobar sumarios)
        $gerenciaFinanzasPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Aprobación de Sumarios (permiso de aprobación sin lectura completa del módulo)
            ['ApprovePayment:Sumario'],
            // Aprobación de ODC (permiso para aprobar ODCs)
            Permission::query()->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra', 'Update:OrdenCompra'])->pluck('name')->all(),
            // Administración de Pagos ODC
            Permission::query()->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra'])->pluck('name')->all(),
        )));
        Role::firstOrCreate(['name' => 'Gerencia de Finanzas'])->syncPermissions($gerenciaFinanzasPermissions);

        // ===== PROCURA =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear y Aprobar
        // Proveedores: CRUD completo
        // Compras: Sumario de Cotizaciones, Ordenes de Compra
        // Productos: Recepción de Productos
        // Dashboard: Dashboard de Procura
        $procuraExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Aprobaciones de Compra
            Permission::query()->whereIn('name', ['ViewAny:SolicitudCompra', 'View:SolicitudCompra', 'Update:SolicitudCompra'])->pluck('name')->all(),
            // Proveedores
            $proveedorPermissions,
            // Sumario de Cotizaciones (CRUD completo)
            $sumarioReviewPermissions,
            $sumarioWriteExtraPermissions,
            ['ProcessReception:OrdenCompra', 'SubmitValidation:Sumario', 'GenerateOdcs:Sumario'],
            // Ordenes de Compra
            $ordenCompraReadPermissions,
            $ordenCompraEditPermissions,
            // Recepción de Productos
            $inventoryProductPermissions,
        )));
        Role::firstOrCreate(['name' => 'Procura'])->syncPermissions($procuraExactPermissions);

        // ===== ALMACEN =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear y Aprobar
        // Inventario: Consultar Entradas, Consultar Salidas, Registro de Materiales, Almacen ADV, Dashboard
        // Configuraciones de Inventario: Categorias, Codificacion SKU
        // Retiros y Compras: Bandeja de Retiros Diarios, Recepcion de Materiales Nuevos
        $almacenExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Aprobaciones de Compra
            Permission::query()->whereIn('name', ['ViewAny:SolicitudCompra', 'View:SolicitudCompra', 'Update:SolicitudCompra'])->pluck('name')->all(),
            // Inventario completo
            $inventoryProductPermissions,
            $inventoryMovementPermissions,
            $inventoryViewPermissions,
            // Categorias (CRUD sin delete)
            $categoryReadWritePermissions,
            // Codificacion SKU
            Permission::query()->whereIn('name', ['ViewAny:SkuCodeRule', 'View:SkuCodeRule'])->pluck('name')->all(),
            // Retiros Diarios
            Permission::query()->whereIn('name', ['ViewAny:DailyWithdrawal', 'View:DailyWithdrawal'])->pluck('name')->all(),
            // Recepcion de Materiales Nuevos
            Permission::query()->whereIn('name', ['ViewAny:InventoryMovement', 'View:InventoryMovement', 'Create:InventoryMovement'])->pluck('name')->all(),
        )));
        Role::firstOrCreate(['name' => 'Almacen'])->syncPermissions($almacenExactPermissions);

        // ===== A.I.T =====
        // Escritorio, Tickets, Notificaciones
        // Configuraciones: Usuarios, Roles, Departamentos, Cargos, Impresoras
        // Solicitudes de Compra: Crear
        $aitExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Usuarios
            $permsFor('User', ['ViewAny', 'View', 'Create', 'Update']),
            // Roles
            $permsFor('Role', ['ViewAny', 'View']),
            // Departamentos
            $permsFor('Departamento', ['ViewAny', 'View', 'Create', 'Update']),
            // Cargos
            $permsFor('Cargo', ['ViewAny', 'View', 'Create', 'Update']),
            // Impresoras
            $permsFor('Impresora', ['ViewAny', 'View', 'Create', 'Update', 'Delete']),
            // Categorias
            $categoryReadWritePermissions,
            // Inventario (solo lectura)
            $inventoryViewPermissions,
            // Sumario y ODC (solo lectura para soporte)
            $sumarioReviewPermissions,
            $ordenCompraReadPermissions,
            // Permisos custom de gestion de tickets
            ['Manage:Ticket'],
        )));
        Role::firstOrCreate(['name' => 'A.I.T'])->syncPermissions($aitExactPermissions);

        // ===== VALIDADOR FINANZAS =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        // Validaciones: Inspección de Sumarios, Inspección de ODC
        $validadorFinanzasExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Inspección de Sumarios
            $sumarioReviewPermissions,
            ['ValidateFinance:Sumario'],
            // Inspección de ODC
            $ordenCompraReadPermissions,
        )));
        Role::firstOrCreate(['name' => 'Validador Finanzas'])->syncPermissions($validadorFinanzasExactPermissions);

        // ===== FACTURAS PAGOS =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        // Pagos: Realización de Pagos ODC, Facturas de Compra
        $finanzasPagosExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Realización de Pagos ODC
            Permission::query()->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra', 'Update:OrdenCompra'])->pluck('name')->all(),
            // Facturas de Compra
            Permission::query()->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra'])->pluck('name')->all(),
        )));
        Role::firstOrCreate(['name' => 'Finanzas Pagos'])->syncPermissions($finanzasPagosExactPermissions);

        // ===== ADMINISTRACION =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        // Facturas y Retenciones: Administración de Facturas
        $administracionExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Administración de Facturas
            Permission::query()->whereIn('name', ['ViewAny:OrdenCompra', 'View:OrdenCompra'])->pluck('name')->all(),
        )));
        Role::firstOrCreate(['name' => 'Administracion'])->syncPermissions($administracionExactPermissions);

        // ===== GERENCIA DE OPERACIONES =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear (no puede elegirse a sí mismo como aprobador)
        // Aprobaciones de Compra: Aprobar de otros
        $gerenciaOperacionesExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
            // Aprobaciones de Compra
            Permission::query()->whereIn('name', ['ViewAny:SolicitudCompra', 'View:SolicitudCompra', 'Update:SolicitudCompra'])->pluck('name')->all(),
        )));
        Role::firstOrCreate(['name' => 'Gerencia de Operaciones'])->syncPermissions($gerenciaOperacionesExactPermissions);

        // ===== TALENTO HUMANO =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        $talentoHumanoExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
        )));
        Role::firstOrCreate(['name' => 'Talento Humano'])->syncPermissions($talentoHumanoExactPermissions);

        // ===== MANTENIMIENTO =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        $mantenimientoExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
        )));
        Role::firstOrCreate(['name' => 'Mantenimiento'])->syncPermissions($mantenimientoExactPermissions);

        // ===== S.I.H.O =====
        // Escritorio, Tickets, Notificaciones
        // Solicitudes de Compra: Crear
        $sihoExactPermissions = array_values(array_unique(array_merge(
            $ticketPermissions,
            $solicitudCreatePermissions,
        )));
        Role::firstOrCreate(['name' => 'S.I.H.O'])->syncPermissions($sihoExactPermissions);

        // ===== ALTA GERENCIA =====
        // Escritorio, Tickets, Notificaciones
        // Dashboards: Almacen, Finanzas, Procura
        // Configuraciones: Usuarios, Roles, Departamentos, Cargos, Impresoras
        // Permisos completos en todo el sistema
        $allPermissionNames = Permission::query()->pluck('name')->all();
        $superAdminRole = Role::firstOrCreate(['name' => 'Alta Gerencia']);
        if (! empty($allPermissionNames)) {
            $superAdminRole->syncPermissions($allPermissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ===== CREACION DE USUARIOS EJECUTIVOS =====
        foreach ($executiveUsers as $executiveUser) {
            $departamentoId = Departamento::where('nombre', $executiveUser['departamento'])->value('id');
            $cargoId = Cargo::firstOrCreate(['nombre' => $executiveUser['cargo']])->id;
            $executiveRole = Role::firstOrCreate(['name' => $executiveUser['role']]);

            $user = User::updateOrCreate([
                'email' => $executiveUser['email'],
            ], [
                'name' => $executiveUser['name'],
                'password' => Hash::make($executiveUser['password']),
                'firma_password' => Hash::make($executiveUser['password']),
                'email_verified_at' => now(),
                'departamento_id' => $departamentoId,
                'cargo_id' => $cargoId,
            ]);

            $user->syncRoles([$executiveRole->name]);
        }

        // ===== USUARIO ADMIN LEGACY =====
        $adminRole = Role::query()->where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([
                'Manage:Ticket',
                'ProcessReception:OrdenCompra',
                'SubmitValidation:Sumario',
                'ValidateFinance:Sumario',
                'ApprovePayment:Sumario',
                'GenerateOdcs:Sumario',
            ]);
        }

        // ===== INICIO BLOQUE DEMO (ELIMINABLE) =====
        $demoDepartamentoId = Departamento::firstOrCreate(['nombre' => 'PRUEBA'])->id;
        $demoCargoId = Cargo::firstOrCreate(['nombre' => 'PRUEBA'])->id;
        $demoRole = Role::firstOrCreate(['name' => 'Demo Prueba']);

        if (! empty($allPermissionNames)) {
            $demoRole->syncPermissions($allPermissionNames);
        }

        $demoUser = User::updateOrCreate(
            ['email' => 'prueba@gmail.com'],
            [
                'name' => 'Usuario Prueba',
                'password' => Hash::make('prueba'),
                'firma_password' => Hash::make('prueba'),
                'email_verified_at' => now(),
                'departamento_id' => $demoDepartamentoId,
                'cargo_id' => $demoCargoId,
            ]
        );

        $demoUser->syncRoles([$demoRole->name, 'Alta Gerencia', 'Almacen']);
        // ===== FIN BLOQUE DEMO (ELIMINABLE) =====

        // ===== REFORZAR SKU CODE RULE SOLO PARA A.I.T, ALTA GERENCIA Y ALMACEN =====
        $skuCodeRulePermissionNames = Permission::query()
            ->where('name', 'like', '%:SkuCodeRule')
            ->pluck('name')
            ->all();

        if (! empty($skuCodeRulePermissionNames)) {
            foreach (Role::query()->whereNotIn('name', ['A.I.T', 'Alta Gerencia', 'Almacen'])->get() as $role) {
                $role->revokePermissionTo($skuCodeRulePermissionNames);
            }
        }

        // ===== EJECUTAR SEEDER DE IMPRESORAS =====
        $this->call(ImpresoraSeeder::class);

        // ===== USUARIO TECNICO PRINCIPAL A.I.T =====
        $aitPrimaryRole = Role::where('name', 'A.I.T')->first();

        $aitPrimaryUser = User::updateOrCreate(
            ['email' => 'xavierdpdev@gmail.com'],
            [
                'name'             => 'Xavier Prado',
                'password'         => Hash::make('Xavidev17'),
                'cargo_id'         => Cargo::firstOrCreate(['nombre' => 'Técnico'])->id,
                'firma_password'   => Hash::make('firma'),
                'email_verified_at'=> now(),
                'departamento_id'  => Departamento::where('nombre', 'A.I.T')->value('id'),
            ]
        );

        $aitPrimaryUser->syncRoles([$aitPrimaryRole->name]);

        // ===== USUARIO TECNICO SECUNDARIO A.I.T =====
        $aitSecondaryUser = User::updateOrCreate(
            ['email' => 'Gabriel.carrasco@agarven.com'],
            [
                'name'             => 'Gabriel Carrasco',
                'password'         => Hash::make('1212'),
                'cargo_id'         => Cargo::firstOrCreate(['nombre' => 'Técnico'])->id,
                'firma_password'   => Hash::make('firma'),
                'email_verified_at'=> now(),
                'departamento_id'  => Departamento::where('nombre', 'A.I.T')->value('id'),
            ]
        );

        $aitSecondaryUser->syncRoles([$aitPrimaryRole->name]);

        // ===== FIRMA DEFAULT GLOBAL =====
        User::query()->update([
            'firma_password' => Hash::make($defaultSignaturePassword),
        ]);

        User::query()->update([
            'withdrawal_password' => Hash::make($defaultWithdrawalPassword),
        ]);

        echo "✅ Base de datos poblada: roles, departamentos, cargos, usuarios ejecutivos y admin A.I.T creados.\n";
    }
}