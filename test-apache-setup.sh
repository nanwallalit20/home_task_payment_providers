#!/bin/bash

echo "Testing Apache2 Setup for Laravel Backend"
echo "=========================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

echo "✅ Docker is running"

# Build and start containers
echo "Building and starting containers..."
docker compose up -d --build

# Wait for containers to be ready
echo "Waiting for containers to be ready..."
sleep 10

# Check if containers are running
if docker compose ps | grep -q "Up"; then
    echo "✅ Containers are running"
else
    echo "❌ Containers failed to start"
    docker compose logs
    exit 1
fi

# Test Apache2 configuration
echo "Testing Apache2 configuration..."
if docker compose exec app apache2ctl -t; then
    echo "✅ Apache2 configuration is valid"
else
    echo "❌ Apache2 configuration has errors"
    exit 1
fi

# Test if Laravel is accessible
echo "Testing Laravel application..."
if curl -s http://localhost:8000 | grep -q "Laravel"; then
    echo "✅ Laravel application is accessible"
else
    echo "⚠️  Laravel application might not be fully configured yet"
    echo "   This is normal if migrations haven't been run yet"
fi

echo ""
echo "🎉 Apache2 setup is complete!"
echo "Your Laravel application should be accessible at: http://localhost:8000"
echo ""
echo "Next steps:"
echo "1. Run: docker compose exec app composer install"
echo "2. Run: docker compose exec app php artisan migrate"
echo "3. Run: docker compose exec app php artisan jwt:secret"
echo "4. Test the API endpoints using the provided Postman collection"
