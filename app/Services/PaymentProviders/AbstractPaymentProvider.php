<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;

abstract class AbstractPaymentProvider implements PaymentProviderInterface
{
    /**
     * Check if this provider supports the given payment method.
     */
    public function supports(string $paymentMethod): bool
    {
        return in_array($paymentMethod, $this->getSupportedMethods());
    }

    /**
     * Create a successful payment response.
     */
    protected function createSuccessResponse(string $transactionId, int $processingTime = 1): array
    {
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'processing_time' => $processingTime,
            'provider' => $this->getName(),
        ];
    }

    /**
     * Create a failed payment response.
     */
    protected function createFailureResponse(string $errorMessage): array
    {
        return [
            'success' => false,
            'error' => $errorMessage,
            'provider' => $this->getName(),
        ];
    }

    /**
     * Generate a transaction ID with provider prefix.
     */
    protected function generateTransactionId(string $prefix): string
    {
        return $prefix . '_TXN_' . uniqid();
    }

    /**
     * Simulate payment processing with given success rate.
     */
    protected function simulatePaymentProcessing(float $successRate): bool
    {
        return (rand(1, 100) / 100) <= $successRate;
    }
}