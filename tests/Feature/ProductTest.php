<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use WithFaker;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_list_their_products(): void
    {
        $userProducts = Product::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create products for another user
        Product::factory()->count(2)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products' => [
                        '*' => [
                            'id',
                            'name',
                            'quantity',
                            'user_id',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $responseProducts = $response->json('data.products');
        $this->assertCount(3, $responseProducts);

        // Verify only user's products are returned
        foreach ($responseProducts as $product) {
            $this->assertEquals($this->user->id, $product['user_id']);
        }
    }

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
                'success',
                'message',
                'data' => [
                    'product' => [
                        'id',
                        'name',
                        'quantity',
                        'user_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => [
                    'product' => [
                        'name' => $productData['name'],
                        'quantity' => $productData['quantity'],
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => $productData['name'],
            'quantity' => $productData['quantity'],
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_create_product_with_invalid_data(): void
    {
        $productData = [
            'name' => '', // Empty name
            'quantity' => -5, // Negative quantity
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'quantity']);
    }

    public function test_user_can_view_their_product(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'product' => [
                        'id',
                        'name',
                        'quantity',
                        'user_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $product->quantity,
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);
    }

    public function test_user_cannot_view_another_users_product(): void
    {
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/products/{$product->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized access to product',
            ]);
    }

    public function test_user_can_update_their_product(): void
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
        ])->putJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'product' => [
                        'id',
                        'name',
                        'quantity',
                        'user_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $updateData['name'],
                        'quantity' => $updateData['quantity'],
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $updateData['name'],
            'quantity' => $updateData['quantity'],
        ]);
    }

    public function test_user_cannot_update_another_users_product(): void
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
        ])->putJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized access to product',
            ]);

        // Verify product was not updated
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $product->name,
            'quantity' => $product->quantity,
        ]);
    }

    public function test_user_can_delete_their_product(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_product(): void
    {
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized access to product',
            ]);

        // Verify product was not deleted
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_product_routes(): void
    {
        $product = Product::factory()->create();

        // Test index
        $response = $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/products');
        $response->assertStatus(401);

        // Test store
        $response = $this->withHeaders(['Accept' => 'application/json'])->postJson('/api/products', []);
        $response->assertStatus(401);

        // Test show
        $response = $this->withHeaders(['Accept' => 'application/json'])->getJson("/api/products/{$product->id}");
        $response->assertStatus(401);

        // Test update
        $response = $this->withHeaders(['Accept' => 'application/json'])->putJson("/api/products/{$product->id}", []);
        $response->assertStatus(401);

        // Test delete
        $response = $this->withHeaders(['Accept' => 'application/json'])->deleteJson("/api/products/{$product->id}");
        $response->assertStatus(401);
    }

    public function test_product_quantity_cannot_be_negative(): void
    {
        $productData = [
            'name' => $this->faker->word(),
            'quantity' => -1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_product_name_is_required(): void
    {
        $productData = [
            'quantity' => 10,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_product_quantity_is_required(): void
    {
        $productData = [
            'name' => $this->faker->word(),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }
}
