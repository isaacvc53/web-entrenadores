<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'accion', 'entidad', 'entidad_id'])]
class AuditLog extends Model
{
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'audit_log';

    /**
     * La tabla solo tiene la columna "fecha", que rellena la base de datos.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Registra una acción de un usuario sobre un elemento.
     */
    public static function registrar(User $user, string $accion, ?Model $elemento = null, ?string $entidad = null): void
    {
        static::create([
            'user_id' => $user->id,
            'accion' => $accion,
            'entidad' => $elemento?->getTable() ?? $entidad,
            'entidad_id' => $elemento?->getKey(),
        ]);
    }
}
