# 🐳 SmartERP - Docker Setup

Dự án SmartERP Laravel đã được cấu hình để chạy trên Docker với đầy đủ các services cần thiết.

## 🚀 Khởi Động Nhanh

### Windows
```powershell
docker-setup.bat
```

### Linux/Mac
```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

## 📦 Services Bao Gồm

- **Nginx** - Web server (Port 8080)
- **PHP 8.2-FPM** - Laravel application
- **MySQL 8.0** - Database (Port 3306)
- **Redis** - Cache & Queue (Port 6379)
- **PhpMyAdmin** - Database management (Port 8081)

## 🌐 URLs

- Application: http://localhost:8080
- PhpMyAdmin: http://localhost:8081

## 📚 Tài Liệu Đầy Đủ

Xem file [DOCKER_GUIDE.md](./DOCKER_GUIDE.md) để biết chi tiết về:
- Hướng dẫn cài đặt
- Các lệnh hữu ích
- Xử lý sự cố
- Production deployment

## 🔧 Lệnh Cơ Bản

```bash
# Khởi động
docker-compose up -d

# Dừng
docker-compose down

# Xem logs
docker-compose logs -f

# Truy cập container
docker-compose exec app bash

# Chạy Artisan commands
docker-compose exec app php artisan migrate
```

## ⚙️ Cấu Hình

Chỉnh sửa file `.env.docker` để thay đổi cấu hình database, cache, và các services khác.

## 🔐 Thông Tin Mặc Định

**Database:**
- Host: mysql
- Database: smarterp
- Username: root
- Password: secret

**Redis:**
- Host: redis
- Port: 6379


# Xem logs real-time
docker-compose logs -f app

# Chạy migrations hoặc seeders
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed

# Clear cache
docker-compose exec app php artisan cache:clear

# Truy cập vào container để debug
docker-compose exec app bash

# Restart một service cụ thể
docker-compose restart app
