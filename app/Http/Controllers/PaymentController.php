<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\Product;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\PaymentStatus;
use InvalidArgumentException;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Initiate a payment for a product.
     */
    public function initiate(PaymentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $product = Product::lockForUpdate()->findOrFail($request->validated('product_id'));

            // Check if product is available
            if (!$product->isAvailable()) {
                return $this->errorResponse('Product is not available', [], 400);
            }

            // Check if user owns the product (optional business rule)
            if ($product->user_id !== Auth::id()) {
                return $this->forbiddenResponse('Unauthorized access to product');
            }

            // Decrement quantity atomically
            if (!$product->decrementQuantity()) {
                return $this->errorResponse('Insufficient quantity available', [], 400);
            }

            // Create payment record
            $payment = Payment::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'payment_method' => $request->validated('payment_method'),
                'amount' => $this->calculateAmount($product),
                'status' => PaymentStatus::PENDING,
            ]);

            try {
                // Process payment using the extensible payment service
                $paymentResult = $this->paymentService->processPayment($payment);

                if ($paymentResult['success']) {
                    $payment->update(['status' => PaymentStatus::PAID]);

                    Log::info('Payment completed successfully', [
                        'payment_id' => $payment->id,
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'provider' => $paymentResult['provider'],
                        'transaction_id' => $paymentResult['transaction_id'],
                    ]);

                    return $this->successResponse([
                        'payment' => $payment->fresh(),
                        'transaction_id' => $paymentResult['transaction_id'],
                        'provider' => $paymentResult['provider'],
                    ], 'Payment completed successfully');
                } else {
                    // Rollback quantity if payment fails
                    $product->increment('quantity');
                    $payment->update(['status' => PaymentStatus::FAILED]);

                    Log::error('Payment failed', [
                        'payment_id' => $payment->id,
                        'error' => $paymentResult['error'],
                        'provider' => $paymentResult['provider'],
                    ]);

                    return $this->errorResponse('Payment failed', ['error' => $paymentResult['error']], 400);
                }
            } catch (InvalidArgumentException $e) {
                // Rollback quantity if payment method is not supported
                $product->increment('quantity');
                $payment->update(['status' => PaymentStatus::FAILED]);

                Log::error('Payment method not supported', [
                    'payment_id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse('Payment method not supported', ['error' => $e->getMessage()], 400);
            }
        });
    }

    /**
     * Get available payment methods.
     */
    public function getPaymentMethods(): JsonResponse
    {
        return $this->successResponse([
            'payment_methods' => $this->paymentService->getAvailablePaymentMethods(),
        ]);
    }

    /**
     * Calculate payment amount for the product.
     */
    private function calculateAmount(Product $product): float
    {
        // Mock pricing logic - in real implementation, this would come from product pricing
        return 99.99;
    }
}
