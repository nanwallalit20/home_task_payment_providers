<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Models\Payment;

class CreditCardProvider extends AbstractPaymentProvider
{
    /**
     * Process a credit card payment.
     */
    public function process(Payment $payment): array
    {
        $success = $this->simulatePaymentProcessing(0.95);

        if ($success) {
            return $this->createSuccessResponse(
                $this->generateTransactionId('CC'),
                2
            );
        }

        return $this->createFailureResponse('Credit card payment processing failed');
    }

    /**
     * Get the name of this payment provider.
     */
    public function getName(): string
    {
        return 'Credit Card Provider';
    }

    /**
     * Get the supported payment methods for this provider.
     */
    public function getSupportedMethods(): array
    {
        return ['credit_card'];
    }
}
