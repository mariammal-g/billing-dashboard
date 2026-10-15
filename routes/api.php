<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MerchantDashboardController;
use App\Http\Controllers\UsageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-token', [AuthController::class, 'verifyToken']);
    Route::post('setup-password', [AuthController::class, 'setupPassword']);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('update-email', [AuthController::class, 'updateEmail']);
        Route::put('update-password', [AuthController::class, 'updatePassword']);
        Route::post('update-name', [AuthController::class, 'updateName']);
    });

    // User management routes
    Route::apiResource('users', UserController::class);
    Route::get('roles', [UserController::class, 'roles']);
});

// Swagger documentation route
Route::get('/api/documentation', function () {
    return redirect('/api/documentation/index.html');
});
Route::middleware('throttle:usage')->post('/usage', [UsageController::class, 'store']);
Route::get('/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show']);
Route::get('/dashboard', [MerchantDashboardController::class, 'index']);
