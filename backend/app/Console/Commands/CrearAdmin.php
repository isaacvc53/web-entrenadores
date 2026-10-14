<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:crear-admin')]
#[Description('Crea una cuenta de administrador ya activa')]
class CrearAdmin extends Command
{
    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $data = [
            'nombre' => $this->ask('Nombre'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Contraseña'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
        ];

        $validator = Validator::make($data, [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'rol' => 'admin',
            'activo' => true,
        ])->save();

        $this->info("Administrador creado: {$user->email}");

        return self::SUCCESS;
    }
}
