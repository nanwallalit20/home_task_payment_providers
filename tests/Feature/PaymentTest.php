<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected string $token;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
        $this->product = Product::factory()->create([
            'user_id' => $this->user->id,
            'quantity' => 10,
        ]);
    }

    public function test_user_can_get_available_payment_methods(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_methods',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $paymentMethods = $response->json('data.payment_methods');
        $this->assertIsArray($paymentMethods);
        $this->assertNotEmpty($paymentMethods);
    }

    public function test_user_can_initiate_payment_for_available_product(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment' => [
                        'id',
                        'product_id',
                        'user_id',
                        'payment_method',
                        'amount',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                    'transaction_id',
                    'provider',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'payment' => [
                        'product_id' => $this->product->id,
                        'user_id' => $this->user->id,
                        'payment_method' => 'credit_card',
                        'status' => PaymentStatus::PAID->value,
                    ],
                ],
            ]);

        // Verify payment was created
        $this->assertDatabaseHas('payments', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'payment_method' => 'credit_card',
            'status' => PaymentStatus::PAID->value,
        ]);

        // Verify product quantity was decremented
        $this->product->refresh();
        $this->assertEquals(9, $this->product->quantity);
    }

    public function test_user_cannot_initiate_payment_for_unavailable_product(): void
    {
        $unavailableProduct = Product::factory()->create([
            'user_id' => $this->user->id,
            'quantity' => 0,
        ]);

        $paymentData = [
            'product_id' => $unavailableProduct->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Product is not available',
            ]);

        // Verify no payment was created
        $this->assertDatabaseMissing('payments', [
            'product_id' => $unavailableProduct->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_initiate_payment_for_another_users_product(): void
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized access to product',
            ]);

        // Verify no payment was created
        $this->assertDatabaseMissing('payments', [
            'product_id' => $otherProduct->id,
            'user_id' => $this->user->id,
        ]);

        // Verify product quantity was not changed
        $otherProduct->refresh();
        $this->assertEquals(5, $otherProduct->quantity);
    }

    public function test_user_cannot_initiate_payment_with_invalid_payment_method(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'invalid_method',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Payment method not supported',
            ]);

        // Verify no payment was created
        $this->assertDatabaseMissing('payments', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
        ]);

        // Verify product quantity was not changed
        $this->product->refresh();
        $this->assertEquals(10, $this->product->quantity);
    }

    public function test_user_cannot_initiate_payment_with_missing_product_id(): void
    {
        $paymentData = [
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_user_cannot_initiate_payment_with_missing_payment_method(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_user_cannot_initiate_payment_with_nonexistent_product(): void
    {
        $paymentData = [
            'product_id' => 99999,
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(404);
    }

    public function test_payment_failure_rolls_back_quantity(): void
    {
        // Mock a payment failure scenario
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('processPayment')
                ->once()
                ->andReturn([
                    'success' => false,
                    'error' => 'Payment failed',
                    'provider' => 'credit_card',
                ]);
        });

        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Payment failed',
            ]);

        // Verify payment was created with failed status
        $this->assertDatabaseHas('payments', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => PaymentStatus::FAILED->value,
        ]);

        // Verify product quantity was restored
        $this->product->refresh();
        $this->assertEquals(10, $this->product->quantity);
    }

    public function test_concurrent_payments_handle_quantity_correctly(): void
    {
        // Create a product with quantity 1
        $limitedProduct = Product::factory()->create([
            'user_id' => $this->user->id,
            'quantity' => 1,
        ]);

        $paymentData = [
            'product_id' => $limitedProduct->id,
            'payment_method' => 'credit_card',
        ];

        // First payment should succeed
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response1->assertStatus(200);

        // Second payment should fail due to insufficient quantity
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response2->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient quantity available',
            ]);

        // Verify only one payment was successful
        $this->assertDatabaseCount('payments', 1);

        // Verify product quantity is 0
        $limitedProduct->refresh();
        $this->assertEquals(0, $limitedProduct->quantity);
    }

    public function test_unauthenticated_user_cannot_access_payment_routes(): void
    {
        // Test payment methods
        $response = $this->getJson('/api/payment-methods');
        $response->assertStatus(401);

        // Test payment initiation
        $response = $this->postJson('/api/payments', []);
        $response->assertStatus(401);
    }

    public function test_payment_amount_is_calculated_correctly(): void
    {
        $paymentData = [
            'product_id' => $this->product->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response->assertStatus(200);

        // Verify payment amount is set correctly (99.99 as per controller logic)
        $this->assertDatabaseHas('payments', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'amount' => 99.99,
        ]);
    }

    public function test_different_payment_methods_are_supported(): void
    {
        $supportedMethods = ['credit_card', 'paypal', 'stripe', 'bank_transfer'];

        foreach ($supportedMethods as $method) {
            $paymentData = [
                'product_id' => $this->product->id,
                'payment_method' => $method,
            ];

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->postJson('/api/payments', $paymentData);

            // Some methods might fail due to mock implementation, but should not be "not supported"
            $this->assertNotEquals(400, $response->status(), "Payment method {$method} should be supported");
        }
    }
}
