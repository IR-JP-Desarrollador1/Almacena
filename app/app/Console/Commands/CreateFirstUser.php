<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateFirstUser extends Command
{
    protected $signature = 'app:create-first-user';

    protected $description = 'Create the first active application account when no users exist';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('Ya existen usuarios. Esta operación solo está disponible para la configuración inicial.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nombres'));
        $lastName = trim((string) $this->ask('Apellidos'));
        $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));
        $phone = trim((string) $this->ask('Teléfono (opcional)')) ?: null;

        if (
            $name === ''
            || mb_strlen($name) > 255
            || $lastName === ''
            || mb_strlen($lastName) > 255
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($email) > 255
            || ($phone !== null && mb_strlen($phone) > 30)
        ) {
            $this->components->error('Ingresa nombres, apellidos y correo válidos; el teléfono es opcional.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña inicial');
        $confirmation = (string) $this->secret('Confirma la contraseña');

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->components->error('La contraseña debe tener al menos 12 caracteres y coincidir con su confirmación.');

            return self::FAILURE;
        }

        $seedExitCode = $this->call('db:seed', [
            '--class' => AccessControlSeeder::class,
            '--no-interaction' => true,
        ]);

        if ($seedExitCode !== self::SUCCESS) {
            $this->components->error('No se pudieron preparar los perfiles y permisos. No se creó la cuenta inicial.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $lastName, $email, $phone, $password) {
            $user = User::create([
                'name' => $name,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
        });

        $this->components->info('Cuenta inicial creada. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
