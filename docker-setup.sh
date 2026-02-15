#!/bin/bash

echo "🚀 SmartERP Docker Setup Script"
echo "================================"

# Copy environment file
if [ ! -f .env ]; then
    echo "📝 Copying .env.docker to .env..."
    cp .env.docker .env
else
    echo "⚠️  .env file already exists, skipping..."
fi

# Build and start containers
echo "🏗️  Building Docker containers..."
docker-compose build

echo "🚀 Starting Docker containers..."
docker-compose up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Generate JWT secret
echo "🔐 Generating JWT secret..."
docker-compose exec app php artisan jwt:secret

# Run migrations
echo "📊 Running database migrations..."
docker-compose exec app php artisan migrate --force

# Create storage link
echo "🔗 Creating storage link..."
docker-compose exec app php artisan storage:link

# Set permissions
echo "🔒 Setting permissions..."
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "✅ Setup complete!"
echo ""
echo "📌 Application URLs:"
echo "   - Application: http://localhost:8080"
echo "   - PhpMyAdmin: http://localhost:8081"
echo ""
echo "🔧 Useful commands:"
echo "   - View logs: docker-compose logs -f"
echo "   - Stop containers: docker-compose down"
echo "   - Restart containers: docker-compose restart"
echo "   - Access app container: docker-compose exec app bash"
