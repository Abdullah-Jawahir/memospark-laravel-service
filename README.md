# 🏗️ MemoSpark Laravel Service - Data Management & User Services

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

**Robust backend service for user management, data persistence, and study analytics**

[🏠 Main Repository](https://github.com/Abdullah-Jawahir/memo-spark) • [🚀 FastAPI Service](https://github.com/Abdullah-Jawahir/memospark-fastapi-service) • [📖 Documentation](https://github.com/Abdullah-Jawahir/memospark-laravel-service/wiki)

</div>

## 📋 Table of Contents

- [🌟 Overview](#-overview)
- [✨ Key Features](#-key-features)
- [🏗️ Architecture](#️-architecture)
- [🛠️ Tech Stack](#️-tech-stack)
- [🚀 Quick Start](#-quick-start)
- [📁 Project Structure](#-project-structure)
- [🗄️ Database Design](#️-database-design)
- [📚 API Documentation](#-api-documentation)
- [🔐 Authentication](#-authentication)
- [🧪 Testing](#-testing)
- [🚀 Deployment](#-deployment)
- [🔧 Configuration](#-configuration)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)
- [👨‍💻 Author](#-author)

## 🌟 Overview

The MemoSpark Laravel Service serves as the backbone for data management, user authentication, study progress tracking, and analytics. Built with Laravel 12 and modern PHP 8.2+, this service provides a robust, scalable foundation for the MemoSpark learning platform.

### 🎯 Core Responsibilities

- **👥 User Management**: Registration, authentication, and profile management
- **📊 Study Analytics**: Progress tracking, performance metrics, and learning insights
- **🗄️ Data Persistence**: Secure storage for user-generated content and study materials
- **🔍 Search Services**: Advanced search functionality for flashcards and study sets
- **📈 Progress Tracking**: Detailed learning analytics and goal management
- **🔐 Security**: Authentication, authorization, and data protection

## ✨ Key Features

### 👤 User Management

- **Secure Registration & Login**: Email-based authentication with email verification
- **Profile Management**: Customizable user profiles with learning preferences
- **Role-Based Access Control**: Admin, teacher, and student roles with appropriate permissions
- **Social Authentication**: Support for Google, Facebook, and other OAuth providers

### 📚 Study Management

- **Flashcard Collections**: Organize and manage study materials
- **Study Sessions**: Track study time and learning progress
- **Performance Analytics**: Detailed insights into learning patterns
- **Goal Setting**: Personal learning objectives and milestone tracking

### 🔍 Advanced Search

- **Smart Search**: AI-powered search across all study materials
- **Filter Options**: Advanced filtering by subject, difficulty, date, and more
- **Search History**: Track and revisit previous searches
- **Saved Searches**: Bookmark frequently used search queries

### 📊 Analytics & Reporting

- **Learning Dashboard**: Comprehensive overview of study progress
- **Performance Metrics**: Success rates, time spent, areas of improvement
- **Progress Reports**: Detailed analytics for students and educators
- **Export Capabilities**: PDF and Excel report generation

### 🔧 Administrative Features

- **User Management**: Admin dashboard for user oversight
- **Content Moderation**: Review and manage user-generated content
- **System Analytics**: Platform usage statistics and insights
- **Configuration Management**: System settings and feature toggles

## 🏗️ Architecture

### Service Architecture

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│                     │    │                     │    │                     │
│   React Frontend    │◄──►│  Laravel Service    │◄──►│  FastAPI Service    │
│   (User Interface)  │    │  (Data Management)  │    │  (AI Processing)    │
│                     │    │                     │    │                     │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
                                      │
                                      ▼
                           ┌─────────────────────┐
                           │                     │
                           │       Supabase      |
                           |          +          |  
                           |    MySQL Database   │
                           │    (Data Storage)   │
                           │                     │
                           └─────────────────────┘
```

### Laravel Service Responsibilities

- **API Gateway**: Central hub for frontend-backend communication
- **Data Layer**: Database operations and data validation
- **Business Logic**: User workflows and application rules
- **Authentication**: Secure user management and session handling
- **Analytics Engine**: Study progress and performance tracking

## 🛠️ Tech Stack

### Core Framework

- **🚀 Laravel 12** - Modern PHP framework with advanced features
- **🐘 PHP 8.2+** - Latest PHP with performance improvements
- **🗄️ MySQL 8.0+** - Robust relational database management

### Laravel Ecosystem

- **🔐 Laravel Sanctum** - API authentication and token management
- **📧 Laravel Mail** - Email services and notifications
- **⏰ Laravel Queue** - Background job processing
- **📊 Laravel Scout** - Full-text search capabilities
- **🧪 Laravel Testing** - Comprehensive testing framework

### Additional Libraries

- **📄 Laravel Excel** - Excel import/export functionality
- **📱 Laravel Notification** - Multi-channel notifications
- **🔄 Laravel Horizon** - Queue monitoring and management
- **📈 Laravel Telescope** - Application debugging and insights

### Development Tools

- **🎨 Laravel Pint** - Code style fixer
- **🚢 Laravel Sail** - Docker development environment
- **🔧 Laravel Tinker** - Interactive REPL
- **📊 Laravel Debugbar** - Debug toolbar for development

## 🚀 Quick Start

### Prerequisites

- **PHP 8.2 or higher**
- **Composer** (PHP dependency manager)
- **MySQL 8.0+** or **PostgreSQL 13+**
- **Node.js 18+** (for asset compilation)
- **Git** for version control

### Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/Abdullah-Jawahir/memospark-laravel-service.git
   cd memospark-laravel-service
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install Node.js dependencies**

   ```bash
   npm install
   ```

4. **Environment setup**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure environment variables**

   ```env
   APP_NAME="MemoSpark"
   APP_ENV=local
   APP_KEY=base64:your-generated-key
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=memospark
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   QUEUE_CONNECTION=database
   CACHE_STORE=database

   FASTAPI_URL=http://localhost:8001

   SUPABASE_URL=Your-supabase-url-here
   SUPABASE_KEY=Your-supabase-key
   SUPABASE_SERVICE_ROLE_KEY
   ```

6. **Database setup**

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Start the development server**

   ```bash
   php artisan serve --port=8000
   ```

8. **Compile assets (optional)**

   ```bash
   npm run dev
   ```

9. **Verify installation**
   Open `http://localhost:8000/api/health` to confirm the service is running.

## 📁 Project Structure

```
memospark-laravel-service/
├── app/
│   ├── Console/              # Artisan commands
│   ├── Events/               # Event classes
│   ├── Exceptions/           # Exception handling
│   ├── Http/
│   │   ├── Controllers/      # API controllers
│   │   │   ├── Auth/         # Authentication controllers
│   │   │   ├── Admin/        # Admin management
│   │   │   ├── Study/        # Study-related endpoints
│   │   │   └── Search/       # Search functionality
│   │   ├── Middleware/       # HTTP middleware
│   │   ├── Requests/         # Form request validation
│   │   └── Resources/        # API resources
│   ├── Jobs/                 # Background jobs
│   ├── Listeners/            # Event listeners
│   ├── Mail/                 # Mail classes
│   ├── Models/               # Eloquent models
│   │   ├── User.php          # User model
│   │   ├── Flashcard.php     # Flashcard model
│   │   ├── StudySession.php  # Study session tracking
│   │   └── Progress.php      # Progress tracking
│   ├── Notifications/        # Notification classes
│   ├── Policies/             # Authorization policies
│   ├── Providers/            # Service providers
│   └── Services/             # Business logic services
│       ├── AuthService.php   # Authentication service
│       ├── StudyService.php  # Study management
│       └── AnalyticsService.php  # Analytics processing
├── bootstrap/                # Application bootstrap
├── config/                   # Configuration files
│   ├── database.php          # Database configuration
│   ├── mail.php             # Email configuration
│   └── sanctum.php          # API authentication
├── database/
│   ├── factories/           # Model factories
│   ├── migrations/          # Database migrations
│   └── seeders/            # Database seeders
├── public/                  # Public assets
├── resources/
│   ├── js/                  # JavaScript assets
│   ├── css/                 # CSS assets
│   └── views/               # Blade templates
├── routes/
│   ├── api.php              # API routes
│   ├── web.php              # Web routes
│   └── console.php          # Artisan routes
├── storage/                 # Application storage
│   ├── app/                 # Application files
│   ├── framework/           # Framework files
│   └── logs/                # Log files
├── tests/
│   ├── Feature/             # Feature tests
│   ├── Unit/                # Unit tests
│   └── TestCase.php         # Base test class
├── vendor/                  # Composer dependencies
├── .env.example             # Environment template
├── composer.json            # PHP dependencies
├── package.json             # Node.js dependencies
└── README.md               # This file
```

## 🗄️ Database Design

### Core Tables

#### Users Table

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    preferences JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Flashcards Table

```sql
CREATE TABLE flashcards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    language VARCHAR(5) DEFAULT 'en',
    subject VARCHAR(100) NULL,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Study Sessions Table

```sql
CREATE TABLE study_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    flashcard_id BIGINT UNSIGNED NOT NULL,
    response_time INT NOT NULL,
    is_correct BOOLEAN NOT NULL,
    difficulty_rating INT NULL,
    session_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (flashcard_id) REFERENCES flashcards(id) ON DELETE CASCADE
);
```

### Relationships

- **Users** have many **Flashcards**
- **Users** have many **Study Sessions**
- **Flashcards** belong to **Users**
- **Study Sessions** belong to **Users** and **Flashcards**

## 📚 API Documentation

### Authentication Endpoints

#### Register User

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}

Response:
{
  "user": {...},
  "token": "1|abc123...",
  "message": "Registration successful"
}
```

#### Login User

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}

Response:
{
  "user": {...},
  "token": "2|def456...",
  "message": "Login successful"
}
```

### Study Management Endpoints

#### Get User Flashcards

```http
GET /api/flashcards
Authorization: Bearer {token}

Query Parameters:
- page: int (pagination)
- subject: string (filter by subject)
- difficulty: string (filter by difficulty)
- search: string (search query)

Response:
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15
  }
}
```

#### Create Flashcard

```http
POST /api/flashcards
Authorization: Bearer {token}
Content-Type: application/json

{
  "question": "What is Laravel?",
  "answer": "Laravel is a PHP web application framework",
  "difficulty": "beginner",
  "language": "en",
  "subject": "Programming",
  "tags": ["php", "framework", "web"]
}
```

### Analytics Endpoints

#### Get Study Progress

```http
GET /api/analytics/progress
Authorization: Bearer {token}

Query Parameters:
- period: string (daily, weekly, monthly)
- start_date: date
- end_date: date

Response:
{
  "total_sessions": 45,
  "accuracy_rate": 0.87,
  "study_time": 1200,
  "progress_data": [...],
  "subject_breakdown": [...]
}
```

### Search Endpoints

#### Search Flashcards

```http
GET /api/search/flashcards
Authorization: Bearer {token}

Query Parameters:
- q: string (search query)
- filters: json (advanced filters)
- sort: string (sort criteria)
- limit: int (result limit)

Response:
{
  "results": [...],
  "total": 25,
  "query": "laravel",
  "filters_applied": {...}
}
```

## 🔐 Authentication

### Laravel Sanctum Integration

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),

'guard' => ['web'],

'expiration' => null,

'middleware' => [
    'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
],
```

### Token Management

```php
// Generate token
$token = $user->createToken('auth-token')->plainTextToken;

// Revoke token
$user->currentAccessToken()->delete();

// Token with abilities
$token = $user->createToken('auth-token', ['read', 'write'])->plainTextToken;
```

### Middleware Protection

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'profile']);
    Route::apiResource('flashcards', FlashcardController::class);
    Route::get('/analytics/progress', [AnalyticsController::class, 'progress']);
});
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/AuthTest.php

# Run tests with database refresh
php artisan test --env=testing
```

### Test Structure

```php
// tests/Feature/FlashcardTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Flashcard;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlashcardTest extends TestCase
{
    public function test_user_can_create_flashcard()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/flashcards', [
            'question' => 'Test question?',
            'answer' => 'Test answer',
            'difficulty' => 'beginner'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure(['data' => ['id', 'question', 'answer']]);
    }
}
```

### Database Testing

```php
// Use in-memory SQLite for testing
// config/database.php (testing environment)
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
],
```

## 🚀 Deployment

### Production Setup

#### Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.memospark.com

DB_CONNECTION=mysql
DB_HOST=your-production-host
DB_DATABASE=memospark_production
DB_USERNAME=production_user
DB_PASSWORD=secure_production_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
```

#### Deployment Commands

```bash
# Optimize for production
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Docker Deployment

```dockerfile
# Dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Expose port
EXPOSE 9000

CMD ["php-fpm"]
```

### Load Balancer Configuration

```nginx
# nginx configuration
upstream laravel_backend {
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
    server 127.0.0.1:8003;
}

server {
    listen 80;
    server_name api.memospark.com;

    location / {
        proxy_pass http://laravel_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 🔧 Configuration

### Performance Optimization

```php
// config/app.php
'providers' => [
    // Remove unnecessary providers for API-only applications
    // App\Providers\BroadcastServiceProvider::class,
    // App\Providers\EventServiceProvider::class,
],

// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

### Queue Configuration

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### Background Jobs

```php
// app/Jobs/ProcessStudyAnalytics.php
<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStudyAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnalyticsService $analyticsService)
    {
        $analyticsService->processUserProgress();
    }
}
```

## 🤝 Contributing

### Development Guidelines

1. **Code Standards**
   - Follow PSR-12 coding standards
   - Use Laravel conventions and best practices
   - Write comprehensive tests for new features
   - Document API endpoints with OpenAPI/Swagger

2. **Git Workflow**

   ```bash
   # Create feature branch
   git checkout -b feature/user-analytics
   
   # Make changes and commit
   git add .
   git commit -m "feat: add user analytics dashboard"
   
   # Push and create pull request
   git push origin feature/user-analytics
   ```

3. **Testing Requirements**

   ```bash
   # Run tests before committing
   php artisan test
   php artisan pint  # Code style
   php artisan insights  # Code quality
   ```

### Code Review Process

- All changes require pull request review
- Automated tests must pass
- Code coverage should not decrease
- Documentation must be updated for API changes

## 📄 License

This project is licensed under the MIT License. See [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

<div align="center">

### **Abdullah Jawahir**

*Laravel Expert & Backend Developer*

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Abdullah_Jawahir-blue?style=flat&logo=linkedin)](https://www.linkedin.com/in/mohamed-jawahir-abdullah/)
[![GitHub](https://img.shields.io/badge/GitHub-Abdullah_Jawahir-black?style=flat&logo=github)](https://github.com/Abdullah-Jawahir)
[![Email](https://img.shields.io/badge/Email-Contact_Me-red?style=flat&logo=gmail)](mailto:mjabdullah33@gmail.com)

---

**🚀 Building scalable backend solutions for modern applications**

*Made with ❤️ and the power of Laravel*

---

### 🔗 Related Services

- **[MemoSpark Frontend](https://github.com/Abdullah-Jawahir/memo-spark)** - React-based user interface
- **[MemoSpark FastAPI Service](https://github.com/Abdullah-Jawahir/memospark-fastapi-service)** - AI content generation engine

</div>

---

<div align="center">

**⭐ If you found this service helpful, please star the repository! ⭐**

**🐛 Found an issue? [Report it here](https://github.com/Abdullah-Jawahir/memospark-laravel-service/issues)**

**💡 Have a suggestion? [Let us know](https://github.com/Abdullah-Jawahir/memospark-laravel-service/issues)**

</div>

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
