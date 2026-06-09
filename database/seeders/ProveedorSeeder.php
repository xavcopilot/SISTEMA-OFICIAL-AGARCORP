<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $proveedores = [
            [
                'nombre' => 'Tecno Suministros Orion',
                'rif' => 'J-29485736',
                'direccion' => 'Av. Principal de Los Cortijos, Torre Empresarial Orion, Piso 3',
                'ciudad' => 'Caracas',
                'email' => 'orion.suministros@gmail.com',
                'contacto' => 'Luis Mendoza',
                'telefono' => '0414-6729345',
                'banco' => 'Banco de Venezuela',
                'numero_cuenta' => '01020012345678901234',
                'tipo_documento' => 'J',
                'documento' => '29485736',
                'beneficiario_nombre_apellido' => 'Tecno Suministros Orion',
            ],
            [
                'nombre' => 'Distribuidora Maxis C.A.',
                'rif' => 'J-31590248',
                'direccion' => 'Zona Industrial Castillito, Calle 4, Galpon 12',
                'ciudad' => 'Valencia',
                'email' => 'maxis.distribuidora@gmail.com',
                'contacto' => 'Andrea Salazar',
                'telefono' => '0424-5187602',
                'banco' => 'Banesco',
                'numero_cuenta' => '01340098765432109876',
                'tipo_documento' => 'J',
                'documento' => '31590248',
                'beneficiario_nombre_apellido' => 'Distribuidora Maxis C.A.',
            ],
            [
                'nombre' => 'Insumos Delta 360 C.A.',
                'rif' => 'J-32764109',
                'direccion' => 'Av. Intercomunal, Centro Comercial Delta, Local 21',
                'ciudad' => 'Puerto La Cruz',
                'email' => 'delta360.insumos@gmail.com',
                'contacto' => 'Carlos Pereira',
                'telefono' => '0412-8451973',
                'banco' => 'Banco Mercantil',
                'numero_cuenta' => '01050045678901234567',
                'tipo_documento' => 'J',
                'documento' => '32764109',
                'beneficiario_nombre_apellido' => 'Insumos Delta 360 C.A.',
            ],
        ];

        foreach ($proveedores as $proveedor) {
            Proveedor::updateOrCreate(
                ['rif' => $proveedor['rif']],
                $proveedor
            );
        }
    }
}
