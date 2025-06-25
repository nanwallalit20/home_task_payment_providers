<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class ProductTest extends TestCase
{
    public function test_product_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $product->user);
        $this->assertEquals($user->id, $product->user->id);
    }

    public function test_product_is_available_when_quantity_greater_than_zero(): void
    {
        $product = Product::factory()->create([
            'quantity' => 5,
        ]);

        $this->assertTrue($product->isAvailable());
    }

    public function test_product_is_not_available_when_quantity_is_zero(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
        ]);

        $this->assertFalse($product->isAvailable());
    }

    public function test_product_is_not_available_when_quantity_is_negative(): void
    {
        $product = Product::factory()->create([
            'quantity' => -1,
        ]);

        $this->assertFalse($product->isAvailable());
    }

    public function test_decrement_quantity_succeeds_when_sufficient_quantity(): void
    {
        $product = Product::factory()->create([
            'quantity' => 10,
        ]);

        $result = $product->decrementQuantity(3);

        $this->assertTrue($result);
        $this->assertEquals(7, $product->quantity);
    }

    public function test_decrement_quantity_fails_when_insufficient_quantity(): void
    {
        $product = Product::factory()->create([
            'quantity' => 5,
        ]);

        $result = $product->decrementQuantity(10);

        $this->assertFalse($result);
        $this->assertEquals(5, $product->quantity);
    }

    public function test_decrement_quantity_defaults_to_one(): void
    {
        $product = Product::factory()->create([
            'quantity' => 5,
        ]);

        $result = $product->decrementQuantity();

        $this->assertTrue($result);
        $this->assertEquals(4, $product->quantity);
    }

    public function test_decrement_quantity_fails_when_quantity_is_zero(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
        ]);

        $result = $product->decrementQuantity(1);

        $this->assertFalse($result);
        $this->assertEquals(0, $product->quantity);
    }

    public function test_product_fillable_attributes_can_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $productData = [
            'name' => 'Test Product',
            'quantity' => 100,
            'user_id' => $user->id,
        ];

        $product = Product::create($productData);

        $this->assertEquals($productData['name'], $product->name);
        $this->assertEquals($productData['quantity'], $product->quantity);
        $this->assertEquals($productData['user_id'], $product->user_id);
    }

    public function test_product_has_correct_table_name(): void
    {
        $product = new Product();

        $this->assertEquals('products', $product->getTable());
    }

    public function test_product_has_correct_primary_key(): void
    {
        $product = new Product();

        $this->assertEquals('id', $product->getKeyName());
    }

    public function test_product_can_be_updated(): void
    {
        $product = Product::factory()->create([
            'name' => 'Original Name',
            'quantity' => 10,
        ]);

        $product->update([
            'name' => 'Updated Name',
            'quantity' => 20,
        ]);

        $this->assertEquals('Updated Name', $product->name);
        $this->assertEquals(20, $product->quantity);
    }

    public function test_product_can_be_deleted(): void
    {
        $product = Product::factory()->create();

        $productId = $product->id;
        $product->delete();

        $this->assertDatabaseMissing('products', [
            'id' => $productId,
        ]);
    }

    public function test_product_relationship_with_payments(): void
    {
        $product = Product::factory()->create();

        // This test verifies that the relationship method exists
        // In a real scenario, you would create payments and test the relationship
        $this->assertTrue(method_exists($product, 'payments'));
    }

    public function test_product_quantity_can_be_incremented(): void
    {
        $product = Product::factory()->create([
            'quantity' => 5,
        ]);

        $product->increment('quantity', 3);

        $this->assertEquals(8, $product->quantity);
    }

    public function test_product_quantity_can_be_decremented_directly(): void
    {
        $product = Product::factory()->create([
            'quantity' => 10,
        ]);

        $product->decrement('quantity', 4);

        $this->assertEquals(6, $product->quantity);
    }
}
