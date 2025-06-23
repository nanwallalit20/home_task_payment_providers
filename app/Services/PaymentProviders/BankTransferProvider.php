<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Models\Payment;

class BankTransferProvider extends AbstractPaymentProvider
{
    /**
     * Process a bank transfer payment.
     */
    public function process(Payment $payment): array
    {
        $success = $this->simulatePaymentProcessing(0.99);

        if ($success) {
            return $this->createSuccessResponse(
                $this->generateTransactionId('BT'),
                3
            );
        }

        return $this->createFailureResponse('Bank transfer processing failed');
    }

    /**
     * Get the name of this payment provider.
     */
    public function getName(): string
    {
        return 'Bank Transfer Provider';
    }

    /**
     * Get the supported payment methods for this provider.
     */
    public function getSupportedMethods(): array
    {
        return ['bank_transfer'];
    }
}
