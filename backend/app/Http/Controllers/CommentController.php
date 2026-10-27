<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Lista los comentarios de una publicación, del más antiguo al más reciente.
     * Los ocultos por moderación solo los ve un administrador.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $comentarios = $post->comments()
            ->with('user:id,nombre,foto')
            ->when($request->user()->rol !== 'admin', fn ($query) => $query->where('moderado', false))
            ->oldest()
            ->oldest('id')
            ->get();

        return response()->json($comentarios);
    }

    /**
     * Añade un comentario a una publicación.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('create', [Comment::class, $post]);

        $data = $request->validate([
            'contenido' => ['required', 'string', 'max:'.Comment::MAX_CARACTERES],
        ]);

        $comentario = new Comment($data);
        $comentario->post_id = $post->id;
        $comentario->user_id = $request->user()->id;
        $comentario->save();

        return response()->json($comentario->refresh()->load('user:id,nombre,foto'), 201);
    }

    /**
     * Elimina un comentario.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        if ($comment->user_id !== $request->user()->id) {
            $this->audit($request, 'eliminar_comentario', $comment);
        }

        return response()->json([
            'message' => 'Comentario eliminado.',
        ]);
    }

    /**
     * Oculta o vuelve a mostrar un comentario.
     */
    public function moderate(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('moderate', $comment);

        $data = $request->validate([
            'moderado' => ['required', 'boolean'],
        ]);

        $comment->forceFill(['moderado' => $data['moderado']])->save();

        $this->audit($request, $comment->moderado ? 'ocultar_comentario' : 'mostrar_comentario', $comment);

        return response()->json([
            'message' => $comment->moderado ? 'Comentario ocultado.' : 'Comentario visible de nuevo.',
            'comment' => $comment->load('user:id,nombre,foto'),
        ]);
    }

    /**
     * Registra una acción de moderación sobre un comentario.
     */
    private function audit(Request $request, string $accion, Comment $comment): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'accion' => $accion,
            'entidad' => 'comments',
            'entidad_id' => $comment->id,
        ]);
    }
}
