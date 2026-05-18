# Panduan Instalasi Treasury Dashboard

## Prasyarat
Pastikan tersedia di server:
- PHP 8.1+ dengan ekstensi: `pdo_pgsql, mbstring, xml, curl, gd, tokenizer, openssl, bcmath`
- Composer 2.x
- PostgreSQL 13+
- Git (opsional)

Cek dengan:
```bash
php -v && composer -V
php -m | grep -E "pdo_pgsql|mbstring|xml|curl|gd"
```

## Langkah Instalasi

### 1. Siapkan PostgreSQL
```sql
-- Jalankan sebagai user postgres
CREATE USER treasury_user WITH PASSWORD 'ganti_dengan_password_kuat';
CREATE DATABASE treasury_db OWNER treasury_user;
GRANT ALL PRIVILEGES ON DATABASE treasury_db TO treasury_user;
\q
```

### 2. Upload & Extract Project
```bash
unzip treasury-laravel-v3.zip
cd treasury-laravel
```

### 3. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

> ✅ Jika berhasil: lanjut ke langkah 4  
> ❌ Jika error "Could not open input file: artisan": lihat Troubleshooting di bawah

### 4. Konfigurasi Environment
```bash
cp .env.example .env
```

Edit `.env`:
```env
APP_URL=http://ip-server-anda-atau-domain
DB_HOST=127.0.0.1
DB_DATABASE=treasury_db
DB_USERNAME=treasury_user
DB_PASSWORD=password_yang_dibuat_tadi
```

### 5. Generate Key & Migrate
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

### 6. Permission & Cache
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan route:cache
```

### 7. Jalankan
```bash
# Development/test (tanpa web server)
php artisan serve --host=0.0.0.0 --port=8000

# Production: konfigurasi Nginx/Apache (lihat README.md)
```

---

## Troubleshooting

### Error: "Could not open input file: artisan"
Ini terjadi karena `composer install --no-scripts` dijalankan, 
atau file `artisan` tidak ada. Solusi:
```bash
# Pastikan file artisan ada dan executable
ls -la artisan
chmod +x artisan

# Kemudian jalankan ulang
composer dump-autoload
php artisan package:discover
```

### Error: "Please provide a valid cache path"
```bash
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Error: "SQLSTATE: could not connect to server"
```bash
# Cek PostgreSQL berjalan
pg_isready -h 127.0.0.1 -p 5432

# Cek koneksi dengan user treasury
psql -U treasury_user -h 127.0.0.1 -d treasury_db -c "\l"
```

### Error: "Class App\Http\Kernel not found"
```bash
composer dump-autoload --optimize
```

---

## Login Default
| Username | Password | Role |
|----------|----------|------|
| `admin` | `Admin@12345` | Admin |
| `bendahara` | `Bendahara@2024` | Editor |
| `pimpinan` | `Pimpinan@2024` | Viewer |

**Ganti semua password segera setelah login pertama!**
