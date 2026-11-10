<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    /**
     * Datos relacionados que acompañan a cada publicación en las respuestas.
     */
    private const RELACIONES = [
        'user:id,nombre,foto',
        'type:id,nombre',
        'team:id,nombre,category_id',
        'team.category:id,nombre',
        'media',
        'tags:id,nombre,fija',
    ];

    /**
     * Máximo de etiquetas por publicación y de caracteres por etiqueta.
     */
    private const MAX_ETIQUETAS = 10;

    private const MAX_LARGO_ETIQUETA = 30;

    /**
     * Lista las publicaciones, de la más reciente a la más antigua, con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'type_id' => ['sometimes', 'integer'],
            'team_id' => ['sometimes', 'integer'],
            'temporada' => ['sometimes', 'string', 'max:20'],
            'tag_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $posts = Post::query()
            ->with(self::RELACIONES)
            ->withCount($this->contadorDeComentarios())
            ->filtrar($filtros)
            ->latest()
            ->latest('id')
            ->paginate(20);

        return response()->json($posts);
    }

    /**
     * Crea una publicación cuyo autor es el usuario conectado.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Post::class);

        $data = $request->validate($this->reglas());

        $post = DB::transaction(function () use ($data, $request) {
            $post = new Post(Arr::except($data, 'etiquetas'));
            $post->user_id = $request->user()->id;
            $post->save();

            $this->guardarEtiquetas($post, $data['etiquetas'] ?? [], $request->user());

            return $post;
        });

        return response()->json($this->conRelaciones($post->refresh()), 201);
    }

    /**
     * Muestra una publicación.
     */
    public function show(Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return response()->json($this->conRelaciones($post));
    }

    /**
     * Edita una publicación; solo cambian los campos que se envían.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate($this->reglas(parcial: true));

        DB::transaction(function () use ($post, $data, $request) {
            $post->update(Arr::except($data, 'etiquetas'));

            // Si no se envían las etiquetas, se quedan como estaban.
            if (array_key_exists('etiquetas', $data)) {
                $this->guardarEtiquetas($post, $data['etiquetas'] ?? [], $request->user());
            }
        });

        return response()->json($this->conRelaciones($post));
    }

    /**
     * Elimina una publicación.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'accion' => 'eliminar_post',
            'entidad' => 'posts',
            'entidad_id' => $post->id,
        ]);

        return response()->json([
            'message' => 'Publicación eliminada.',
        ]);
    }

    /**
     * Reglas de validación comunes a crear y editar.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(bool $parcial = false): array
    {
        $obligatorio = $parcial ? 'sometimes' : 'required';

        return [
            'titulo' => [$obligatorio, 'string', 'max:255'],
            'contenido' => ['nullable', 'string', 'max:20000'],
            'type_id' => [$obligatorio, 'integer', 'exists:post_types,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'temporada' => ['nullable', 'string', 'max:20'],
            // Nombres de etiqueta; las que no existen se crean.
            'etiquetas' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_ETIQUETAS],
            'etiquetas.*' => ['required', 'string', 'max:'.self::MAX_LARGO_ETIQUETA, 'distinct:ignore_case'],
        ];
    }

    /**
     * Deja en la publicación exactamente estas etiquetas. Las que aún no existen se crean
     * a nombre del usuario, y las de los entrenadores que se quedan sin uso se borran.
     *
     * @param  array<int, string>  $nombres
     */
    private function guardarEtiquetas(Post $post, array $nombres, User $user): void
    {
        $ids = collect($nombres)
            // Sin espacios de más, para que "Balón  parado" y "Balón parado" sean la misma.
            ->map(fn (string $nombre) => preg_replace('/\s+/u', ' ', trim($nombre)))
            ->filter()
            ->map(function (string $nombre) use ($user) {
                // La base de datos no distingue mayúsculas ni tildes al comparar,
                // así que "tactica" encuentra la etiqueta "Táctica" que ya existe.
                $etiqueta = Tag::firstWhere('nombre', $nombre);

                if (! $etiqueta) {
                    $etiqueta = new Tag(['nombre' => $nombre]);
                    $etiqueta->user_id = $user->id;
                    $etiqueta->save();
                }

                return $etiqueta->id;
            })
            ->unique();

        $post->tags()->sync($ids);

        Tag::borrarSinUso();
    }

    private function conRelaciones(Post $post): Post
    {
        return $post
            ->load(self::RELACIONES)
            ->loadCount($this->contadorDeComentarios());
    }

    /**
     * Cuenta solo los comentarios visibles, como "comentarios_count".
     *
     * @return array<string, \Closure>
     */
    private function contadorDeComentarios(): array
    {
        return ['comments as comentarios_count' => fn ($query) => $query->where('moderado', false)];
    }
}
