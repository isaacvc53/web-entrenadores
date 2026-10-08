<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new account, pending approval by an administrator.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
        ]);

        return response()->json([
            'message' => 'Cuenta creada. Un administrador debe aprobarla antes de que puedas iniciar sesión.',
            'user' => $user->refresh(),
        ], 201);
    }

    /**
     * Check the credentials and issue an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Email o contraseña incorrectos.',
            ], 401);
        }

        if (! $user->activo) {
            return response()->json([
                'message' => 'Tu cuenta está pendiente de aprobación por un administrador.',
            ], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada.',
        ]);
    }
}
