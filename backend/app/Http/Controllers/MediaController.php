<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Adjunta a una publicación un archivo subido o un enlace de YouTube.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'archivo' => ['required_without:url', 'prohibits:url', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'url' => ['required_without:archivo', 'string', 'max:500'],
        ]);

        $post = Post::findOrFail($request->integer('post_id'));

        Gate::authorize('update', $post);

        if ($post->media()->count() >= Media::MAX_POR_POST) {
            return response()->json([
                'message' => 'Una publicación puede tener como máximo '.Media::MAX_POR_POST.' adjuntos.',
            ], 422);
        }

        $media = $request->hasFile('archivo')
            ? $this->guardarArchivo($post, $request->file('archivo'))
            : $this->guardarYoutube($post, $request->string('url'));

        return response()->json($media, 201);
    }

    /**
     * Descarga un archivo si el usuario puede ver su publicación.
     */
    public function download(Media $media): StreamedResponse
    {
        Gate::authorize('view', $media->post);

        abort_if($media->tipo === 'youtube' || ! Storage::exists($media->ruta), 404);

        return Storage::download($media->ruta, $media->nombre_original, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Quita un adjunto y borra su archivo.
     */
    public function destroy(Media $media): JsonResponse
    {
        Gate::authorize('update', $media->post);

        if ($media->tipo !== 'youtube') {
            Storage::delete($media->ruta);
        }

        $media->delete();

        return response()->json([
            'message' => 'Adjunto eliminado.',
        ]);
    }

    private function guardarArchivo(Post $post, UploadedFile $archivo): Media
    {
        $mime = $archivo->getMimeType();

        return $post->media()->create([
            'tipo' => $mime === 'application/pdf' ? 'pdf' : 'imagen',
            'ruta' => $archivo->store("media/{$post->id}"),
            'nombre_original' => mb_substr(basename($archivo->getClientOriginalName()), 0, 255),
            'mime_type' => $mime,
            'tamano' => $archivo->getSize(),
        ]);
    }

    private function guardarYoutube(Post $post, string $url): Media
    {
        $id = $this->idDeYoutube($url);

        if ($id === null) {
            throw ValidationException::withMessages([
                'url' => 'El enlace debe ser de un vídeo de YouTube.',
            ]);
        }

        return $post->media()->create([
            'tipo' => 'youtube',
            'ruta' => "https://www.youtube.com/watch?v={$id}",
        ]);
    }

    /**
     * Extrae el identificador de 11 caracteres de los formatos habituales de enlace de YouTube.
     */
    private function idDeYoutube(string $url): ?string
    {
        $partes = parse_url(trim($url));

        if (! $partes || ! in_array($partes['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = preg_replace('/^(www\.|m\.)/', '', strtolower($partes['host'] ?? ''));
        $ruta = $partes['path'] ?? '';
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim($ruta, '/');
        } elseif ($host === 'youtube.com' && $ruta === '/watch') {
            parse_str($partes['query'] ?? '', $consulta);
            $id = $consulta['v'] ?? null;
        } elseif ($host === 'youtube.com' && preg_match('#^/(shorts|embed)/([^/]+)#', $ruta, $coincidencia)) {
            $id = $coincidencia[2];
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
}
