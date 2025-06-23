<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Models\Payment;

class StripeProvider extends AbstractPaymentProvider
{
    /**
     * Process a Stripe payment.
     */
    public function process(Payment $payment): array
    {
        $success = $this->simulatePaymentProcessing(0.97);

        if ($success) {
            return $this->createSuccessResponse(
                $this->generateTransactionId('STRIPE'),
                1.5
            );
        }

        return $this->createFailureResponse('Stripe payment processing failed');
    }

    /**
     * Get the name of this payment provider.
     */
    public function getName(): string
    {
        return 'Stripe Provider';
    }

    /**
     * Get the supported payment methods for this provider.
     */
    public function getSupportedMethods(): array
    {
        return ['stripe', 'stripe_card'];
    }
}
