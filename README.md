<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Laravel Backend System with Extensible Payment Providers

A comprehensive Laravel backend system with user authentication, product management, and an **extensible payment processing system** that supports plug-and-play addition of new payment methods.

## Features

- **JWT Authentication**: Secure token-based authentication
- **Product Management**: CRUD operations for user-owned products
- **Extensible Payment System**: Plug-and-play architecture for payment providers
- **Authorization**: User ownership validation and access control
- **Database Transactions**: Thread-safe quantity management
- **Comprehensive Testing**: Unit and feature tests for critical functionality

## Payment System Architecture

The payment system is designed with **extensibility in mind**:

- **PaymentProviderInterface**: Contract that all payment providers must implement
- **PaymentService**: Main service that manages and routes payments to appropriate providers
- **Individual Providers**: Concrete implementations for each payment method
- **Dynamic Registration**: Easy addition of new payment providers without code changes

### Current Payment Providers

- **CreditCardProvider** - Handles credit card payments (95% success rate)
- **PayPalProvider** - Handles PayPal payments (98% success rate)
- **BankTransferProvider** - Handles bank transfer payments (99% success rate)
- **StripeProvider** - Example provider for Stripe integration (commented out)

### Adding New Payment Providers

See [Payment Provider Documentation](docs/PAYMENT_PROVIDERS.md) for detailed instructions on how to add new payment methods.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- Docker & Docker Compose (for containerized setup)

## Installation

### Option 1: Docker Setup (Recommended)

1. Clone the repository:
```bash
git clone <repository-url>
cd home_task
```

2. Start the Docker containers:
```bash
docker compose up -d
```

The Docker setup includes:
- **Apache2 with PHP 8.2**: Web server with mod_rewrite enabled
- **MySQL 8.0**: Database server
- **Laravel Application**: Running on port 8000

3. Install dependencies and run migrations:
```bash
docker compose exec app composer install
docker compose exec app php artisan migrate
```

4. Generate application keys:
```bash
# Generate Laravel application encryption key
docker compose exec app php artisan key:generate 

# Generate JWT secret for authentication
docker compose exec app php artisan jwt:secret 
```

5. Set proper permissions:
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Quick Setup Test:**
You can run the provided test script to verify the Apache2 setup:
```bash
./test-apache-setup.sh
```

This script will:
- Check if Docker is running
- Build and start the containers
- Verify Apache2 configuration
- Test if the Laravel application is accessible

### Option 2: Local Setup

1. Clone the repository and install dependencies:
```bash
git clone <repository-url>
cd home_task
composer install
```

2. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

3. Configure database in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

4. Run migrations:
```bash
php artisan migrate
```

5. Set proper permissions:
```bash
chmod -R 755 storage bootstrap/cache
```

## Docker Configuration

### Apache2 Setup

The project uses Apache2 with PHP 8.2 as the web server. The configuration includes:

- **Virtual Host**: Configured for `/var/www/html/public` (Laravel's public directory)
- **mod_rewrite**: Enabled for Laravel's URL rewriting
- **Authorization Headers**: Properly configured for JWT authentication
- **Directory Permissions**: Set to allow Laravel's .htaccess rules

### Container Architecture

- **app**: Apache2 + PHP 8.2 container serving the Laravel application
- **db**: MySQL 8.0 container for the database
- **Network**: Bridge network for container communication
- **Volumes**: Persistent storage for database and application files

### Ports

- **8000**: Laravel application (Apache2)
- **3306**: MySQL database

## API Documentation

### Authentication Endpoints

#### Register User
```http
POST /api/users
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### Login
```http
POST /api/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

#### Logout
```http
POST /api/logout
Authorization: Bearer {token}
```

#### Refresh Token
```http
POST /api/refresh
Authorization: Bearer {token}
```

#### Get User Profile
```http
GET /api/me
Authorization: Bearer {token}
```

### Product Endpoints

#### List Products
```http
GET /api/products
Authorization: Bearer {token}
```

#### Create Product
```http
POST /api/products
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Product Name",
    "quantity": 10
}
```

#### Get Product
```http
GET /api/products/{id}
Authorization: Bearer {token}
```

#### Update Product
```http
PUT /api/products/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated Product Name",
    "quantity": 20
}
```

#### Delete Product
```http
DELETE /api/products/{id}
Authorization: Bearer {token}
```

### Payment Endpoints

#### Get Available Payment Methods
```http
GET /api/payment-methods
Authorization: Bearer {token}
```

Response:
```json
{
    "payment_methods": ["credit_card", "paypal", "bank_transfer"]
}
```

#### Initiate Payment
```http
POST /api/payments
Authorization: Bearer {token}
Content-Type: application/json

{
    "product_id": 1,
    "payment_method": "credit_card"
}
```

Response:
```json
{
    "message": "Payment completed successfully",
    "payment": {
        "id": 1,
        "product_id": 1,
        "user_id": 1,
        "payment_method": "credit_card",
        "amount": "99.99",
        "status": "paid"
    },
    "transaction_id": "CC_TXN_64f8a1b2c3d4e",
    "provider": "Credit Card Provider"
}
```

## Testing

Run the test suite:

```bash
# Using Docker
docker compose exec app php artisan test

# Local setup
php artisan test
```

### Test Coverage

- **Authentication Tests**: Registration, login, logout, token refresh
- **Product Tests**: CRUD operations, authorization, ownership validation
- **Payment Tests**: Payment initiation, validation, atomic operations
- **Payment Provider Tests**: Extensibility, provider registration, dynamic methods

### Payment Provider Testing

Test the extensible payment system specifically:

```bash
php artisan test --filter=PaymentProviderTest
```

## Database Schema

### Users Table
- `id` (Primary Key)
- `name` (String)
- `email` (String, Unique)
- `password` (Hashed String)
- `email_verified_at` (Timestamp)
- `remember_token` (String)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

### Products Table
- `id` (Primary Key)
- `name` (String)
- `quantity` (Integer)
- `user_id` (Foreign Key to Users)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

### Payments Table
- `id` (Primary Key)
- `product_id` (Foreign Key to Products)
- `user_id` (Foreign Key to Users)
- `payment_method` (String)
- `amount` (Decimal)
- `status` (Enum: pending, paid, failed)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

## Security Features

- JWT token-based authentication
- Password hashing with bcrypt
- Input validation and sanitization
- User ownership validation
- Database transaction safety
- Rate limiting (configurable)

## Payment System Architecture

The payment system is designed as a **plug-and-play architecture**:

- **Extensible Design**: Easy to integrate real payment gateways
- **Provider Interface**: Consistent contract for all payment methods
- **Dynamic Registration**: Add new providers without code changes
- **Atomic Operations**: Database transactions ensure data consistency
- **Error Handling**: Comprehensive error handling and rollback mechanisms
- **Testing**: Full test coverage for extensibility

### Benefits

1. **Extensibility** - Add new payment methods without touching existing code
2. **Testability** - Each provider can be tested independently
3. **Maintainability** - Clear separation of concerns
4. **Flexibility** - Easy to enable/disable providers
5. **Type Safety** - Interface ensures consistent implementation

## Development

### Code Style
- Follows PSR-12 coding standards
- Uses Laravel Pint for code formatting
- Strict typing enabled

### Architecture
- MVC pattern with Laravel conventions
- Repository pattern ready for data access layer
- Service layer for business logic
- Form requests for validation
- Resource classes for API responses
- **Strategy Pattern** for payment providers

## Deployment

### Production Considerations

1. Update environment variables for production
2. Configure proper database credentials
3. Set up SSL certificates
4. Configure proper logging
5. Set up monitoring and error tracking
6. Configure backup strategies

### Environment Variables

Key environment variables to configure:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

JWT_SECRET=your-jwt-secret
JWT_TTL=60
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
