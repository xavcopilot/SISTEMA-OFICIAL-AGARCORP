<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DailyWithdrawalSetupSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Almacen Recepcion',
        ]);

        $user = User::updateOrCreate([
            'email' => 'recepcion@agarven.com',
        ], [
            'name' => 'Terminal Recepcion',
            'email' => 'recepcion@agarven.com',
            'password' => Hash::make('recepcion'),
            'withdrawal_password' => Hash::make('1234'),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role->name]);

        $this->command?->info('DailyWithdrawalSetupSeeder ejecutado: rol "Almacen Recepcion" y usuario de recepcion configurados.');
    }
}
