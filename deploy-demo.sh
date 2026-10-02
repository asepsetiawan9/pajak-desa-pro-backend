#!/bin/bash
# ==============================================================================
# 🚀 LENTERA DEMO (Desa Sukatani) - Production One-Click Deployment Script
# Isolated Git Pull, Composer Install, Database Migration & Cache Refresh
# ==============================================================================

set -e

echo "================================================================="
echo "🚀 Memulai Proses Update Auto-Deploy Backend LENTERA DEMO..."
echo "================================================================="

PROJECT_DIR="/var/www/pajak-demo-backend"
cd "$PROJECT_DIR" || { echo "❌ Directory $PROJECT_DIR tidak ditemukan!"; exit 1; }

echo "📥 Mengambil kode terbaru dari GitHub (origin/main)..."
git fetch origin main
git reset --hard origin/main

echo "📦 Menginstal dependensi Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🗄️ Menjalankan migrasi database demo..."
php artisan migrate --force

echo "⚡ Memperbarui cache konfigurasi, route, dan view..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "🔒 Memperbarui izin akses folder storage & cache..."
php artisan storage:link || true
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

echo "🔄 Mereload PHP-FPM & Nginx..."
systemctl reload php8.5-fpm
systemctl reload nginx

echo "================================================================="
echo "🎉 Update Deployment LENTERA DEMO Berhasil 100%!"
echo "================================================================="
