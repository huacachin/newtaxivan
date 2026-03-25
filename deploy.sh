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

echo "🔑 Creando roles nuevos..."
php artisan db:seed --class=RoleSetupSeeder --force

echo "🔑 Creando permisos nuevos..."
php artisan db:seed --class=PermissionCatalogSeeder --force

echo "🔄 Migrando roles de usuarios (admin→administrador, controller→controlador)..."
php artisan users:migrate-roles

echo "🧹 Limpiando cachés..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset

echo "✅ Deploy completado."
