<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public — protected by the signature itself, not auth. Used for
// WhatsApp/email receipt links a customer can open without logging in.
Route::get('/receipts/{sale}/view', [ReceiptController::class, 'publicView'])
    ->name('receipts.public')
    ->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::post('/business', [BusinessController::class, 'store']);
    Route::get('/business', [BusinessController::class, 'show']);
    Route::put('/business', [BusinessController::class, 'update']);

    // Everything below requires a business to already exist
    Route::middleware('has.business')->group(function () {

        // Owner-only: business settings, adding/editing team members
        Route::middleware('is.owner')->group(function () {
            Route::put('/business/settings', [BusinessController::class, 'updateSettings']);
            Route::post('/team', [TeamController::class, 'store']);
            Route::put('/team/{member}', [TeamController::class, 'update']);
            Route::delete('/team/{member}', [TeamController::class, 'destroy']);
        });

        // Any active team member can view the roster
        Route::get('/team', [TeamController::class, 'index']);

        Route::apiResource('categories', CategoryController::class)->except(['destroy']);
        Route::apiResource('products', ProductController::class)->except(['destroy']);
        Route::apiResource('customers', CustomerController::class)->except(['destroy']);
        Route::apiResource('expenses', ExpenseController::class)->except(['destroy']);
        Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);

        // Staff can create/edit freely, but destructive/reversing actions
        // need an admin or the owner
        Route::middleware('not.staff')->group(function () {
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
        });

        Route::get('/sales/{sale}/receipt/pdf', [ReceiptController::class, 'download']);
        Route::post('/sales/{sale}/receipt/email', [ReceiptController::class, 'email']);
        Route::post('/sales/{sale}/receipt/whatsapp', [ReceiptController::class, 'whatsapp']);

        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/daily', [ReportController::class, 'daily']);
        Route::get('/reports/monthly', [ReportController::class, 'monthly']);
        Route::get('/reports/profit', [ReportController::class, 'profit']);
    });
});
