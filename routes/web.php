<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Add a simple login route to prevent RouteNotFoundException
Route::get('/login', function () {
    return response()->json([
        'status' => 401,
        'message' => 'Please use the API login endpoint: POST /api/auth/login',
        'error' => 'Authentication required'
    ], 401);
})->name('login');
