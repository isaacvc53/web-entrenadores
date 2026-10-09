<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Models\Post;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'ok';
    } catch (Throwable $e) {
        $database = 'error';
    }

    return response()->json([
        'status' => $database === 'ok' ? 'ok' : 'error',
        'database' => $database,
    ], $database === 'ok' ? 200 : 503);
});

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/categories', function () {
        return [
            'tipos' => Post::TIPOS,
            'visibilidades' => Post::VISIBILIDADES,
            'equipos' => Team::orderBy('nombre')->get(['id', 'nombre', 'categoria', 'temporada']),
        ];
    });

    Route::apiResource('posts', PostController::class);

    Route::middleware('rol:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{user}/approve', [UserController::class, 'approve']);
        Route::put('/users/{user}/deactivate', [UserController::class, 'deactivate']);
    });
});
