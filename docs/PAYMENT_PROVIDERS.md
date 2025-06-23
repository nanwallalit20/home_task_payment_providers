# Payment Provider System

This document explains how the extensible payment provider system works and how to add new payment providers.

## Architecture Overview

The payment system is designed with a **plug-and-play architecture** that allows easy addition of new payment methods without modifying existing code. It follows the **Strategy Pattern** and **Dependency Injection** principles.

### Key Components

1. **PaymentProviderInterface** - Contract that all payment providers must implement
2. **PaymentService** - Main service that manages and routes payments to appropriate providers
3. **PaymentServiceProvider** - Laravel service provider that registers payment providers
4. **Individual Providers** - Concrete implementations for each payment method

## Current Payment Providers

- **CreditCardProvider** - Handles credit card payments
- **PayPalProvider** - Handles PayPal payments  
- **BankTransferProvider** - Handles bank transfer payments
- **StripeProvider** - Example provider for Stripe payments (commented out)

## How to Add a New Payment Provider

### Step 1: Create the Provider Class

Create a new class that implements `PaymentProviderInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;

class YourNewProvider implements PaymentProviderInterface
{
    public function process(Payment $payment): array
    {
        // Your payment processing logic here
        $success = $this->processWithYourGateway($payment);
        
        if ($success) {
            return [
                'success' => true,
                'transaction_id' => 'YOUR_TXN_' . uniqid(),
                'processing_time' => 1.5,
                'provider' => $this->getName(),
            ];
        }

        return [
            'success' => false,
            'error' => 'Payment processing failed',
            'provider' => $this->getName(),
        ];
    }

    public function getName(): string
    {
        return 'Your New Provider';
    }

    public function supports(string $paymentMethod): bool
    {
        return in_array($paymentMethod, $this->getSupportedMethods());
    }

    public function getSupportedMethods(): array
    {
        return ['your_method', 'your_alternative_method'];
    }

    private function processWithYourGateway(Payment $payment): bool
    {
        // Integrate with your payment gateway here
        // Example: Stripe, Square, etc.
        return true; // or false based on result
    }
}
```

### Step 2: Register the Provider

Add your provider to `app/Providers/PaymentServiceProvider.php`:

```php
public function register(): void
{
    $this->app->singleton(PaymentService::class, function ($app) {
        $paymentService = new PaymentService();
        
        // Register default providers
        $paymentService->registerProvider(new CreditCardProvider());
        $paymentService->registerProvider(new PayPalProvider());
        $paymentService->registerProvider(new BankTransferProvider());
        
        // Register your new provider
        $paymentService->registerProvider(new YourNewProvider());
        
        return $paymentService;
    });
}
```

### Step 3: Test Your Provider

Create tests for your provider:

```php
public function test_your_new_provider(): void
{
    $provider = new YourNewProvider();
    
    $this->assertTrue($provider->supports('your_method'));
    $this->assertFalse($provider->supports('invalid_method'));
    $this->assertEquals('Your New Provider', $provider->getName());
    
    // Test payment processing
    $payment = Payment::factory()->create([
        'payment_method' => 'your_method',
    ]);
    
    $result = $provider->process($payment);
    $this->assertArrayHasKey('success', $result);
}
```

## API Endpoints

### Get Available Payment Methods
```http
GET /api/payment-methods
Authorization: Bearer {token}
```

Response:
```json
{
    "payment_methods": ["credit_card", "paypal", "bank_transfer", "your_method"]
}
```

### Process Payment
```http
POST /api/payments
Authorization: Bearer {token}
Content-Type: application/json

{
    "product_id": 1,
    "payment_method": "your_method"
}
```

## Benefits of This Architecture

1. **Extensibility** - Add new payment methods without touching existing code
2. **Testability** - Each provider can be tested independently
3. **Maintainability** - Clear separation of concerns
4. **Flexibility** - Easy to enable/disable providers
5. **Type Safety** - Interface ensures consistent implementation

## Real-World Integration Examples

### Stripe Integration
```php
class StripeProvider implements PaymentProviderInterface
{
    public function process(Payment $payment): array
    {
        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            
            $charge = $stripe->charges->create([
                'amount' => $payment->amount * 100, // Stripe uses cents
                'currency' => 'usd',
                'source' => $payment->stripe_token,
                'description' => "Payment for product {$payment->product_id}",
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $charge->id,
                'provider' => $this->getName(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $this->getName(),
            ];
        }
    }
}
```

### PayPal Integration
```php
class PayPalProvider implements PaymentProviderInterface
{
    public function process(Payment $payment): array
    {
        // Integrate with PayPal SDK
        $paypal = new PayPalClient();
        
        $result = $paypal->processPayment([
            'amount' => $payment->amount,
            'currency' => 'USD',
            'payment_id' => $payment->paypal_payment_id,
        ]);
        
        return [
            'success' => $result->success,
            'transaction_id' => $result->transaction_id,
            'provider' => $this->getName(),
        ];
    }
}
```

## Configuration

Payment providers can be configured in `config/services.php`:

```php
'stripe' => [
    'secret' => env('STRIPE_SECRET'),
    'publishable' => env('STRIPE_PUBLISHABLE'),
],

'paypal' => [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'mode' => env('PAYPAL_MODE', 'sandbox'),
],
```

## Error Handling

All providers should handle errors gracefully and return consistent error responses:

```php
return [
    'success' => false,
    'error' => 'Human-readable error message',
    'provider' => $this->getName(),
    'error_code' => 'OPTIONAL_ERROR_CODE',
];
```

## Testing

Run the payment provider tests:

```bash
php artisan test --filter=PaymentProviderTest
```

This will test the extensible payment system and verify that new providers can be added correctly. 
