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
            'EQUIPO MEDICO',
            'SOLDADURA',
            'LABORATORIO',
            'HERRAMIENTAS',
            'UTENSILIOS DE COCINA',
            'PRODUCTOS QUIMICOS',
            'ILUMINACION',
            'SISTEMA DE SEGURIDAD',
            'EPP',
            'ELECTRONICA DE CONSUMO',
            'VEHICULO',
            'ALS',
        ];

        foreach ($names as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        $this->command?->info('Categorias de inventario generadas.');
    }
}
