#!/bin/bash
# ==============================================================================
# 🚀 LENTERA (Layanan Elektronik Terpadu Pajak Daerah) - Production One-Click Deployment Script
# Automatic Git Pull, Composer Install, Database Migration & Cache Refresh
# ==============================================================================

set -e

echo "================================================================="
echo "🚀 Memulai Proses Update Auto-Deploy Backend LENTERA..."
echo "================================================================="

# Change directory to project root
PROJECT_DIR="/var/www/pajak-backend"
cd "$PROJECT_DIR" || { echo "❌ Directory $PROJECT_DIR tidak ditemukan!"; exit 1; }

# Put application into maintenance mode (optional, graceful)
echo "🔒 Menutup akses sementara (maintenance mode)..."
php artisan down || true

# Fetch & Pull latest changes from GitHub main branch
echo "📥 Mengambil kode terbaru dari GitHub (origin/main)..."
git fetch origin main
git reset --hard origin/main

# Install production composer dependencies
echo "📦 Menginstal dependensi Composer (Production)..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run database migrations & user seeder safely
echo "🗄️ Menjalankan migrasi database & user seeder..."
php artisan migrate --force
php artisan db:seed --class=DesaSeeder --force
php artisan db:seed --class=UserSeeder --force

# Optimize Laravel Caching & Configuration
echo "⚡ Memperbarui cache konfigurasi, route, dan view..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure correct storage symlink & file permissions
echo "🔒 Memperbarui izin akses folder storage & cache..."
php artisan storage:link || true
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# Bring application back online
echo "🔓 Membuka kembali layanan aplikasi..."
php artisan up

# Reload PHP-FPM & Nginx
echo "🔄 Mereload PHP-FPM & Nginx..."
if systemctl is-active --quiet php8.5-fpm; then
    systemctl reload php8.5-fpm
elif systemctl is-active --quiet php8.4-fpm; then
    systemctl reload php8.4-fpm
fi
systemctl reload nginx

echo "================================================================="
echo "🎉 Update Deployment Berhasil! REST API Backend 100% Terbaru."
echo "================================================================="
