<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PaymentProviders\CreditCardProvider;
use App\Services\PaymentProviders\PayPalProvider;
use App\Services\PaymentProviders\BankTransferProvider;
use App\Services\PaymentProviders\StripeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PaymentService $paymentService;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = new PaymentService();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'user_id' => $this->user->id,
            'quantity' => 10,
        ]);
    }

    /**
     * Test that payment providers can be registered and retrieved.
     */
    public function test_payment_providers_can_be_registered(): void
    {
        $creditCardProvider = new CreditCardProvider();
        $paypalProvider = new PayPalProvider();

        $this->paymentService->registerProvider($creditCardProvider);
        $this->paymentService->registerProvider($paypalProvider);

        $this->assertCount(2, $this->paymentService->getProviders());
        $this->assertTrue($this->paymentService->isPaymentMethodSupported('credit_card'));
        $this->assertTrue($this->paymentService->isPaymentMethodSupported('paypal'));
        $this->assertFalse($this->paymentService->isPaymentMethodSupported('invalid_method'));
    }

    /**
     * Test that available payment methods are returned correctly.
     */
    public function test_available_payment_methods_are_returned(): void
    {
        $this->paymentService->registerProvider(new CreditCardProvider());
        $this->paymentService->registerProvider(new PayPalProvider());
        $this->paymentService->registerProvider(new BankTransferProvider());

        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertContains('credit_card', $methods);
        $this->assertContains('paypal', $methods);
        $this->assertContains('bank_transfer', $methods);
        $this->assertCount(3, $methods);
    }

    /**
     * Test that the correct provider is returned for a payment method.
     */
    public function test_correct_provider_is_returned_for_payment_method(): void
    {
        $creditCardProvider = new CreditCardProvider();
        $paypalProvider = new PayPalProvider();

        $this->paymentService->registerProvider($creditCardProvider);
        $this->paymentService->registerProvider($paypalProvider);

        $provider = $this->paymentService->getProviderForMethod('credit_card');
        $this->assertInstanceOf(CreditCardProvider::class, $provider);

        $provider = $this->paymentService->getProviderForMethod('paypal');
        $this->assertInstanceOf(PayPalProvider::class, $provider);

        $provider = $this->paymentService->getProviderForMethod('invalid_method');
        $this->assertNull($provider);
    }

    /**
     * Test that payment processing works with different providers.
     */
    public function test_payment_processing_with_different_providers(): void
    {
        $this->paymentService->registerProvider(new CreditCardProvider());
        $this->paymentService->registerProvider(new PayPalProvider());
        $this->paymentService->registerProvider(new BankTransferProvider());

        $payment = Payment::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'payment_method' => 'credit_card',
            'amount' => 99.99,
        ]);

        $result = $this->paymentService->processPayment($payment);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('Credit Card Provider', $result['provider']);

        if ($result['success']) {
            $this->assertArrayHasKey('transaction_id', $result);
            $this->assertStringStartsWith('CC_TXN_', $result['transaction_id']);
        } else {
            $this->assertArrayHasKey('error', $result);
        }
    }

    /**
     * Test that adding a new payment provider works correctly.
     */
    public function test_adding_new_payment_provider(): void
    {
        $stripeProvider = new StripeProvider();
        $this->paymentService->registerProvider($stripeProvider);

        $this->assertTrue($this->paymentService->isPaymentMethodSupported('stripe'));
        $this->assertTrue($this->paymentService->isPaymentMethodSupported('stripe_card'));

        $methods = $this->paymentService->getAvailablePaymentMethods();
        $this->assertContains('stripe', $methods);
        $this->assertContains('stripe_card', $methods);

        $payment = Payment::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'payment_method' => 'stripe',
            'amount' => 99.99,
        ]);

        $result = $this->paymentService->processPayment($payment);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('Stripe Provider', $result['provider']);

        if ($result['success']) {
            $this->assertStringStartsWith('STRIPE_TXN_', $result['transaction_id']);
        }
    }

    /**
     * Test that payment method validation works with dynamic methods.
     */
    public function test_payment_method_validation_with_dynamic_methods(): void
    {
        $this->paymentService->registerProvider(new CreditCardProvider());
        $this->paymentService->registerProvider(new PayPalProvider());

        $token = auth('api')->login($this->user);

        // Test with valid payment method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/payments', [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(200);

        // Test with invalid payment method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/payments', [
            'product_id' => $this->product->id,
            'payment_method' => 'invalid_method',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test that payment methods endpoint returns available methods.
     */
    public function test_payment_methods_endpoint(): void
    {
        $token = auth('api')->login($this->user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure(['payment_methods']);

        $methods = $response->json('payment_methods');
        $this->assertContains('credit_card', $methods);
        $this->assertContains('paypal', $methods);
        $this->assertContains('bank_transfer', $methods);
    }
}
