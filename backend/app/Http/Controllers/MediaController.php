<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Support\Imagen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Lado mayor de las miniaturas, en píxeles: se ven a unos 300 px, y así salen nítidas en pantallas de alta resolución.
     */
    private const LADO_MINIATURA = 640;

    /**
     * Lista los archivos y vídeos de todas las publicaciones, del más reciente al más antiguo (sección Recursos).
     * Se filtran por clase de adjunto, por los datos de su publicación o por el nombre del archivo o el título.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'tipo' => ['sometimes', Rule::in(['imagen', 'pdf', 'youtube'])],
            'type_id' => ['sometimes', 'integer'],
            'team_id' => ['sometimes', 'integer'],
            'temporada' => ['sometimes', 'string', 'max:20'],
            'tag_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $media = Media::query()
            ->with([
                'post:id,titulo,user_id,type_id,team_id,temporada,created_at',
                'post.user:id,nombre,foto',
                'post.type:id,nombre',
                'post.team:id,nombre,category_id,temporada',
                'post.team.category:id,nombre',
            ])
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->whereHas('post', fn ($query) => $query->filtrar(Arr::except($filtros, ['tipo', 'q'])))
            // El texto se busca en el nombre del archivo y en el título de su publicación.
            ->when($filtros['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($query) => $query->whereLike('nombre_original', '%'.$q.'%')
                    ->orWhereHas('post', fn ($query) => $query->whereLike('titulo', '%'.$q.'%'))
            ))
            ->latest()
            ->latest('id')
            ->paginate(30);

        return response()->json($media);
    }

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
     * Devuelve una copia pequeña de una imagen, para las miniaturas: pesa mucho menos que el original.
     * Se crea la primera vez que se pide y se guarda junto al original; si no se puede crear, se envía el original.
     */
    public function thumbnail(Media $media): Response
    {
        Gate::authorize('view', $media->post);

        abort_if($media->tipo !== 'imagen' || ! Storage::exists($media->ruta), 404);

        $miniatura = $this->rutaDeMiniatura($media);

        if (! Storage::exists($miniatura)) {
            $contenido = Imagen::miniatura(Storage::path($media->ruta), self::LADO_MINIATURA);

            if ($contenido === null) {
                $miniatura = $media->ruta;
            } else {
                Storage::put($miniatura, $contenido);
            }
        }

        // La miniatura de un adjunto no cambia nunca, así que el navegador puede guardarla un día.
        return Storage::response($miniatura, null, [
            'Cache-Control' => 'private, max-age=86400',
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
            Storage::delete([$media->ruta, $this->rutaDeMiniatura($media)]);
        }

        $media->delete();

        return response()->json([
            'message' => 'Adjunto eliminado.',
        ]);
    }

    /**
     * Las miniaturas van en una subcarpeta de la de la publicación, así se borran con ella.
     */
    private function rutaDeMiniatura(Media $media): string
    {
        return "media/{$media->post_id}/miniaturas/".pathinfo($media->ruta, PATHINFO_FILENAME).'.jpg';
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
