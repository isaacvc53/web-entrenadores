<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Etiqueta para clasificar publicaciones. Las "fijas" las pone el administrador;
 * el resto las crean los entrenadores al publicar y se borran solas cuando ya no las usa nadie.
 */
#[Fillable(['nombre', 'fija'])]
#[Hidden(['pivot'])]
class Tag extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fija' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags');
    }

    /**
     * Primero las de la lista fija y después las demás, cada grupo por orden alfabético.
     */
    public function scopeOrdenadas(Builder $query): void
    {
        $query->orderByDesc('fija')->orderBy('nombre');
    }

    /**
     * Borra las etiquetas creadas por los entrenadores que ya no tiene ninguna publicación.
     */
    public static function borrarSinUso(): void
    {
        static::query()->where('fija', false)->doesntHave('posts')->delete();
    }
}
