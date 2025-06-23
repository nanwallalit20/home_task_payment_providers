<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    /**
     * Test user can create a product.
     */
    public function test_user_can_create_product(): void
    {
        $productData = [
            'name' => $this->faker->word(),
            'quantity' => $this->faker->numberBetween(1, 100),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'product' => ['id', 'name', 'quantity', 'user_id'],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => $productData['name'],
            'quantity' => $productData['quantity'],
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test product creation validation.
     */
    public function test_product_creation_validation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'quantity']);
    }

    /**
     * Test user can list their products.
     */
    public function test_user_can_list_products(): void
    {
        Product::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
                    'data' => [
                        '*' => ['id', 'name', 'quantity', 'user_id'],
                    ],
                ],
            ]);
    }

    /**
     * Test user can view their own product.
     */
    public function test_user_can_view_own_product(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJson([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $product->quantity,
                ],
            ]);
    }

    /**
     * Test user cannot view another user's product.
     */
    public function test_user_cannot_view_other_user_product(): void
    {
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/products/' . $product->id);

        $response->assertStatus(403);
    }

    /**
     * Test user can update their own product.
     */
    public function test_user_can_update_own_product(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => 'Updated Product Name',
            'quantity' => 50,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Product updated successfully',
                'product' => [
                    'id' => $product->id,
                    'name' => $updateData['name'],
                    'quantity' => $updateData['quantity'],
                ],
            ]);
    }

    /**
     * Test user cannot update another user's product.
     */
    public function test_user_cannot_update_other_user_product(): void
    {
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $updateData = [
            'name' => 'Updated Product Name',
            'quantity' => 50,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(403);
    }

    /**
     * Test user can delete their own product.
     */
    public function test_user_can_delete_own_product(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Test user cannot delete another user's product.
     */
    public function test_user_cannot_delete_other_user_product(): void
    {
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(403);
    }
}
