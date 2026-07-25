<?php

use App\Http\Controllers\Api\Admin\AdminBusinessController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminPlanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DraftSaleController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryLogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
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

    Route::post('/verify-email', [EmailVerificationController::class, 'verify']);
    Route::post('/resend-otp', [EmailVerificationController::class, 'resend']);

    Route::post('/business', [BusinessController::class, 'store'])->middleware('email.verified');
    Route::get('/business', [BusinessController::class, 'show']);
    Route::put('/business', [BusinessController::class, 'update']);

    // ── Platform admin (you, running Ledgerly as a SaaS) ──────────────
    // Entirely separate from any single business — a super admin may not
    // belong to a business at all. Deliberately sits outside has.business
    // and subscription.active, since neither concept applies here.
    Route::middleware('is.super_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'stats']);

        Route::get('/businesses', [AdminBusinessController::class, 'index']);
        Route::get('/businesses/{business}', [AdminBusinessController::class, 'show']);
        Route::post('/businesses/{business}/activate', [AdminBusinessController::class, 'activate']);
        Route::post('/businesses/{business}/extend-trial', [AdminBusinessController::class, 'extendTrial']);
        Route::post('/businesses/{business}/suspend', [AdminBusinessController::class, 'suspend']);
        Route::post('/businesses/{business}/reactivate', [AdminBusinessController::class, 'reactivate']);
        Route::post('/businesses/{business}/payments', [AdminPaymentController::class, 'store']);

        Route::get('/payments', [AdminPaymentController::class, 'index']);

        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::put('/plans/{plan}', [AdminPlanController::class, 'update']);
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);
    });

    // ── Tenant side: requires a business AND an active/trialing
    // subscription. This is the actual product every business uses. ────
    Route::middleware(['has.business', 'subscription.active'])->group(function () {

        // Owner-only: business settings, adding/editing team members
        Route::middleware('is.owner')->group(function () {
            Route::put('/business/settings', [BusinessController::class, 'updateSettings']);
            Route::post('/team', [TeamController::class, 'store']);
            Route::put('/team/{member}', [TeamController::class, 'update']);
            Route::delete('/team/{member}', [TeamController::class, 'destroy']);
        });

        // Any active team member can view the roster
        Route::get('/team', [TeamController::class, 'index']);

        // Barcode lookup — registered before apiResource so it takes
        // priority over the /products/{product} pattern
        Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);

        Route::apiResource('categories', CategoryController::class)->except(['destroy']);
        Route::apiResource('products', ProductController::class)->except(['destroy']);
        Route::apiResource('customers', CustomerController::class)->except(['destroy']);
        Route::apiResource('expenses', ExpenseController::class)->except(['destroy']);
        Route::apiResource('suppliers', SupplierController::class)->except(['destroy']);
        Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);

        // Draft sales — a paused cart, no stock effect, any team member
        // can save/resume/edit/discard one
        Route::apiResource('draft-sales', DraftSaleController::class);

        // Inventory log — anyone can view the stock history; only
        // admin/owner can log manual purchases/returns/adjustments (same
        // "corrective action" line as delete/void elsewhere in this file)
        Route::get('/inventory-logs', [InventoryLogController::class, 'index']);

        Route::get('/returns', [ReturnController::class, 'index']);
        Route::get('/returns/{return}', [ReturnController::class, 'show']);

        // Staff can create/edit freely, but destructive/reversing actions
        // — including processing a return, which refunds money and
        // restocks items — need an admin or the owner
        Route::middleware('not.staff')->group(function () {
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
            Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
            Route::post('/inventory-logs', [InventoryLogController::class, 'store']);
            Route::post('/returns', [ReturnController::class, 'store']);
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
