<?php

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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
