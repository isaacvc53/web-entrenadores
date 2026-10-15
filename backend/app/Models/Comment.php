<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contenido'])]
class Comment extends Model
{
    /**
     * Longitud máxima de un comentario.
     */
    public const MAX_CARACTERES = 2000;

    /**
     * Conversiones de tipo de los atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moderado' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
