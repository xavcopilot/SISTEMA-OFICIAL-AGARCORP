<?php   
# Este comando de consola permite reiniciar la clave de un usuario especificando su correo.
# Ejecutar en terminal php artisan user:reset-password usuario@empresa.com
# Definir Correo a Actualizar antes de ejecutar el comando.

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password
        {email : Correo del usuario a resetear}
        {--password=Temporal123456 : Nueva clave en texto plano}
        {--with-signature : Tambien actualiza firma_password con la misma clave}
        {--force : Omite confirmacion interactiva}';

    protected $description = 'Reinicia la clave de un usuario por correo para soporte tecnico.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $plainPassword = (string) $this->option('password');
        $withSignature = (bool) $this->option('with-signature');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Debes indicar un correo valido.');

            return self::INVALID;
        }

        if ($plainPassword === '') {
            $this->error('La opcion --password no puede estar vacia.');

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error('Usuario no encontrado para el correo: ' . $email);

            return self::FAILURE;
        }

        if (! (bool) $this->option('force')) {
            $message = 'Se reiniciara la clave de ' . $user->email;
            if ($withSignature) {
                $message .= ' y tambien firma_password';
            }

            if (! $this->confirm($message . '. Deseas continuar?', true)) {
                $this->warn('Operacion cancelada por el usuario.');

                return self::INVALID;
            }
        }

        $hash = Hash::make($plainPassword);
        $user->password = $hash;

        if ($withSignature) {
            $user->firma_password = $hash;
        }

        $user->save();

        $this->info('Clave reiniciada correctamente.');
        $this->line('Usuario: ' . $user->email);
        $this->line('ID: ' . (string) $user->id);
        $this->line('firma_password actualizada: ' . ($withSignature ? 'SI' : 'NO'));

        return self::SUCCESS;
    }
}
