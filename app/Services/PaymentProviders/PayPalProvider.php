<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Models\Payment;

class PayPalProvider extends AbstractPaymentProvider
{
    /**
     * Process a PayPal payment.
     */
    public function process(Payment $payment): array
    {
        $success = $this->simulatePaymentProcessing(0.98);

        if ($success) {
            return $this->createSuccessResponse(
                $this->generateTransactionId('PP'),
                1
            );
        }

        return $this->createFailureResponse('PayPal payment processing failed');
    }

    /**
     * Get the name of this payment provider.
     */
    public function getName(): string
    {
        return 'PayPal Provider';
    }

    /**
     * Get the supported payment methods for this provider.
     */
    public function getSupportedMethods(): array
    {
        return ['paypal'];
    }
}
