#!/bin/bash
# Script para limpar TODOS os caches do Laravel e regenerar autoloader
# Execute no servidor: bash clear-all-cache.sh

echo "🧹 Limpando todos os caches do Laravel..."

# Limpar caches do Laravel
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan event:clear

# Regenerar autoloader do Composer (CRÍTICO para remover classes deletadas)
echo "🔄 Regenerando autoloader do Composer..."
composer dump-autoload --optimize

# Limpar cache do OPcache (se estiver ativo)
if command -v php &> /dev/null; then
    echo "⚡ Limpando cache do OPcache..."
    php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache limpo\n'; } else { echo 'OPcache não está ativo\n'; }"
fi

# Remover arquivos de cache compilados
echo "🗑️  Removendo arquivos de cache compilados..."
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-v7.php
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php

echo "✅ Limpeza completa! Reinicie o PHP-FPM se necessário:"
echo "   sudo systemctl restart php8.4-fpm"
echo "   ou"
echo "   sudo service php8.4-fpm restart"
