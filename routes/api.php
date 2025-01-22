<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Welcome to API'];
});

Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');

        Route::middleware(['auth:api'])->group(function () {
            Route::post('verify-email', 'verifyEmail');
            Route::post('resend-otp', 'resendOtp');
            Route::post('logout', 'logout');
        });
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// Route::post('register', [AuthController::class, 'register']);
// Route::post('resend-otp', [AuthController::class, 'resendOtp']);
// Route::post('verify-otp', [AuthController::class, 'verifyEmail']);
