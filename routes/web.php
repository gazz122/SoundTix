<?php

use App\Http\Controllers\AdminEventController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GatekeeperScannerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrganizerEventController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TicketCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('home');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:organizer')->prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/events', [OrganizerEventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [OrganizerEventController::class, 'create'])->name('events.create');
        Route::post('/events', [OrganizerEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [OrganizerEventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [OrganizerEventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/submit', [OrganizerEventController::class, 'submit'])->name('events.submit');
    });

    Route::middleware('role:organizer,admin')->prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/events/{event}/categories', [TicketCategoryController::class, 'index'])->name('events.categories');
        Route::post('/events/{event}/categories', [TicketCategoryController::class, 'store'])->name('events.categories.store');
        Route::put('/events/{event}/categories/{category}', [TicketCategoryController::class, 'update'])->name('events.categories.update');
        Route::delete('/events/{event}/categories/{category}', [TicketCategoryController::class, 'destroy'])->name('events.categories.destroy');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::post('/events/{event}/approve', [AdminEventController::class, 'approve'])->name('events.approve');
        Route::post('/events/{event}/reject', [AdminEventController::class, 'reject'])->name('events.reject');
    });

    Route::middleware('role:gatekeeper,admin')->prefix('gatekeeper')->name('gatekeeper.')->group(function () {
        Route::get('/scanner', [GatekeeperScannerController::class, 'index'])->name('scanner');
        Route::post('/validate', [GatekeeperScannerController::class, 'validate'])->name('validate');
    });

    Route::middleware('role:customer')->name('orders.')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('index');
        Route::get('/orders/{order:order_code}', [OrderController::class, 'show'])->name('show');
        Route::post('/orders/{order:order_code}/cancel', [OrderController::class, 'cancel'])->name('cancel');
        Route::post('/events/{slug}/order', [OrderController::class, 'store'])->name('store');
    });

    Route::middleware('role:customer')->name('payments.')->group(function () {
        Route::post('/orders/{order:order_code}/pay', [PaymentController::class, 'pay'])->name('pay');
        Route::post('/payments/{payment}/settle', [PaymentController::class, 'settle'])->name('settle');
        Route::post('/payments/{payment}/expire', [PaymentController::class, 'expire'])->name('expire');
    });
});
