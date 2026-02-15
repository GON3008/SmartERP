@echo off
echo ========================================
echo SmartERP Docker Setup Script (Windows)
echo ========================================
echo.

REM Copy environment file
if not exist .env (
    echo [*] Copying .env.docker to .env...
    copy .env.docker .env
) else (
    echo [!] .env file already exists, skipping...
)

REM Build and start containers
echo [*] Building Docker containers...
docker-compose build

echo [*] Starting Docker containers...
docker-compose up -d

REM Wait for MySQL to be ready
echo [*] Waiting for MySQL to be ready...
timeout /t 10 /nobreak > nul

REM Generate application key
echo [*] Generating application key...
docker-compose exec app php artisan key:generate

REM Generate JWT secret
echo [*] Generating JWT secret...
docker-compose exec app php artisan jwt:secret

REM Run migrations
echo [*] Running database migrations...
docker-compose exec app php artisan migrate --force

REM Create storage link
echo [*] Creating storage link...
docker-compose exec app php artisan storage:link

REM Set permissions
echo [*] Setting permissions...
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache

echo.
echo ========================================
echo Setup complete!
echo ========================================
echo.
echo Application URLs:
echo   - Application: http://localhost:8080
echo   - PhpMyAdmin: http://localhost:8081
echo.
echo Useful commands:
echo   - View logs: docker-compose logs -f
echo   - Stop containers: docker-compose down
echo   - Restart containers: docker-compose restart
echo   - Access app container: docker-compose exec app bash
echo.
pause
