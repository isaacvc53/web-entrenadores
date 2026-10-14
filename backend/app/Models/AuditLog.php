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
}
