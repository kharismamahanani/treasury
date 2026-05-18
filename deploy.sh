#!/bin/bash
set -e

echo "========================================"
echo " Treasury Dashboard — Deploy Script"
echo " Laravel 10 + PostgreSQL"
echo "========================================"

# ── Cek prasyarat ─────────────────────────────────────────────────────────────
echo ""
echo "[CHECK] Memeriksa prasyarat..."

php_ver=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "0")
if [ "$php_ver" = "0" ]; then
  echo "  ✗ PHP tidak ditemukan. Install PHP 8.1+ terlebih dahulu."
  exit 1
fi
echo "  ✓ PHP $php_ver"

if ! php -m | grep -q pdo_pgsql; then
  echo "  ✗ Ekstensi pdo_pgsql tidak aktif. Jalankan: apt install php${php_ver}-pgsql"
  exit 1
fi
echo "  ✓ pdo_pgsql"

# ── Buat direktori yang diperlukan ────────────────────────────────────────────
echo ""
echo "[1/7] Memastikan direktori storage tersedia..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache
echo "  ✓ Direktori siap"

# ── Install Composer ──────────────────────────────────────────────────────────
echo "[2/7] Menginstall dependensi Composer..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
echo "  ✓ Dependensi terinstall"

# ── .env setup ───────────────────────────────────────────────────────────────
echo "[3/7] Menyiapkan konfigurasi .env..."
if [ ! -f .env ]; then
  cp .env.example .env
  echo "  → .env dibuat dari .env.example"
  echo ""
  echo "  ╔══════════════════════════════════════════════╗"
  echo "  ║  WAJIB: Edit .env sebelum lanjut             ║"
  echo "  ║  - DB_HOST, DB_DATABASE, DB_USERNAME         ║"
  echo "  ║  - DB_PASSWORD                               ║"
  echo "  ║  - APP_URL                                   ║"
  echo "  ╚══════════════════════════════════════════════╝"
  echo ""
  echo "  Tekan ENTER setelah selesai edit .env..."
  read -r
fi

# ── Generate key ──────────────────────────────────────────────────────────────
echo "[4/7] Menggenerate APP_KEY..."
php artisan key:generate --force
echo "  ✓ APP_KEY di-generate"

# ── Database ──────────────────────────────────────────────────────────────────
echo "[5/7] Menjalankan migrasi database..."
php artisan migrate --force
echo "  ✓ Migrasi selesai"

echo "[6/7] Mengisi data awal (seeder)..."
php artisan db:seed --force
echo "  ✓ Seeder selesai"

# ── Cache & permission ────────────────────────────────────────────────────────
echo "[7/7] Optimasi cache & permission..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
  chown -R $(whoami) storage bootstrap/cache

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║  ✅ Deploy selesai!                                  ║"
echo "║                                                      ║"
echo "║  🌐 Buka: sesuai APP_URL di .env                    ║"
echo "║  🔐 Login: admin / Admin@12345                       ║"
echo "║  ⚠  Segera ganti password default!                  ║"
echo "╚══════════════════════════════════════════════════════╝"

# ── Record deployment version ────────────────────────────────────────────────
echo ""
echo "[+] Mencatat versi deployment ke database..."
VERSION=${1:-"1.0.0"}
TYPE=${2:-"patch"}
php artisan deploy:record "${VERSION}" --type="${TYPE}" --env="${APP_ENV:-production}" 2>/dev/null || \
  echo "  → Catat versi manual via menu Version Control di aplikasi"

echo ""
echo "  ℹ  Untuk catat versi berikutnya:"
echo "     php artisan deploy:record 1.1.0 --type=minor --notes='Tambah fitur X'"
