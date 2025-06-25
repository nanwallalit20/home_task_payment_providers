<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_many_products(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $this->assertCount(3, $user->products);
        $this->assertInstanceOf(Product::class, $user->products->first());
    }

    public function test_user_products_relationship_returns_correct_products(): void
    {
        $user = User::factory()->create();
        $userProducts = Product::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        // Create products for another user
        Product::factory()->count(3)->create();

        $this->assertCount(2, $user->products);

        foreach ($user->products as $product) {
            $this->assertEquals($user->id, $product->user_id);
        }
    }

    public function test_user_jwt_identifier_returns_user_id(): void
    {
        $user = User::factory()->create();

        $this->assertEquals($user->id, $user->getJWTIdentifier());
    }

    public function test_user_jwt_custom_claims_returns_empty_array(): void
    {
        $user = User::factory()->create();

        $this->assertEquals([], $user->getJWTCustomClaims());
    }

    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plaintext_password',
        ]);

        $this->assertNotEquals('plaintext_password', $user->password);
        $this->assertTrue(password_verify('plaintext_password', $user->password));
    }

    public function test_user_hidden_attributes_are_not_serialized(): void
    {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_user_fillable_attributes_can_be_mass_assigned(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $user = User::create($userData);

        $this->assertEquals($userData['name'], $user->name);
        $this->assertEquals($userData['email'], $user->email);
        $this->assertTrue(password_verify($userData['password'], $user->password));
    }

    public function test_user_casts_are_applied_correctly(): void
    {
        $user = User::factory()->create();

        $this->assertIsArray($user->getCasts());
        $this->assertArrayHasKey('email_verified_at', $user->getCasts());
        $this->assertArrayHasKey('password', $user->getCasts());
    }
}
