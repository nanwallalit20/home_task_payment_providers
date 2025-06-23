<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Payment;

interface PaymentProviderInterface
{
    /**
     * Process a payment using this provider.
     */
    public function process(Payment $payment): array;

    /**
     * Get the name of this payment provider.
     */
    public function getName(): string;

    /**
     * Check if this provider supports the given payment method.
     */
    public function supports(string $paymentMethod): bool;

    /**
     * Get the supported payment methods for this provider.
     */
    public function getSupportedMethods(): array;
}
