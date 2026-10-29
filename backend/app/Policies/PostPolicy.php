<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Todos los usuarios con la cuenta activa ven todas las publicaciones.
     * Se deja aquí por si algún día hay un rol con menos permisos.
     */
    public function view(User $user, Post $post): Response
    {
        return Response::allow();
    }

    /**
     * Todos los usuarios con la cuenta activa (administradores y entrenadores) pueden publicar.
     */
    public function create(User $user): Response
    {
        return Response::allow();
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
