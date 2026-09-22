<?php

use App\Http\Controllers\Admin\AdminPayoutController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Organizer\OrganizerPayoutController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event:slug}', [EventController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'role:organizer'])->prefix('/organizer')->group(function () {
    Route::post('/events/{eventId}/payout', [OrganizerPayoutController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:organizer,gatekeeper'])->prefix('/tickets')->group(function () {
    Route::post('/validate', [TicketValidationController::class, 'validate']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('/admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::get('/payouts', [AdminPayoutController::class, 'index']);
    Route::post('/payouts/{payoutId}/approve', [AdminPayoutController::class, 'approve']);
    Route::post('/payouts/{payoutId}/complete', [AdminPayoutController::class, 'completePayout']);
});
