<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nombre', 'email', 'password_hash'])]
#[Hidden(['password_hash', 'foto'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Roles posibles (los mismos que la columna rol de la tabla users).
     */
    public const ROLES = ['admin', 'entrenador', 'consulta'];

    /**
     * La columna que guarda el hash de la contraseña.
     *
     * @var string
     */
    protected $authPasswordName = 'password_hash';

    /**
     * Atributos calculados que se añaden al JSON. La ruta de la foto no se envía,
     * solo si hay foto; la imagen se pide aparte, con el token.
     *
     * @var list<string>
     */
    protected $appends = ['tiene_foto'];

    /**
     * Si el usuario ha subido una foto de perfil.
     */
    protected function tieneFoto(): Attribute
    {
        return Attribute::get(fn () => $this->foto !== null);
    }

    /**
     * Conversiones de tipo de los atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'activo' => 'boolean',
        ];
    }
}
