<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $payment = Payment::factory()->create([
            'product_id' => $product->id,
        ]);

        $this->assertInstanceOf(Product::class, $payment->product);
        $this->assertEquals($product->id, $payment->product->id);
    }

    public function test_payment_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $payment->user);
        $this->assertEquals($user->id, $payment->user->id);
    }

    public function test_payment_status_is_casted_to_enum(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::PENDING,
        ]);

        $this->assertInstanceOf(PaymentStatus::class, $payment->status);
        $this->assertEquals(PaymentStatus::PENDING, $payment->status);
    }

    public function test_payment_amount_is_casted_to_decimal(): void
    {
        $payment = Payment::factory()->create([
            'amount' => 99.99,
        ]);

        $this->assertEquals(99.99, $payment->amount);
        $this->assertIsFloat($payment->amount);
    }

    public function test_payment_fillable_attributes_can_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $paymentData = [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'payment_method' => 'credit_card',
            'amount' => 99.99,
            'status' => PaymentStatus::PENDING,
        ];

        $payment = Payment::create($paymentData);

        $this->assertEquals($paymentData['product_id'], $payment->product_id);
        $this->assertEquals($paymentData['user_id'], $payment->user_id);
        $this->assertEquals($paymentData['payment_method'], $payment->payment_method);
        $this->assertEquals($paymentData['amount'], $payment->amount);
        $this->assertEquals($paymentData['status'], $payment->status);
    }

    public function test_payment_has_correct_table_name(): void
    {
        $payment = new Payment();

        $this->assertEquals('payments', $payment->getTable());
    }

    public function test_payment_has_correct_primary_key(): void
    {
        $payment = new Payment();

        $this->assertEquals('id', $payment->getKeyName());
    }

    public function test_payment_can_be_updated(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::PENDING,
            'amount' => 50.00,
        ]);

        $payment->update([
            'status' => PaymentStatus::PAID,
            'amount' => 75.00,
        ]);

        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals(75.00, $payment->amount);
    }

    public function test_payment_can_be_deleted(): void
    {
        $payment = Payment::factory()->create();

        $paymentId = $payment->id;
        $payment->delete();

        $this->assertDatabaseMissing('payments', [
            'id' => $paymentId,
        ]);
    }

    public function test_payment_status_enum_values(): void
    {
        $this->assertEquals('pending', PaymentStatus::PENDING->value);
        $this->assertEquals('paid', PaymentStatus::PAID->value);
        $this->assertEquals('failed', PaymentStatus::FAILED->value);
    }

    public function test_payment_status_enum_cases(): void
    {
        $cases = PaymentStatus::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(PaymentStatus::PENDING, $cases);
        $this->assertContains(PaymentStatus::PAID, $cases);
        $this->assertContains(PaymentStatus::FAILED, $cases);
    }

    public function test_payment_relationships_work_correctly(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Test user relationship
        $this->assertEquals($user->id, $payment->user->id);
        $this->assertEquals($user->name, $payment->user->name);
        $this->assertEquals($user->email, $payment->user->email);

        // Test product relationship
        $this->assertEquals($product->id, $payment->product->id);
        $this->assertEquals($product->name, $payment->product->name);
        $this->assertEquals($product->quantity, $payment->product->quantity);
    }

    public function test_payment_can_have_different_payment_methods(): void
    {
        $paymentMethods = ['credit_card', 'paypal', 'stripe', 'bank_transfer'];

        foreach ($paymentMethods as $method) {
            $payment = Payment::factory()->create([
                'payment_method' => $method,
            ]);

            $this->assertEquals($method, $payment->payment_method);
        }
    }

    public function test_payment_amount_can_be_zero(): void
    {
        $payment = Payment::factory()->create([
            'amount' => 0.00,
        ]);

        $this->assertEquals(0.00, $payment->amount);
    }

    public function test_payment_amount_can_be_very_large(): void
    {
        $payment = Payment::factory()->create([
            'amount' => 999999.99,
        ]);

        $this->assertEquals(999999.99, $payment->amount);
    }

    public function test_payment_status_transitions(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::PENDING,
        ]);

        // Test transition to PAID
        $payment->update(['status' => PaymentStatus::PAID]);
        $this->assertEquals(PaymentStatus::PAID, $payment->status);

        // Test transition to FAILED
        $payment->update(['status' => PaymentStatus::FAILED]);
        $this->assertEquals(PaymentStatus::FAILED, $payment->status);
    }
}
