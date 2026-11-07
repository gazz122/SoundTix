<?php

namespace App\Providers;

use App\Services\PaymentGateway\MidtransPaymentDriver;
use App\Services\PaymentGateway\MockPaymentDriver;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            if (! $app->environment('testing') && filled(config('services.midtrans.server_key'))) {
                return $app->make(MidtransPaymentDriver::class);
            }

            return $app->make(MockPaymentDriver::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole() && $this->app->environment('local')) {
            // Bind the dev server to all interfaces so a phone on the same Wi-Fi
            // can open QR links generated from a localhost browser session.
            DevCommands::artisan('serve --host=0.0.0.0', 'server');
        }

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if (in_array($response->statusCode(), [403, 404, 500, 503], true)) {
                return $response->render('ErrorPage', [
                    'status' => $response->statusCode(),
                ])->withSharedData();
            }
        });
    }
}
