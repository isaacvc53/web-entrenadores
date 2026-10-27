<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * Datos relacionados que acompañan a cada publicación en las respuestas.
     */
    private const RELACIONES = [
        'user:id,nombre',
        'type:id,nombre',
        'team:id,nombre,category_id',
        'team.category:id,nombre',
        'media',
    ];

    /**
     * Lista las publicaciones que el usuario puede ver, de la más reciente a la más antigua, con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'type_id' => ['sometimes', 'integer'],
            'team_id' => ['sometimes', 'integer'],
            'temporada' => ['sometimes', 'string', 'max:20'],
            'user_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $posts = Post::query()
            ->visiblePara($request->user())
            ->with(self::RELACIONES)
            ->withCount($this->contadorDeComentarios())
            ->when($filtros['type_id'] ?? null, fn ($query, $typeId) => $query->where('type_id', $typeId))
            ->when($filtros['team_id'] ?? null, fn ($query, $teamId) => $query->where('team_id', $teamId))
            // La temporada puede estar escrita en la publicación o venir de su equipo.
            ->when($filtros['temporada'] ?? null, fn ($query, $temporada) => $query->where(
                fn ($query) => $query->where('temporada', $temporada)
                    ->orWhereHas('team', fn ($query) => $query->where('temporada', $temporada))
            ))
            ->when($filtros['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filtros['q'] ?? null, fn ($query, $q) => $query->whereLike('titulo', '%'.$q.'%'))
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

        $post = new Post($data);
        $post->user_id = $request->user()->id;
        $post->save();

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

        $post->update($request->validate($this->reglas(parcial: true)));

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
            'visibilidad' => ['sometimes', Rule::in(Post::VISIBILIDADES)],
        ];
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
