<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['post_id', 'tipo', 'ruta', 'nombre_original', 'mime_type', 'tamano'])]
#[Hidden(['ruta'])]
class Media extends Model
{
    /**
     * Número máximo de adjuntos por publicación.
     */
    public const MAX_POR_POST = 10;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'media';

    /**
     * Atributos calculados que se añaden al convertir el modelo a JSON.
     *
     * @var list<string>
     */
    protected $appends = ['youtube_id'];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * El identificador del vídeo, solo en los enlaces de YouTube.
     */
    protected function youtubeId(): Attribute
    {
        return Attribute::get(fn () => $this->tipo === 'youtube' ? Str::after($this->ruta, 'v=') : null);
    }
}
