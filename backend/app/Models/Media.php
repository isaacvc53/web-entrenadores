<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
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
     * Tamaño máximo, en KB, de una imagen o un PDF (10 MB).
     */
    public const MAX_KB_ARCHIVO = 10 * 1024;

    /**
     * Tamaño máximo, en KB, de un vídeo (120 MB). El servidor de OVH admite hasta 128 MB por archivo.
     */
    public const MAX_KB_VIDEO = 120 * 1024;

    /**
     * Horas que vale el enlace para reproducir un vídeo.
     */
    public const HORAS_ENLACE_VIDEO = 12;

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
    protected $appends = ['youtube_id', 'firma_video'];

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

    /**
     * Solo en los vídeos subidos: la parte final del enlace firmado para reproducirlo
     * ("expires=…&signature=…"). El reproductor del navegador no puede enviar el token,
     * así que el vídeo se pide con este enlace, que caduca a las HORAS_ENLACE_VIDEO horas.
     */
    protected function firmaVideo(): Attribute
    {
        return Attribute::get(function () {
            if ($this->tipo !== 'video' || ! $this->exists) {
                return null;
            }

            $enlace = URL::temporarySignedRoute(
                'media.stream',
                now()->addHours(self::HORAS_ENLACE_VIDEO),
                ['media' => $this->id],
                absolute: false,
            );

            return Str::after($enlace, '?');
        });
    }
}
