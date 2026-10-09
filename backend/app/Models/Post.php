<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['titulo', 'contenido', 'tipo', 'team_id', 'temporada', 'visibilidad'])]
class Post extends Model
{
    /**
     * The kinds of content a post can hold.
     */
    public const TIPOS = ['video', 'entrenamiento', 'tarea', 'jugada', 'recurso'];

    /**
     * Who can see a post: only coaches and admins, or read-only users too.
     */
    public const VISIBILIDADES = ['entrenadores', 'todos'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Limit the query to the posts the given user is allowed to see.
     */
    public function scopeVisiblePara(Builder $query, User $user): void
    {
        if ($user->rol === 'consulta') {
            $query->where('visibilidad', 'todos');
        }
    }
}
