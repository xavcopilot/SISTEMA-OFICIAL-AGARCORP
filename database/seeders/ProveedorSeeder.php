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
            ],
            [
                'nombre' => 'Distribuidora Maxis C.A.',
                'rif' => 'J-31590248',
                'direccion' => 'Zona Industrial Castillito, Calle 4, Galpon 12',
                'ciudad' => 'Valencia',
                'email' => 'maxis.distribuidora@gmail.com',
                'contacto' => 'Andrea Salazar',
                'telefono' => '0424-5187602',
            ],
            [
                'nombre' => 'Insumos Delta 360 C.A.',
                'rif' => 'J-32764109',
                'direccion' => 'Av. Intercomunal, Centro Comercial Delta, Local 21',
                'ciudad' => 'Puerto La Cruz',
                'email' => 'delta360.insumos@gmail.com',
                'contacto' => 'Carlos Pereira',
                'telefono' => '0412-8451973',
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
