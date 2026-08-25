<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\SavingsProjectionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// These are the app's only public, unauthenticated endpoints, so they are the
// primary credential-stuffing/brute-force surface. Laravel 11's default `api`
// middleware group does NOT include throttling unless `->throttleApi()` is
// called in bootstrap/app.php (which this app doesn't do), so rate limiting
// must be applied explicitly here rather than assumed.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::patch('/accounts/{account}', [AccountController::class, 'update']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::get('/debts', [DebtController::class, 'index']);
    Route::post('/debts', [DebtController::class, 'store']);
    Route::patch('/debts/{debt}', [DebtController::class, 'update']);
    Route::delete('/debts/{debt}', [DebtController::class, 'destroy']);

    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::put('/budgets/{category}', [BudgetController::class, 'update']);

    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/savings-projection', SavingsProjectionController::class);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
});
