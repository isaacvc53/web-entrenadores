<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registra una cuenta nueva, pendiente de que la apruebe un administrador.
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
     * Comprueba las credenciales y entrega un token de la API.
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

        // Se aprovecha para borrar sus sesiones ya caducadas, que solo ocupan sitio en la base de datos.
        $user->tokens()
            ->where('created_at', '<', now()->subMinutes(config('sanctum.expiration')))
            ->delete();

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * Cambia la contraseña del usuario conectado, comprobando antes la actual.
     * Se cierran sus otras sesiones abiertas, pero no la de esta petición.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:password_actual', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_actual'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'password_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->forceFill(['password_hash' => $data['password']])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json([
            'message' => 'Contraseña cambiada.',
        ]);
    }

    /**
     * Anula el token usado en esta petición.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada.',
        ]);
    }
}
