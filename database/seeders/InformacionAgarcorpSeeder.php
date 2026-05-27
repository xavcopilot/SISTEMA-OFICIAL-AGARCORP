<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InformacionAgarcorpSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('informacion_impresa')->updateOrInsert(
            ['id' => 1],
            [
                'razon_social' => 'AGARCORP DE VENEZUELA, C.A',
                'rif' => 'J-30693407-3',
                'direccion_fiscal' => 'AV 77 EDIF 5 JULIO PISO 4 OF D/4 SECTOR TIERRA NEGRA MARACAIBO, ZULIA',
                'telefono_principal' => '0261-7184260',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
