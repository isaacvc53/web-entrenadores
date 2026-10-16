<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['titulo', 'contenido', 'type_id', 'team_id', 'temporada', 'visibilidad'])]
class Post extends Model
{
    /**
     * Quién puede ver una publicación: solo entrenadores y administradores, o también los usuarios de consulta.
     */
    public const VISIBILIDADES = ['entrenadores', 'todos'];

    /**
     * Borra del disco los archivos de la publicación; el borrado en cascada de la base de datos solo borra las filas.
     */
    protected static function booted(): void
    {
        static::deleting(function (Post $post) {
            Storage::deleteDirectory("media/{$post->id}");
        });
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PostType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PostType::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Limita la consulta a las publicaciones que puede ver el usuario indicado.
     */
    public function scopeVisiblePara(Builder $query, User $user): void
    {
        if ($user->rol === 'consulta') {
            $query->where('visibilidad', 'todos');
        }
    }
}
