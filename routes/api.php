<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDiscountController;
use App\Http\Controllers\AdminInviteController;

Route::post('/register', [UserController::class, 'store']);
Route::post('/verify', [UserController::class,'verifyEmail']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/resend-verification', [UserController::class, 'resendVerificationEmail']);

// Public: browse the catalogue
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/categories', [ProductController::class, 'categories']);
    Route::get('/{id}', [ProductController::class, 'show'])->whereNumber('id');
});

// Public: check and accept an admin invite
Route::prefix('admin/invites')->middleware('throttle:10,1')->group(function () {
    Route::post('/accept', [AdminInviteController::class, 'accept']);
    Route::get('/check/{token}', [AdminInviteController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'user.role:vendor,admin'])->group(function () {
    Route::prefix('shops')->group(function () {
        Route::post('/create', [ShopController::class, 'store']);
    });

    Route::get('/vendor/products', [ProductController::class, 'mine']);

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->whereNumber('id');
        Route::put('/{id}/discount', [ProductDiscountController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}/discount', [ProductDiscountController::class, 'destroy'])->whereNumber('id');
    });
});

// Admin only: manage invites
Route::middleware(['auth:sanctum', 'user.role:admin'])->group(function () {
    Route::prefix('admin/invites')->group(function () {
        Route::get('/', [AdminInviteController::class, 'index']);
        Route::post('/', [AdminInviteController::class, 'store']);
        Route::delete('/{id}', [AdminInviteController::class, 'destroy']);
    });
});