# MySQL Database Deployment Guide

This guide covers setting up and configuring a MySQL database on Railway (and other platforms) for your Laravel application.

## Table of Contents

1. [Railway MySQL Setup](#railway-mysql-setup)
2. [Connection Configuration](#connection-configuration)
3. [Laravel Configuration](#laravel-configuration)
4. [Database Migrations](#database-migrations)
5. [Backup & Restore](#backup--restore)
6. [Performance Optimization](#performance-optimization)
7. [Troubleshooting](#troubleshooting)

---

## Railway MySQL Setup

### Step 1: Create MySQL Service

1. Open your Railway project
2. Click **"+ New"** button
3. Select **"Database"**
4. Choose **"MySQL"**

Railway will automatically:
- Provision a MySQL server
- Generate credentials
- Create a database named `railway`

### Step 2: Get Connection Details

Go to your MySQL service and click the **"Variables"** tab. You'll see:

| Variable | Description | Example |
|----------|-------------|---------|
| `MYSQL_URL` | Full connection URL | `mysql://root:pass@host:3306/railway` |
| `MYSQLHOST` | Internal hostname | `mysql.railway.internal` |
| `MYSQLPORT` | Internal port | `3306` |
| `MYSQLDATABASE` | Database name | `railway` |
| `MYSQLUSER` | Username | `root` |
| `MYSQLPASSWORD` | Password | (auto-generated) |

### Step 3: Get Public Connection URL

1. Go to MySQL service → **Settings**
2. Under **Networking**, enable **Public Networking**
3. You'll get a public URL like: `shortline.proxy.rlwy.net:33588`

⚠️ The public port (e.g., `33588`) is different from internal port (`3306`)!

---

## Connection Configuration

### Internal vs Public Connections

| Type | When to Use | Format |
|------|-------------|--------|
| **Internal** | Services in same Railway project | `mysql.railway.internal:3306` |
| **Public** | External apps, local dev, debugging | `shortline.proxy.rlwy.net:33588` |

### Connection String Formats

```
# URL Format
mysql://root:password@host:port/database

# Separate Values
Host: shortline.proxy.rlwy.net
Port: 33588
Database: railway
Username: root
Password: your-password
```

### Choosing Host/Port

**Option A: Using Railway Variables (Internal)**

```env
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
```

✅ Works when both services are in same project  
❌ May fail if region mismatch or networking issues

**Option B: Using Public URL (Recommended for stability)**

```env
DB_HOST=shortline.proxy.rlwy.net
DB_PORT=33588
```

✅ Always works  
✅ Easier to debug  
⚠️ Slightly higher latency

---

## Laravel Configuration

### Environment Variables

Set these in Railway (Variables tab) or `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=shortline.proxy.rlwy.net
DB_PORT=33588
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=your-generated-password
```

### config/database.php

Laravel's default MySQL configuration:

```php
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

### SSL Configuration (if required)

Some platforms require SSL. Add to your database config:

```php
'options' => [
    PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-certificate.crt',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
],
```

Or disable SSL verification (not recommended for production):

```php
'options' => [
    PDO::ATTR_EMULATE_PREPARES => true,
],
```

---

## Database Migrations

### Running Migrations on Deploy

The `entrypoint.sh` script runs migrations automatically:

```bash
#!/bin/sh
# ... other setup ...

# Run migrations
php artisan migrate --force --no-interaction

# Start services
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

### Manual Migration Commands

```bash
# Run all pending migrations
php artisan migrate

# Run migrations (production - no confirmation)
php artisan migrate --force

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (drops all tables)
php artisan migrate:fresh  # CAREFUL: Destroys all data!

# Check migration status
php artisan migrate:status
```

### Creating Migrations

```bash
# Create a new migration
php artisan make:migration create_users_table

# Create migration for existing table
php artisan make:migration add_role_to_users_table --table=users
```

---

## Backup & Restore

### Manual Backup via Railway CLI

```bash
# Install Railway CLI
npm install -g @railway/cli

# Login
railway login

# Connect to MySQL
railway connect mysql

# Inside MySQL shell, use mysqldump
```

### Using mysqldump Directly

```bash
# Backup
mysqldump -h shortline.proxy.rlwy.net -P 33588 -u root -p railway > backup.sql

# Restore
mysql -h shortline.proxy.rlwy.net -P 33588 -u root -p railway < backup.sql
```

### Automated Backups

Railway doesn't have built-in automated backups. Consider:

1. **Using a cron job** in your Laravel app:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:run')->daily();
}
```

2. **Using Spatie Laravel Backup** package:

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

---

## Performance Optimization

### Indexing

Add indexes to frequently queried columns:

```php
// In migration
Schema::table('users', function (Blueprint $table) {
    $table->index('email');
    $table->index(['created_at', 'status']);
});
```

### Query Optimization

```php
// Bad: N+1 problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();
}

// Good: Eager loading
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count();
}
```

### Connection Pooling

For high-traffic applications, consider connection pooling:

```php
// config/database.php
'mysql' => [
    // ... other config ...
    'options' => [
        PDO::ATTR_PERSISTENT => true,  // Persistent connections
    ],
],
```

### Railway Resource Limits

| Plan | RAM | Connections |
|------|-----|-------------|
| Trial | 512MB | ~50 |
| Hobby | 1GB | ~100 |
| Pro | 8GB+ | ~500+ |

---

## Troubleshooting

### Connection Refused

**Symptoms**:
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions**:

1. **Check host/port are correct**
   ```bash
   # Test connection
   mysql -h shortline.proxy.rlwy.net -P 33588 -u root -p
   ```

2. **Use public URL instead of internal**
   ```env
   DB_HOST=shortline.proxy.rlwy.net
   DB_PORT=33588
   ```

3. **Verify MySQL service is running**
   - Go to Railway dashboard
   - Check MySQL service status

### Connection Timed Out

**Symptoms**:
```
SQLSTATE[HY000] [2002] Operation timed out
```

**Solutions**:

1. **Check region consistency**
   - Both services should be in same region
   
2. **Increase connection timeout**
   ```php
   'mysql' => [
       'options' => [
           PDO::ATTR_TIMEOUT => 30,
       ],
   ],
   ```

3. **Use public URL**
   - Internal networking can have issues

### Access Denied

**Symptoms**:
```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'...'
```

**Solutions**:

1. **Verify credentials**
   - Copy password directly from Railway Variables tab
   - Watch for trailing spaces

2. **Check database name**
   - Default is `railway`, not your app name

### Too Many Connections

**Symptoms**:
```
SQLSTATE[HY000] [1040] Too many connections
```

**Solutions**:

1. **Reduce connections in queue workers**
   ```bash
   # supervisord.conf
   command=php artisan queue:work --sleep=3 --tries=3
   ```

2. **Use database sessions sparingly**
   ```env
   SESSION_DRIVER=file  # Instead of database
   ```

3. **Upgrade Railway plan** for more connections

---

## Quick Reference

### Connection Test Script

Create `test_db.php`:

```php
<?php
$host = 'shortline.proxy.rlwy.net';
$port = '33588';
$db = 'railway';
$user = 'root';
$pass = 'your-password';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    echo "Connected successfully!\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
```

### Useful MySQL Commands

```sql
-- Show databases
SHOW DATABASES;

-- Show tables
SHOW TABLES;

-- Show table structure
DESCRIBE users;

-- Show current connections
SHOW PROCESSLIST;

-- Check database size
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
GROUP BY table_schema;
```

### Laravel Artisan Database Commands

```bash
# Test database connection
php artisan db

# Run raw SQL
php artisan tinker
>>> DB::select('SELECT 1');

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

---

## Alternative Platforms

### PlanetScale (Recommended for Production)

- Serverless MySQL
- Automatic scaling
- Branching (like Git for databases)

```env
DB_CONNECTION=mysql
DB_HOST=us-east.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

### AWS RDS

- Managed MySQL
- Multi-AZ for high availability
- Automated backups

### Render PostgreSQL

If using Render, they provide PostgreSQL instead:

```env
DB_CONNECTION=pgsql
DB_HOST=your-db.render.com
DB_PORT=5432
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

Update `config/database.php` to use `pgsql` driver.
