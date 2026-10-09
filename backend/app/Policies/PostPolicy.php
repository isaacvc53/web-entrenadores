<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Read-only users can only see posts that are visible to everyone.
     */
    public function view(User $user, Post $post): Response
    {
        return $user->rol !== 'consulta' || $post->visibilidad === 'todos'
            ? Response::allow()
            : Response::deny('No tienes permiso para ver esta publicación.');
    }

    /**
     * Coaches and admins can publish; read-only users cannot.
     */
    public function create(User $user): Response
    {
        return in_array($user->rol, ['admin', 'entrenador'], true)
            ? Response::allow()
            : Response::deny('No tienes permiso para publicar.');
    }

    /**
     * A post can be edited by its author or by an admin.
     */
    public function update(User $user, Post $post): Response
    {
        return $this->esAutorOAdmin($user, $post)
            ? Response::allow()
            : Response::deny('Solo el autor o un administrador pueden editar esta publicación.');
    }

    /**
     * A post can be deleted by its author or by an admin.
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
