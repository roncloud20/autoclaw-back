<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShopController;

Route::post('/register', [UserController::class, 'store']);
Route::post('/verify', [UserController::class,'verifyEmail']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/resend-verification', [UserController::class, 'resendVerificationEmail']);

Route::middleware(['auth:sanctum', 'user.role:vendor,admin'])->group(function () {
    Route::prefix('shops')->group(function () {
        Route::post('/create', [ShopController::class, 'store']);
    });
});
