#!/bin/bash
set -e

echo "🚀 Iniciando deploy..."

cd "$(dirname "$0")"

echo "📥 Descargando cambios..."
git pull origin main

echo "📦 Instalando dependencias PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🗄️ Ejecutando migraciones..."
php artisan migrate --force

echo "🧹 Limpiando cachés..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset

echo "✅ Deploy completado."
