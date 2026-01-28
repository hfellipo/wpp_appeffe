#!/usr/bin/env php
<?php
/**
 * Script para limpar todos os caches do Laravel
 * Execute via CLI: php clear-cache.php
 * 
 * IMPORTANTE: Este script deve ser executado no diretório raiz do projeto Laravel
 */

echo "🧹 Limpando todos os caches do Laravel...\n\n";

// Verificar se estamos no diretório correto
if (!file_exists(__DIR__ . '/artisan')) {
    echo "❌ ERRO: Arquivo artisan não encontrado. Execute este script no diretório raiz do projeto Laravel.\n";
    exit(1);
}

// Comandos para limpar cache
$commands = [
    'php artisan optimize:clear',
    'php artisan route:clear',
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan view:clear',
    'php artisan event:clear',
];

foreach ($commands as $command) {
    echo "Executando: {$command}\n";
    exec($command, $output, $returnCode);
    if ($returnCode !== 0) {
        echo "⚠️  Aviso: Comando retornou código {$returnCode}\n";
    }
    echo "\n";
}

// Regenerar autoloader do Composer
echo "🔄 Regenerando autoloader do Composer...\n";
exec('composer dump-autoload --optimize', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Autoloader regenerado com sucesso\n\n";
} else {
    echo "⚠️  Aviso: Erro ao regenerar autoloader\n\n";
}

// Remover arquivos de cache compilados manualmente
echo "🗑️  Removendo arquivos de cache compilados...\n";
$cacheFiles = [
    __DIR__ . '/bootstrap/cache/config.php',
    __DIR__ . '/bootstrap/cache/routes-v7.php',
    __DIR__ . '/bootstrap/cache/services.php',
    __DIR__ . '/bootstrap/cache/packages.php',
];

foreach ($cacheFiles as $file) {
    if (file_exists($file)) {
        unlink($file);
        echo "  ✓ Removido: " . basename($file) . "\n";
    }
}

// Limpar OPcache se disponível
echo "\n⚡ Verificando OPcache...\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpo\n";
} else {
    echo "ℹ️  OPcache não está ativo\n";
}

echo "\n✅ Limpeza completa!\n";
echo "\n💡 Se o problema persistir, reinicie o PHP-FPM:\n";
echo "   sudo systemctl restart php8.4-fpm\n";
echo "   ou\n";
echo "   sudo service php8.4-fpm restart\n";
echo "\n";
