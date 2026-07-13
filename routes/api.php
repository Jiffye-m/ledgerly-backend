<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/business', [BusinessController::class, 'store']);
    Route::get('/business', [BusinessController::class, 'show']);
    Route::put('/business', [BusinessController::class, 'update']);

    // Next: products, categories, customers, sales, expenses, reports
});
