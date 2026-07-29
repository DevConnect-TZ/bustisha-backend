<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/settings/min-deposit', [SettingController::class, 'minDeposit']);
Route::get('/settings/whatsapp', [SettingController::class, 'whatsapp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/services', [AdminController::class, 'services']);
        Route::post('/services', [AdminController::class, 'storeService']);
        Route::put('/services/{service}', [AdminController::class, 'updateService']);
        Route::delete('/services/{service}', [AdminController::class, 'destroyService']);

        Route::get('/orders', [AdminController::class, 'orders']);
        Route::put('/orders/{order}', [AdminController::class, 'updateOrder']);

        Route::get('/tickets', [AdminController::class, 'tickets']);
        Route::put('/tickets/{ticket}', [AdminController::class, 'updateTicket']);
        Route::post('/tickets/{ticket}/reply', [AdminController::class, 'replyTicket']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);

        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::put('/transactions/{transaction}', [AdminController::class, 'updateTransaction']);

        Route::get('/providers', [ProviderController::class, 'index']);
        Route::post('/providers', [ProviderController::class, 'store']);
        Route::put('/providers/{provider}', [ProviderController::class, 'update']);
        Route::delete('/providers/{provider}', [ProviderController::class, 'destroy']);
        Route::post('/providers/{provider}/balance', [ProviderController::class, 'balance']);
        Route::post('/providers/{provider}/fetch-services', [ProviderController::class, 'fetchServices']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
    });

});
