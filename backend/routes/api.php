<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostTypeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostType;
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
            'categorias' => Category::ordenadas()->get(['id', 'nombre', 'orden']),
            'tipos' => PostType::ordenados()->get(['id', 'nombre', 'orden']),
            'visibilidades' => Post::VISIBILIDADES,
            'equipos' => Team::with('category:id,nombre,orden')
                ->orderByDesc('temporada')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'category_id', 'temporada']),
        ];
    });

    Route::apiResource('posts', PostController::class);

    Route::post('/media', [MediaController::class, 'store']);
    Route::get('/media/{media}/download', [MediaController::class, 'download']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);

    Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::put('/comments/{comment}/moderate', [CommentController::class, 'moderate']);

    Route::middleware('rol:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{user}/approve', [UserController::class, 'approve']);
        Route::put('/users/{user}/deactivate', [UserController::class, 'deactivate']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/order', [CategoryController::class, 'reorder']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/post-types', [PostTypeController::class, 'store']);
        Route::put('/post-types/order', [PostTypeController::class, 'reorder']);
        Route::put('/post-types/{postType}', [PostTypeController::class, 'update']);
        Route::delete('/post-types/{postType}', [PostTypeController::class, 'destroy']);

        Route::post('/teams', [TeamController::class, 'store']);
        Route::post('/teams/copy-season', [TeamController::class, 'copySeason']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
    });
});
