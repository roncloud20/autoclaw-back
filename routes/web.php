<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return redirect('https://autoclaw-front.vercel.app');
});

Route::get('/home', function () {
    return view('home');
});

Route::post('/register', [UserController::class, 'store']);