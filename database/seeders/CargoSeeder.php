<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        $cargos = [
            'Analista',
            'Lider',
            'Tecnico',
            'Coordinador',
            'Vicepresidente',
            'Gerente General',
            'Gerente de Finanzas',
            'Gerente de Operaciones',
            'Almacenista',
            'Lider de Procura',
        ];

        foreach ($cargos as $nombre) {
            Cargo::firstOrCreate(['nombre' => $nombre]);
        }
        $this->command->info('✅ Cargos generados.');
    }
}
