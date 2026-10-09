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
     * List the posts the user can see, newest first, with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'tipo' => ['sometimes', Rule::in(Post::TIPOS)],
            'team_id' => ['sometimes', 'integer'],
            'temporada' => ['sometimes', 'string', 'max:20'],
            'user_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $posts = Post::query()
            ->visiblePara($request->user())
            ->with(['user:id,nombre', 'team:id,nombre,categoria'])
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['team_id'] ?? null, fn ($query, $teamId) => $query->where('team_id', $teamId))
            ->when($filtros['temporada'] ?? null, fn ($query, $temporada) => $query->where('temporada', $temporada))
            ->when($filtros['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filtros['q'] ?? null, fn ($query, $q) => $query->whereLike('titulo', '%'.$q.'%'))
            ->latest()
            ->latest('id')
            ->paginate(20);

        return response()->json($posts);
    }

    /**
     * Create a post owned by the authenticated user.
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
     * Show a single post.
     */
    public function show(Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return response()->json($this->conRelaciones($post));
    }

    /**
     * Update a post; only the fields that are sent are changed.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $post->update($request->validate($this->reglas(parcial: true)));

        return response()->json($this->conRelaciones($post));
    }

    /**
     * Delete a post.
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
     * Validation rules shared by store and update.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(bool $parcial = false): array
    {
        $obligatorio = $parcial ? 'sometimes' : 'required';

        return [
            'titulo' => [$obligatorio, 'string', 'max:255'],
            'contenido' => ['nullable', 'string', 'max:20000'],
            'tipo' => [$obligatorio, Rule::in(Post::TIPOS)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'temporada' => ['nullable', 'string', 'max:20'],
            'visibilidad' => ['sometimes', Rule::in(Post::VISIBILIDADES)],
        ];
    }

    private function conRelaciones(Post $post): Post
    {
        return $post->load(['user:id,nombre', 'team:id,nombre,categoria']);
    }
}
