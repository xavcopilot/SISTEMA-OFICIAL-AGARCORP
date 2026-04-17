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

        // 1. Definimos los roles y sus contraseñas (antes estaba en $departamentos)
        $roles = [
            'Procura'     => 'procura',
            'Almacen'     => 'almacen',
            'Talento Humano' => 'talentohumano',
            'A.I.T'       => 'ait',
            'Finanzas'    => 'finanzas',
        ];

        // creamos departamentos primero
        $this->call(DepartamentoSeeder::class);
        $this->call(CargoSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(SubcategorySeeder::class);
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

        foreach (['Alta Gerencia', 'Gerencia de Operaciones', 'Gerencia de Finanzas'] as $extraRole) {
            $roleModel = Role::firstOrCreate(['name' => $extraRole]);
            $roleModel->givePermissionTo($ticketPermissions);

            if ($extraRole === 'Gerencia de Finanzas') {
                $roleModel->givePermissionTo(array_merge(
                    $sumarioReviewPermissions,
                    $ordenCompraReadPermissions,
                    $ordenCompraEditPermissions,
                ));

                $roleModel->givePermissionTo(['ApprovePayment:Sumario']);
            }
        }

        // Mapa desde rol => nombre de departamento (según tu tabla numerada)
        $roleToDept = [
            'Procura' => 'ADMINISTRACIÓN',
            'Almacen' => 'ALMACEN',
            'Talento Humano' => 'TALENTO HUMANO',
            'A.I.T' => 'A.I.T',
            'Finanzas' => 'FINANZAS',
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
            'Finanzas' => [
                'name' => 'Vanessa',
                'email' => 'vanessa@agarven.com',
                'cargo' => 'Analista',
            ],
        ];

        foreach ($roles as $rol => $password) {
            // Creamos o buscamos el rol (Spatie Role)
            $roleModel = Role::firstOrCreate(['name' => $rol]);

            // Asignamos los permisos de Ticket a cada rol
            $roleModel->givePermissionTo($ticketPermissions);

            if (in_array($rol, ['Almacen', 'Procura'], true) && ! empty($solicitudCreatePermissions)) {
                $roleModel->givePermissionTo($solicitudCreatePermissions);
            }

            if ($rol === 'Procura' && ! empty($proveedorPermissions)) {
                $roleModel->givePermissionTo($proveedorPermissions);
            }

            if ($rol === 'Almacen' && ! empty($inventoryProductPermissions)) {
                $roleModel->givePermissionTo($inventoryProductPermissions);
            }

            if ($rol === 'Almacen' && ! empty($inventoryMovementPermissions)) {
                $roleModel->givePermissionTo($inventoryMovementPermissions);
            }

            if ($rol === 'Almacen') {
                $roleModel->givePermissionTo(array_merge(
                    $inventoryViewPermissions,
                    $categoryReadWritePermissions,
                ));
            }

            if ($rol === 'Procura') {
                $roleModel->givePermissionTo(array_merge(
                    $sumarioReviewPermissions,
                    $sumarioWriteExtraPermissions,
                    $ordenCompraReadPermissions,
                ));

                $roleModel->givePermissionTo([
                    'ProcessReception:OrdenCompra',
                    'SubmitValidation:Sumario',
                    'GenerateOdcs:Sumario',
                ]);
            }

            if ($rol === 'Finanzas') {
                $roleModel->givePermissionTo(array_merge(
                    $sumarioReviewPermissions,
                    $ordenCompraReadPermissions,
                    $ordenCompraEditPermissions,
                ));

                $roleModel->givePermissionTo(['ValidateFinance:Sumario']);
            }

            if ($rol === 'A.I.T') {
                $roleModel->givePermissionTo(array_merge(
                    $inventoryViewPermissions,
                    $categoryReadWritePermissions,
                    $categoryDeletePermissions,
                    $sumarioReviewPermissions,
                    $sumarioWriteExtraPermissions,
                    $ordenCompraReadPermissions,
                    $ordenCompraEditPermissions,
                    $ordenCompraDeletePermissions,
                ));

                $roleModel->givePermissionTo([
                    'Manage:Ticket',
                    'ProcessReception:OrdenCompra',
                    'SubmitValidation:Sumario',
                    'ValidateFinance:Sumario',
                    'ApprovePayment:Sumario',
                    'GenerateOdcs:Sumario',
                ]);
            }

            $override = $roleUserOverrides[$rol] ?? null;

            // Generamos el email de acceso de cada rol base (o usamos override)
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

        foreach ($executiveUsers as $executiveUser) {
            $departamentoId = Departamento::where('nombre', $executiveUser['departamento'])->value('id');
            $cargoId = Cargo::firstOrCreate(['nombre' => $executiveUser['cargo']])->id;
            $executiveRole = Role::firstOrCreate(['name' => $executiveUser['role']]);
            $executiveRole->givePermissionTo($ticketPermissions);

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

        // Alta Gerencia es el unico rol con permisos completos.
        $allPermissionNames = Permission::query()->pluck('name')->all();
        $superAdminRole = Role::firstOrCreate(['name' => 'Alta Gerencia']);
        if (! empty($allPermissionNames)) {
            $superAdminRole->syncPermissions($allPermissionNames);
        }

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
        // Usuario de prueba para demostraciones con acceso total al sistema.
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

        // A.I.T mantiene acceso a lo actual, pero sin heredar automaticamente
        // permisos futuros fuera de estos modulos.
        $aitAllowedSubjects = [
            'Ticket',
            'SolicitudCompra',
            'User',
            'Role',
            'Cargo',
            'Departamento',
            'Impresora',
            'Category',
            'InventoryMovement',
            'Sumario',
            'OrdenCompra',
        ];

        $aitPermissionNames = Permission::query()
            ->where(function ($query) use ($aitAllowedSubjects): void {
                foreach ($aitAllowedSubjects as $subject) {
                    $query->orWhere('name', 'like', "%:{$subject}");
                }
            })
            ->where('name', '!=', 'Create:Ticket')
            ->pluck('name')
            ->all();

        $aitRole = Role::firstOrCreate(['name' => 'A.I.T']);
        if (! empty($aitPermissionNames)) {
            $aitRole->syncPermissions($aitPermissionNames);
        }

        // Reforzamos permisos: reglas de codificacion solo para A.I.T y Alta Gerencia.
        $skuCodeRulePermissionNames = Permission::query()
            ->where('name', 'like', '%:SkuCodeRule')
            ->pluck('name')
            ->all();

        if (! empty($skuCodeRulePermissionNames)) {
            foreach (Role::query()->whereNotIn('name', ['A.I.T', 'Alta Gerencia'])->get() as $role) {
                $role->revokePermissionTo($skuCodeRulePermissionNames);
            }

            $aitRole->givePermissionTo($skuCodeRulePermissionNames);
        }

        // Ejecutar el seeder dedicado de impresoras (centralizado en su propio archivo)
        $this->call(ImpresoraSeeder::class);

        // --- 🚀 USUARIO TECNICO PRINCIPAL A.I.T ---
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

        // Se mantiene como usuario tecnico con permisos de gestion.
        $aitPrimaryUser->syncRoles([$aitPrimaryRole->name]);

        // ===== INICIO BLOQUE FIRMA DEFAULT GLOBAL =====
        // Homologa la clave de firma para pruebas de flujo en todos los usuarios.
        User::query()->update([
            'firma_password' => Hash::make($defaultSignaturePassword),
        ]);
        // ===== FIN BLOQUE FIRMA DEFAULT GLOBAL =====

        User::query()->update([
            'withdrawal_password' => Hash::make($defaultWithdrawalPassword),
        ]);

        echo "✅ Base de datos poblada: roles, departamentos, cargos, usuarios ejecutivos y admin A.I.T creados.\n";
    }
}