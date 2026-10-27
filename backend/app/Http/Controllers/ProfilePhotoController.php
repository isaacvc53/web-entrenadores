<?php

namespace App\Http\Controllers;

use App\Models\User;
use GdImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProfilePhotoController extends Controller
{
    /**
     * Lado de la foto guardada, en píxeles (cuadrada).
     */
    private const LADO = 256;

    /**
     * Píxeles máximos de la foto original, para no agotar la memoria al procesarla
     * (24 millones: de sobra para una foto de móvil normal).
     */
    private const MAX_PIXELES = 24_000_000;

    /**
     * Sube o cambia la foto de perfil del usuario que ha iniciado sesión.
     *
     * La foto no se guarda tal cual: se recorta en cuadrado, se reduce y se vuelve a
     * guardar como JPG. Así ocupa poco y se pierden los datos ocultos del archivo,
     * como la ubicación GPS que añaden muchos móviles.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $user = $request->user();
        $contenido = $this->procesar($request->file('foto'));

        // Nombre nuevo cada vez, para que nadie vea la foto anterior guardada en caché.
        $ruta = "fotos/{$user->id}-".Str::random(16).'.jpg';
        Storage::put($ruta, $contenido);

        $anterior = $user->foto;
        $user->forceFill(['foto' => $ruta])->save();

        if ($anterior) {
            Storage::delete($anterior);
        }

        return response()->json([
            'message' => 'Foto de perfil actualizada.',
            'user' => $user,
        ]);
    }

    /**
     * Quita la foto de perfil del usuario que ha iniciado sesión.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->foto) {
            Storage::delete($user->foto);
            $user->forceFill(['foto' => null])->save();
        }

        return response()->json([
            'message' => 'Foto de perfil quitada.',
            'user' => $user,
        ]);
    }

    /**
     * Devuelve la foto de perfil de un usuario. Cualquiera con sesión puede verla,
     * porque sale junto a sus publicaciones y comentarios.
     */
    public function show(User $user): Response
    {
        abort_unless($user->foto && Storage::exists($user->foto), 404);

        return Storage::response($user->foto, null, [
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Recorta la imagen en cuadrado desde el centro, la reduce y la devuelve como JPG.
     */
    private function procesar(UploadedFile $archivo): string
    {
        [$ancho, $alto] = getimagesize($archivo->getRealPath()) ?: [0, 0];

        if ($ancho * $alto > self::MAX_PIXELES) {
            throw ValidationException::withMessages([
                'foto' => 'La foto tiene demasiados píxeles. Prueba con una más pequeña.',
            ]);
        }

        $original = match ($archivo->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($archivo->getRealPath()),
            'image/png' => imagecreatefrompng($archivo->getRealPath()),
            'image/webp' => imagecreatefromwebp($archivo->getRealPath()),
            default => false,
        };

        if (! $original) {
            throw ValidationException::withMessages([
                'foto' => 'No se ha podido leer la imagen.',
            ]);
        }

        $original = $this->enderezar($original, $archivo);

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $lado = min($ancho, $alto);

        $foto = imagecreatetruecolor(self::LADO, self::LADO);
        // Fondo blanco, por si la imagen tenía partes transparentes (PNG o WebP).
        imagefill($foto, 0, 0, imagecolorallocate($foto, 255, 255, 255));
        imagecopyresampled(
            $foto, $original,
            0, 0, intdiv($ancho - $lado, 2), intdiv($alto - $lado, 2),
            self::LADO, self::LADO, $lado, $lado,
        );

        ob_start();
        imagejpeg($foto, null, 85);

        return ob_get_clean();
    }

    /**
     * Los móviles guardan muchas fotos "tumbadas" y anotan en el archivo cómo girarlas.
     * Como esa anotación se pierde al volver a guardar, se aplica el giro antes.
     */
    private function enderezar(GdImage $imagen, UploadedFile $archivo): GdImage
    {
        if ($archivo->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        $orientacion = @exif_read_data($archivo->getRealPath())['Orientation'] ?? 1;

        $girada = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        return $girada ?: $imagen;
    }
}
