<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Services\PaymentService;
use App\Services\PaymentProviders\BankTransferProvider;
use App\Services\PaymentProviders\CreditCardProvider;
use App\Services\PaymentProviders\PayPalProvider;
use App\Services\PaymentProviders\StripeProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentService::class, function ($app) {
            $paymentService = new PaymentService();

            // Register default providers
            $paymentService->registerProvider(new CreditCardProvider());
            $paymentService->registerProvider(new PayPalProvider());
            $paymentService->registerProvider(new BankTransferProvider());

            // Example: Register additional providers (uncomment to enable)
            // $paymentService->registerProvider(new StripeProvider());

            return $paymentService;
        });

        // Bind the interface to the service
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            return $app->make(PaymentService::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
