<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ImpresoraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run(): void
{
    $impresoras = [
        ['codigo' => 'ADV-HPCOLOR-1FC5', 'nombre' => 'ADV-HPCOLOR-1FC5 (THSIHO)'],
        ['codigo' => 'ADV-HPCOLOR-1F1B', 'nombre' => 'ADV-HPCOLOR-1F1B (ADC)'],
        ['codigo' => 'ADV-HPBYN-44F2',  'nombre' => 'ADV-HPBYN-44F2 (MTTO)'],
        ['codigo' => 'ADV-HPBYN-5478',  'nombre' => 'ADV-HPBYN-5478 (ADM)'],
        ['codigo' => 'ADV-HPBYN-6482',  'nombre' => 'ADV-HPBYN-6482 (THSIHO)'],
        ['codigo' => 'ADV-HPBYN-6344',  'nombre' => 'ADV-HPBYN-6344 (FIN)'],
        ['codigo' => 'ADV-HPBYN-3554',  'nombre' => 'ADV-HPBYN-3554 (FIN)'],
        ['codigo' => 'ADV-HPBYN-E1A8',  'nombre' => 'ADV-HPBYN-E1A8 (FIN)'],
        ['codigo' => 'ADV-CANONCOLOR-634C', 'nombre' => 'ADV-CANONCOLOR-634C (GEN)'],
        ['codigo' => 'ADV-HPBYN-P1102W-TH', 'nombre' => 'ADV-HPBYN-P1102W (TH)'],
        ['codigo' => 'ADV-HPBYN-P1102W-ALM', 'nombre' => 'ADV-HPBYN-P1102W (ALM)'],
    ];

    foreach ($impresoras as $impresora) {
        \App\Models\Impresora::updateOrCreate(
            ['codigo' => $impresora['codigo']], // Evita duplicados si lo corres dos veces
            ['nombre' => $impresora['nombre']]
        );
    }

    $this->command->info('✅ ¡Las impresoras de Agarcorp han sido cargadas!');
}
}
