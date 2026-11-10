<?php

namespace App\Support;

use GdImage;

/**
 * Lectura y reducción de imágenes con GD. La usan la foto de perfil y las miniaturas de los adjuntos.
 */
class Imagen
{
    /**
     * Píxeles máximos de una imagen que se procesa, para no agotar la memoria
     * (24 millones: de sobra para una foto de móvil normal).
     */
    public const MAX_PIXELES = 24_000_000;

    /**
     * Si la imagen tiene más píxeles de los que se pueden procesar.
     */
    public static function demasiadoGrande(string $ruta): bool
    {
        [$ancho, $alto] = @getimagesize($ruta) ?: [0, 0];

        return $ancho * $alto > self::MAX_PIXELES;
    }

    /**
     * Abre una imagen JPG, PNG o WebP ya enderezada, o devuelve null si no se puede leer.
     */
    public static function abrir(string $ruta): ?GdImage
    {
        $tipo = @getimagesize($ruta)['mime'] ?? null;

        $imagen = match ($tipo) {
            'image/jpeg' => @imagecreatefromjpeg($ruta),
            'image/png' => @imagecreatefrompng($ruta),
            'image/webp' => @imagecreatefromwebp($ruta),
            default => false,
        };

        if (! $imagen) {
            return null;
        }

        return $tipo === 'image/jpeg' ? self::enderezar($imagen, $ruta) : $imagen;
    }

    /**
     * Crea una imagen nueva con fondo blanco, por si la original tenía partes transparentes (PNG o WebP).
     */
    public static function lienzo(int $ancho, int $alto): GdImage
    {
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        return $lienzo;
    }

    /**
     * Copia reducida de la imagen, sin recortar, con el lado mayor de "lado" píxeles como mucho, en JPG.
     * Devuelve null si la imagen no se puede leer o es demasiado grande para procesarla.
     */
    public static function miniatura(string $ruta, int $lado): ?string
    {
        if (self::demasiadoGrande($ruta) || ! $original = self::abrir($ruta)) {
            return null;
        }

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $escala = min(1, $lado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $miniatura = self::lienzo($nuevoAncho, $nuevoAlto);
        imagecopyresampled($miniatura, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return self::jpg($miniatura, 80);
    }

    /**
     * Guarda la imagen como JPG y devuelve su contenido. Al volver a guardarla se pierden
     * los datos ocultos del archivo original, como la ubicación GPS que añaden muchos móviles.
     */
    public static function jpg(GdImage $imagen, int $calidad = 85): string
    {
        ob_start();
        imagejpeg($imagen, null, $calidad);

        return ob_get_clean();
    }

    /**
     * Los móviles guardan muchas fotos "tumbadas" y anotan en el archivo cómo girarlas.
     * Como esa anotación se pierde al volver a guardar, se aplica el giro antes.
     */
    private static function enderezar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $orientacion = @exif_read_data($ruta)['Orientation'] ?? 1;

        $girada = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        return $girada ?: $imagen;
    }
}
