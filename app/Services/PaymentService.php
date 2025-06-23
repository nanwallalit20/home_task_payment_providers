<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentService
{
    private Collection $providers;

    public function __construct()
    {
        $this->providers = collect();
    }

    /**
     * Process a payment using the appropriate provider.
     */
    public function processPayment(Payment $payment): array
    {
        $provider = $this->getProviderForMethod($payment->payment_method);

        if (!$provider) {
            throw new InvalidArgumentException("No provider found for payment method: {$payment->payment_method}");
        }

        return $provider->process($payment);
    }

    /**
     * Get the provider for a specific payment method.
     */
    public function getProviderForMethod(string $paymentMethod): ?PaymentProviderInterface
    {
        return $this->providers->first(function (PaymentProviderInterface $provider) use ($paymentMethod) {
            return $provider->supports($paymentMethod);
        });
    }

    /**
     * Get all available payment methods.
     */
    public function getAvailablePaymentMethods(): array
    {
        return $this->providers->flatMap(function (PaymentProviderInterface $provider) {
            return $provider->getSupportedMethods();
        })->unique()->values()->toArray();
    }

    /**
     * Get all registered providers.
     */
    public function getProviders(): Collection
    {
        return $this->providers;
    }

    /**
     * Register a new payment provider.
     */
    public function registerProvider(PaymentProviderInterface $provider): void
    {
        $this->providers->push($provider);
    }

    /**
     * Check if a payment method is supported.
     */
    public function isPaymentMethodSupported(string $paymentMethod): bool
    {
        return $this->getProviderForMethod($paymentMethod) !== null;
    }
}
