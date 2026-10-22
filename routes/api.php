<?php

use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/posts/{post}', [PostController::class, 'show']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/posts/{post}', [PostController::class, 'update']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/posts/{post}', [PostController::class, 'delete']);
});