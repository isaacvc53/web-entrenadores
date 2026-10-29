<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    /**
     * Se puede comentar en cualquier publicación que se pueda ver.
     */
    public function create(User $user, Post $post): Response
    {
        return (new PostPolicy)->view($user, $post);
    }

    /**
     * Un comentario lo puede eliminar su autor o un administrador.
     */
    public function delete(User $user, Comment $comment): Response
    {
        return $user->rol === 'admin' || $comment->user_id === $user->id
            ? Response::allow()
            : Response::deny('Solo el autor o un administrador pueden eliminar este comentario.');
    }

    /**
     * Solo un administrador puede ocultar o volver a mostrar un comentario.
     */
    public function moderate(User $user, Comment $comment): Response
    {
        return $user->rol === 'admin'
            ? Response::allow()
            : Response::deny('Solo un administrador puede moderar comentarios.');
    }
}
