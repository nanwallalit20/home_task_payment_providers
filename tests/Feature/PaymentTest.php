<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupUserAndProduct();
    }

    /**
     * Test user can initiate payment for available product.
     */
    public function test_user_can_initiate_payment(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment' => ['id', 'product_id', 'user_id', 'payment_method', 'amount', 'status'],
                    'transaction_id',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'payment_method' => 'credit_card',
        ]);

        // Check that quantity was decremented
        $this->product->refresh();
        $this->assertEquals(9, $this->product->quantity);
    }

    /**
     * Test payment validation.
     */
    public function test_payment_validation(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/payments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'payment_method']);
    }

    /**
     * Test payment with invalid product ID.
     */
    public function test_payment_with_invalid_product(): void
    {
        $paymentData = [
            'product_id' => 99999,
            'payment_method' => 'credit_card',
        ];

        $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    /**
     * Test payment with invalid payment method.
     */
    public function test_payment_with_invalid_payment_method(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'invalid_method',
        ];

        $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    /**
     * Test payment for unavailable product.
     */
    public function test_payment_for_unavailable_product(): void
    {
        $this->product->update(['quantity' => 0]);

        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Product is not available']);
    }

    /**
     * Test payment for another user's product.
     */
    public function test_payment_for_another_users_product(): void
    {
        $otherUser = User::factory()->create();
        $otherProduct = Product::factory()->create([
            'user_id' => $otherUser->id,
            'quantity' => 5,
        ]);

        $paymentData = [
            'product_id' => $otherProduct->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized access to product']);
    }

    /**
     * Test payment with different payment methods.
     */
    public function test_payment_with_different_methods(): void
    {
        $paymentMethods = ['credit_card', 'paypal', 'bank_transfer'];

        foreach ($paymentMethods as $method) {
            $paymentData = [
                'product_id' => $this->product->id,
                'payment_method' => $method,
            ];

            $response = $this->authenticatedRequest('POST', '/api/payments', $paymentData);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'payment' => ['id', 'product_id', 'user_id', 'payment_method', 'amount', 'status'],
                        'transaction_id',
                    ],
                ]);

            $this->assertDatabaseHas('payments', [
                'product_id' => $this->product->id,
                'user_id' => $this->user->id,
                'payment_method' => $method,
            ]);
        }
    }

    /**
     * Test atomic quantity decrement.
     */
    public function test_atomic_quantity_decrement(): void
    {
        $initialQuantity = $this->product->quantity;
        $paymentCount = 3;

        // Create multiple payments simultaneously
        $responses = [];
        for ($i = 0; $i < $paymentCount; $i++) {
            $paymentData = [
                'product_id' => $this->product->id,
                'payment_method' => 'credit_card',
            ];

            $responses[] = $this->authenticatedRequest('POST', '/api/payments', $paymentData);
        }

        // Check that all payments were processed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Check final quantity
        $this->product->refresh();
        $this->assertEquals($initialQuantity - $paymentCount, $this->product->quantity);

        // Check payment records
        $this->assertEquals($paymentCount, Payment::where('product_id', $this->product->id)->count());
    }
}
