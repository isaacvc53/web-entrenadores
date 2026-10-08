<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'accion', 'entidad', 'entidad_id'])]
class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_log';

    /**
     * The table only has a "fecha" column, filled in by the database.
     *
     * @var bool
     */
    public $timestamps = false;
}
