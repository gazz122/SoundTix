<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AdminEventController;
use App\Http\Controllers\AdminPayoutPageController;
use App\Http\Controllers\AdminRefundPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GatekeeperScannerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Organizer\OrganizerRefundController;
use App\Http\Controllers\OrganizerEventController;
use App\Http\Controllers\OrganizerPayoutPageController;
use App\Http\Controllers\OrganizerRefundPageController;
use App\Http\Controllers\OrganizerReportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RundownController;
use App\Http\Controllers\TicketCategoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [EventController::class, 'index'])->name('home');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::get('/about', fn () => Inertia::render('About'))->name('about');
Route::get('/contact', fn () => Inertia::render('Contact'))->name('contact');
Route::get('/organizer-guide', fn () => Inertia::render('OrganizerGuide'))->name('organizer.guide');
Route::get('/organizer-info', fn () => Inertia::render('OrganizerInfo'))->name('organizer.info');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify', [OtpVerificationController::class, 'show'])->name('verify.show');
    Route::post('/verify', [OtpVerificationController::class, 'confirm'])->name('verify.confirm');
    Route::post('/verify/send', [OtpVerificationController::class, 'send'])->middleware('throttle:auth')->name('verify.send');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::middleware(['role:organizer,gatekeeper', 'redirect.gatekeepers'])->prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/events', [OrganizerEventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [OrganizerEventController::class, 'create'])->name('events.create');
        Route::post('/events', [OrganizerEventController::class, 'store'])->name('events.store');
        Route::post('/events/{event}/submit', [OrganizerEventController::class, 'submit'])->name('events.submit');
    });

    Route::middleware('role:organizer')->prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/events/{event}', [OrganizerEventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [OrganizerEventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [OrganizerEventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/cancel', [OrganizerRefundController::class, 'requestEventCancellation'])->name('events.cancel');

        Route::get('/refunds', [OrganizerRefundPageController::class, 'index'])->name('refunds.index');

        Route::get('/reports', [OrganizerReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/csv', [OrganizerReportController::class, 'csv'])->name('reports.csv');
        Route::get('/reports/print', [OrganizerReportController::class, 'print'])->name('reports.print');

        Route::post('/events/{event}/rundowns', [RundownController::class, 'store'])->name('events.rundowns.store');
        Route::put('/events/{event}/rundowns/{rundown}', [RundownController::class, 'update'])->name('events.rundowns.update');
        Route::delete('/events/{event}/rundowns/{rundown}', [RundownController::class, 'destroy'])->name('events.rundowns.destroy');

        Route::get('/payouts', [OrganizerPayoutPageController::class, 'index'])->name('payouts.index');
        Route::post('/payouts/{event}/withdraw', [OrganizerPayoutPageController::class, 'withdraw'])->name('payouts.withdraw');
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

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/payouts', [AdminPayoutPageController::class, 'index'])->name('payouts.index');
        Route::post('/payouts/{payout}/approve', [AdminPayoutPageController::class, 'approve'])->name('payouts.approve');
        Route::post('/payouts/{payout}/complete', [AdminPayoutPageController::class, 'complete'])->name('payouts.complete');

        Route::get('/refunds', [AdminRefundPageController::class, 'index'])->name('refunds.index');
        Route::post('/refunds/{refund}/approve', [AdminRefundPageController::class, 'approve'])->name('refunds.approve');
        Route::post('/refunds/events/{event}/approve-cancellation', [AdminRefundPageController::class, 'approveCancellation'])->name('refunds.events.approve-cancellation');
        Route::post('/refunds/events/{event}/reject-cancellation', [AdminRefundPageController::class, 'rejectCancellation'])->name('refunds.events.reject-cancellation');
    });

    Route::middleware('role:organizer,gatekeeper')->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/scan', [GatekeeperScannerController::class, 'index'])->name('scan');
        Route::post('/scan/validate', [GatekeeperScannerController::class, 'validate'])->name('scan.validate');
    });

    Route::middleware('role:customer')->name('orders.')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('index');
        Route::get('/orders/{order:order_code}', [OrderController::class, 'show'])->name('show');
        Route::post('/orders/{order:order_code}/cancel', [OrderController::class, 'cancel'])->name('cancel');
        Route::post('/orders/{order:order_code}/refund', [OrderController::class, 'refund'])->name('refund');
        Route::post('/events/{slug}/order', [OrderController::class, 'store'])->name('store');
    });

    Route::middleware('role:customer')->name('payments.')->group(function () {
        Route::post('/orders/{order:order_code}/pay', [PaymentController::class, 'pay'])->name('pay');
        Route::post('/payments/{payment}/settle', [PaymentController::class, 'settle'])->name('settle');
        Route::post('/payments/{payment}/expire', [PaymentController::class, 'expire'])->name('expire');
        Route::post('/payments/{payment}/check', [PaymentController::class, 'checkStatus'])->name('check');
    });
});
