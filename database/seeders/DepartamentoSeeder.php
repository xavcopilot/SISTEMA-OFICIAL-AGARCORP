<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;

class DepartamentoSeeder extends Seeder
{
    public function run(): void
    {
        // Seed en el orden específico para que los IDs sigan la numeración requerida
        $nombres = [
            'OPERACIONES',        // 1
            'ADMINISTRACIÓN',     // 2
            'MANTENIMIENTO',      // 3
            'SIHO-A',             // 4
            'CALIDAD',            // 5
            'CONTROL Y GESTION',  // 6
            'GERENCIA',           // 7
            'FINANZAS',           // 8
            'CAMPO OPERACIONAL',  // 9
            'ALMACEN',            // 10
            'TALENTO HUMANO',     // 11
            'A.I.T',              // 12 
            'SERVICIO TECNICO',   // 13
            'ALS',                // 14
        ];

        foreach ($nombres as $nombre) {
            Departamento::firstOrCreate(['nombre' => $nombre]);
        }

        $this->command->info('✅ Departamentos generados.');
    }
}
