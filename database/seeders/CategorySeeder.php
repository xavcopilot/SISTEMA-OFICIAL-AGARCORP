<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'FABRICACION',
            'CONSUMIBLES',
            'INFORMATICA',
            'TELECOMUNICACIONES',
            'MEDICAMENTOS',
            'EQUIPO_MEDICO',
            'SOLDADURA',
            'LABORATORIO',
            'HERRAMIENTAS_',
            'UTENSILIOS_DE_COCINA',
            'PRODUCTOS_QUIMICOS',
            'ILUMINACION',
            'SISTEMA_DE_SEGURIDAD',
            'EPP',
            'ELECTRONICA_DE_CONSUMO',
            'VEHICULO',
            'ALS',
        ];

        foreach ($names as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        $this->command?->info('Categorias de inventario generadas.');
    }
}
