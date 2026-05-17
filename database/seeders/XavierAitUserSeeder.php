<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class XavierAitUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $departamentoId = Departamento::firstOrCreate([
            'nombre' => 'A.I.T',
        ])->id;

        $cargoId = Cargo::firstOrCreate([
            'nombre' => 'Técnico',
        ])->id;

        $role = Role::firstOrCreate([
            'name' => 'A.I.T',
        ]);

        $user = User::updateOrCreate(
            ['email' => 'xavierdpdev@gmail.com'],
            [
                'name' => 'Xavier Prado',
                'password' => Hash::make('Xavidev17'),
                'firma_password' => Hash::make('firma'),
                'email_verified_at' => now(),
                'departamento_id' => $departamentoId,
                'cargo_id' => $cargoId,
            ]
        );

        $user->syncRoles([$role->name]);
    }
}