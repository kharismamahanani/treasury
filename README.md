# Treasury Dashboard
**Sistem Manajemen Kas & Investasi — PTNBH**

Stack: **Laravel 10** · **PHP 8.1+** · **PostgreSQL** · **Blade + Alpine.js** · **Chart.js**

---

## Prasyarat Server

| Komponen     | Versi minimum |
|--------------|---------------|
| PHP          | 8.1           |
| PostgreSQL   | 13+           |
| Composer     | 2.x           |
| Nginx/Apache | —             |

**PHP Extensions yang dibutuhkan:**
```
pdo_pgsql, mbstring, xml, curl, gd, tokenizer, openssl, bcmath
```

---

## Instalasi

### 1. Siapkan database PostgreSQL
```sql
-- Jalankan di psql sebagai superuser
CREATE USER treasury_user WITH PASSWORD 'ganti_password_kuat_ini';
CREATE DATABASE treasury_db OWNER treasury_user;
GRANT ALL PRIVILEGES ON DATABASE treasury_db TO treasury_user;
```

### 2. Clone & Install
```bash
# Upload project ke server, kemudian:
cd /var/www/treasury-dashboard
composer install --no-dev --optimize-autoloader
```

### 3. Konfigurasi `.env`
```bash
cp .env.example .env
nano .env
```

Isi minimal:
```env
APP_URL=http://treasury.ptnbh.ac.id    # Sesuaikan
APP_KEY=                               # Akan diisi otomatis
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=treasury_db
DB_USERNAME=treasury_user
DB_PASSWORD=ganti_password_kuat_ini
```

### 4. Deploy otomatis
```bash
chmod +x deploy.sh
./deploy.sh
```

Atau manual:
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Konfigurasi Nginx

```nginx
server {
    listen 80;
    server_name treasury.ptnbh.ac.id;
    root /var/www/treasury-dashboard/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

---

## Struktur Project

```
treasury-laravel/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php   — KPI, summary, trend
│   │   ├── BankController.php        — CRUD bank
│   │   ├── ProductController.php     — CRUD produk, import CSV, best yield
│   │   └── UserController.php        — Manajemen pengguna (admin)
│   └── Models/
│       ├── Bank.php
│       ├── Product.php               — Accessor: days_until_maturity, urgency
│       ├── BalanceHistory.php
│       └── User.php                  — Role: admin/editor/viewer
├── database/
│   ├── migrations/                   — 4 tabel: users, banks, products, balance_histories
│   └── seeders/DatabaseSeeder.php    — Data bank & produk sample
├── resources/views/
│   ├── layouts/app.blade.php         — Layout utama + semua CSS
│   ├── auth/login.blade.php
│   ├── dashboard/index.blade.php     — 7 views dalam satu SPA
│   └── partials/
│       ├── sidebar.blade.php
│       ├── topbar.blade.php
│       └── modals.blade.php
├── public/js/treasury.js             — Semua logika frontend
└── routes/web.php                    — Auth routes + API JSON routes
```

---

## Akun Default

| Username    | Password          | Role    | Hak Akses        |
|-------------|-------------------|---------|------------------|
| `admin`     | `Admin@12345`     | Admin   | Akses penuh      |
| `bendahara` | `Bendahara@2024`  | Editor  | Input & edit data|
| `pimpinan`  | `Pimpinan@2024`   | Viewer  | Hanya lihat      |

> ⚠️ **Ganti semua password segera setelah deploy pertama!**

---

## Fitur Utama

| Modul             | Keterangan                                                              |
|-------------------|-------------------------------------------------------------------------|
| **Overview**      | KPI saldo total, distribusi per tipe/bank (chart), tren historis       |
| **Produk**        | CRUD kas, deposito, giro, tabungan — IDR & USD, filter multi-dimensi   |
| **Imbal Hasil**   | Ranking bank per return tertinggi, best yield card per kategori/currency|
| **Jatuh Tempo**   | Alert deposito dalam 7/30/90 hari, instruksi ARO/non-ARO/pencairan     |
| **Import CSV**    | Upload massal data produk, validasi per baris, download template        |
| **Master Bank**   | CRUD bank: BUMN/Swasta/Asing/Daerah, PIC, cabang                       |
| **Pengguna**      | Role-based access (admin/editor/viewer), audit trail created_by         |

---

## Database Schema (Ringkas)

```sql
-- Tabel utama
users           → id, name, username, email, password, role, is_active, last_login_at
banks           → id, name, code, type, branch, pic_name, pic_phone, is_active, deleted_at
products        → id, bank_id, type, account_number, currency, balance, yield_rate,
                  tenor_days, placement_date, maturity_date, rollover_instruction,
                  is_active, created_by, updated_by, deleted_at
balance_histories → id, product_id, bank_id, currency, balance, yield_rate,
                    source, note, recorded_by, recorded_at
```

---

## Keamanan

- Session-based auth (Laravel Breeze pattern)
- CSRF protection aktif di semua form & API call
- Role guard di controller level
- SoftDelete untuk produk & bank (data tidak hilang permanen)
- Password di-hash dengan bcrypt
- `.env` di-exclude dari version control

---

## Backup Database

```bash
# Backup
pg_dump -U treasury_user -h 127.0.0.1 treasury_db > backup_$(date +%Y%m%d).sql

# Restore
psql -U treasury_user -h 127.0.0.1 treasury_db < backup_20240101.sql
```

Disarankan otomasi backup harian via cron:
```bash
0 2 * * * pg_dump -U treasury_user treasury_db | gzip > /backup/treasury_$(date +\%Y\%m\%d).sql.gz
```
