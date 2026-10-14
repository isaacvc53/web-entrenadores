<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Los usuarios de consulta solo ven las publicaciones visibles para todos.
     */
    public function view(User $user, Post $post): Response
    {
        return $user->rol !== 'consulta' || $post->visibilidad === 'todos'
            ? Response::allow()
            : Response::deny('No tienes permiso para ver esta publicación.');
    }

    /**
     * Entrenadores y administradores pueden publicar; los usuarios de consulta no.
     */
    public function create(User $user): Response
    {
        return in_array($user->rol, ['admin', 'entrenador'], true)
            ? Response::allow()
            : Response::deny('No tienes permiso para publicar.');
    }

    /**
     * Una publicación la puede editar su autor o un administrador.
     */
    public function update(User $user, Post $post): Response
    {
        return $this->esAutorOAdmin($user, $post)
            ? Response::allow()
            : Response::deny('Solo el autor o un administrador pueden editar esta publicación.');
    }

    /**
     * Una publicación la puede eliminar su autor o un administrador.
     */
    public function delete(User $user, Post $post): Response
    {
        return $this->esAutorOAdmin($user, $post)
            ? Response::allow()
            : Response::deny('Solo el autor o un administrador pueden eliminar esta publicación.');
    }

    private function esAutorOAdmin(User $user, Post $post): bool
    {
        return $user->rol === 'admin' || $post->user_id === $user->id;
    }
}
