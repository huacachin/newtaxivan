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
# Sin view:cache: Livewire 3 solo inyecta sus marcadores de morph ([if BLOCK]) cuando la vista
# se compila dentro de un render; compiladas por artisan salen sin ellos y los @if de los
# formularios corren los campos al redibujar. Se compilan solas en el primer uso.
php artisan view:clear
php artisan permission:cache-reset

echo "✅ Deploy completado."
