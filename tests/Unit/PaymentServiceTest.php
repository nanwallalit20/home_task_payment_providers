<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;
use App\Services\PaymentService;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    public function test_payment_service_can_register_provider(): void
    {
        $provider = $this->createMockProvider(['credit_card']);

        $this->paymentService->registerProvider($provider);

        $this->assertCount(1, $this->paymentService->getProviders());
        $this->assertSame($provider, $this->paymentService->getProviders()->first());
    }

    public function test_payment_service_can_register_multiple_providers(): void
    {
        $provider1 = $this->createMockProvider(['credit_card']);
        $provider2 = $this->createMockProvider(['paypal']);

        $this->paymentService->registerProvider($provider1);
        $this->paymentService->registerProvider($provider2);

        $this->assertCount(2, $this->paymentService->getProviders());
    }

    public function test_payment_service_returns_provider_for_supported_method(): void
    {
        $provider = $this->createMockProvider(['credit_card']);
        $this->paymentService->registerProvider($provider);

        $result = $this->paymentService->getProviderForMethod('credit_card');

        $this->assertSame($provider, $result);
    }

    public function test_payment_service_returns_null_for_unsupported_method(): void
    {
        $provider = $this->createMockProvider(['credit_card']);
        $this->paymentService->registerProvider($provider);

        $result = $this->paymentService->getProviderForMethod('unsupported_method');

        $this->assertNull($result);
    }

    public function test_payment_service_returns_all_available_payment_methods(): void
    {
        $provider1 = $this->createMockProvider(['credit_card', 'debit_card']);
        $provider2 = $this->createMockProvider(['paypal', 'stripe']);

        $this->paymentService->registerProvider($provider1);
        $this->paymentService->registerProvider($provider2);

        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertCount(4, $methods);
        $this->assertContains('credit_card', $methods);
        $this->assertContains('debit_card', $methods);
        $this->assertContains('paypal', $methods);
        $this->assertContains('stripe', $methods);
    }

    public function test_payment_service_removes_duplicate_payment_methods(): void
    {
        $provider1 = $this->createMockProvider(['credit_card', 'paypal']);
        $provider2 = $this->createMockProvider(['credit_card', 'stripe']);

        $this->paymentService->registerProvider($provider1);
        $this->paymentService->registerProvider($provider2);

        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertCount(3, $methods);
        $this->assertContains('credit_card', $methods);
        $this->assertContains('paypal', $methods);
        $this->assertContains('stripe', $methods);
    }

    public function test_payment_service_processes_payment_successfully(): void
    {
        $payment = Payment::factory()->create(['payment_method' => 'credit_card']);
        $expectedResult = [
            'success' => true,
            'transaction_id' => 'txn_123',
            'provider' => 'credit_card',
        ];

        $provider = $this->createMockProvider(['credit_card'], $expectedResult);
        $this->paymentService->registerProvider($provider);

        $result = $this->paymentService->processPayment($payment);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_payment_service_throws_exception_for_unsupported_method(): void
    {
        $payment = Payment::factory()->create(['payment_method' => 'unsupported_method']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No provider found for payment method: unsupported_method');

        $this->paymentService->processPayment($payment);
    }

    public function test_payment_service_checks_if_method_is_supported(): void
    {
        $provider = $this->createMockProvider(['credit_card']);
        $this->paymentService->registerProvider($provider);

        $this->assertTrue($this->paymentService->isPaymentMethodSupported('credit_card'));
        $this->assertFalse($this->paymentService->isPaymentMethodSupported('unsupported_method'));
    }

    public function test_payment_service_returns_empty_array_when_no_providers(): void
    {
        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertIsArray($methods);
        $this->assertEmpty($methods);
    }

    public function test_payment_service_returns_empty_collection_when_no_providers(): void
    {
        $providers = $this->paymentService->getProviders();

        $this->assertTrue($providers->isEmpty());
    }

    public function test_payment_service_handles_provider_with_no_supported_methods(): void
    {
        $provider = $this->createMockProvider([]);
        $this->paymentService->registerProvider($provider);

        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertIsArray($methods);
        $this->assertEmpty($methods);
    }

    public function test_payment_service_handles_multiple_providers_for_same_method(): void
    {
        $provider1 = $this->createMockProvider(['credit_card']);
        $provider2 = $this->createMockProvider(['credit_card']);

        $this->paymentService->registerProvider($provider1);
        $this->paymentService->registerProvider($provider2);

        // Should return the first registered provider
        $result = $this->paymentService->getProviderForMethod('credit_card');

        $this->assertSame($provider1, $result);
    }

    public function test_payment_service_processes_payment_with_failure_result(): void
    {
        $payment = Payment::factory()->create(['payment_method' => 'credit_card']);
        $expectedResult = [
            'success' => false,
            'error' => 'Payment failed',
            'provider' => 'credit_card',
        ];

        $provider = $this->createMockProvider(['credit_card'], $expectedResult);
        $this->paymentService->registerProvider($provider);

        $result = $this->paymentService->processPayment($payment);

        $this->assertEquals($expectedResult, $result);
    }

    private function createMockProvider(array $supportedMethods, array $processResult = null): PaymentProviderInterface
    {
        $provider = $this->createMock(PaymentProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($method) use ($supportedMethods) {
            return in_array($method, $supportedMethods);
        });
        $provider->method('getSupportedMethods')->willReturn($supportedMethods);

        if ($processResult) {
            $provider->method('process')->willReturn($processResult);
        } else {
            $provider->method('process')->willReturn([
                'success' => true,
                'transaction_id' => 'txn_123',
                'provider' => 'mock',
            ]);
        }

        return $provider;
    }
}
