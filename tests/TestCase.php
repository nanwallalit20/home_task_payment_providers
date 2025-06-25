<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected string $token;
    protected Product $product;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create and authenticate a user for testing.
     */
    protected function createAuthenticatedUser(): void
    {
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }

    /**
     * Create a product for the authenticated user.
     */
    protected function createProduct(array $attributes = []): Product
    {
        $defaultAttributes = [
            'user_id' => $this->user->id,
            'quantity' => 10,
        ];

        return Product::factory()->create(array_merge($defaultAttributes, $attributes));
    }

    /**
     * Create authenticated user and product for testing.
     */
    protected function setupUserAndProduct(array $productAttributes = []): void
    {
        $this->createAuthenticatedUser();
        $this->product = $this->createProduct($productAttributes);
    }

    /**
     * Make an authenticated API request.
     */
    protected function authenticatedRequest(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->json($method, $uri, $data);
    }

    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
