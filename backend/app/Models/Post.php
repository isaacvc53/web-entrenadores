<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['titulo', 'contenido', 'type_id', 'team_id', 'temporada'])]
class Post extends Model
{
    /**
     * Borra del disco los archivos de la publicación; el borrado en cascada de la base de datos solo borra las filas.
     */
    protected static function booted(): void
    {
        static::deleting(function (Post $post) {
            Storage::deleteDirectory("media/{$post->id}");
        });

        // Las etiquetas de los entrenadores que solo usaba esta publicación ya no hacen falta.
        static::deleted(fn () => Tag::borrarSinUso());
    }

    /**
     * Filtra por tipo, equipo, temporada, etiqueta, autor y texto del título; los filtros que no llegan no se aplican.
     * Lo usan la lista de publicaciones y la de recursos.
     *
     * @param  Builder<Post>  $query
     * @param  array<string, mixed>  $filtros
     */
    public function scopeFiltrar(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['type_id'] ?? null, fn ($query, $typeId) => $query->where('type_id', $typeId))
            ->when($filtros['team_id'] ?? null, fn ($query, $teamId) => $query->where('team_id', $teamId))
            // La temporada puede estar escrita en la publicación o venir de su equipo.
            ->when($filtros['temporada'] ?? null, fn ($query, $temporada) => $query->where(
                fn ($query) => $query->where('temporada', $temporada)
                    ->orWhereHas('team', fn ($query) => $query->where('temporada', $temporada))
            ))
            ->when($filtros['tag_id'] ?? null, fn ($query, $tagId) => $query->whereHas(
                'tags', fn ($query) => $query->whereKey($tagId)
            ))
            ->when($filtros['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filtros['q'] ?? null, fn ($query, $q) => $query->whereLike('titulo', '%'.$q.'%'));
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
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
}
