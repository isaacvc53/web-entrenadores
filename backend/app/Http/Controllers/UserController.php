<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users, optionally filtered by whether they are active.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activo' => ['sometimes', 'boolean'],
        ]);

        $users = User::query()
            ->when(array_key_exists('activo', $data), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($users);
    }

    /**
     * Approve an account so that its owner can log in.
     */
    public function approve(Request $request, User $user): JsonResponse
    {
        $user->forceFill(['activo' => true])->save();

        $this->audit($request, 'aprobar_usuario', $user);

        return response()->json([
            'message' => 'Cuenta aprobada.',
            'user' => $user,
        ]);
    }

    /**
     * Deactivate an account and revoke all of its tokens.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        $user->forceFill(['activo' => false])->save();
        $user->tokens()->delete();

        $this->audit($request, 'desactivar_usuario', $user);

        return response()->json([
            'message' => 'Cuenta desactivada.',
            'user' => $user,
        ]);
    }

    /**
     * Record an administrative action on a user.
     */
    private function audit(Request $request, string $accion, User $user): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'accion' => $accion,
            'entidad' => 'users',
            'entidad_id' => $user->id,
        ]);
    }
}
