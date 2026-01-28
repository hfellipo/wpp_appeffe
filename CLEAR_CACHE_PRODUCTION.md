# 🔧 Como Limpar Cache no Servidor de Produção

## Problema
O erro `Target class [App\Http\Controllers\EvolutionApiController] does not exist` ocorre porque o servidor de produção está usando cache de rotas antigo que referencia um controller que não existe mais.

## Soluções

### ✅ Solução 1: Script PHP (Recomendado)

Execute o script diretamente no servidor via SSH:

```bash
cd /caminho/para/o/projeto
php clear-cache.php
```

Este script:
- Limpa todos os caches do Laravel
- Regenera o autoloader do Composer
- Remove arquivos de cache compilados
- Limpa o OPcache (se ativo)

### ✅ Solução 2: Comandos Artisan Manuais

Se preferir executar os comandos manualmente:

```bash
cd /caminho/para/o/projeto

# Limpar todos os caches
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan event:clear

# Regenerar autoloader (CRÍTICO)
composer dump-autoload --optimize

# Remover arquivos de cache compilados manualmente
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-v7.php
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php
```

### ✅ Solução 3: Script Bash Existente

Use o script bash que já existe no projeto:

```bash
cd /caminho/para/o/projeto
bash clear-all-cache.sh
```

### ⚠️ Solução 4: Limpar Cache Manualmente (Se Artisan não funcionar)

Se os comandos `php artisan` não funcionarem devido ao cache quebrado:

```bash
cd /caminho/para/o/projeto

# Remover arquivos de cache diretamente
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-v7.php
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php

# Limpar cache de views
rm -rf storage/framework/views/*

# Limpar cache de sessões (opcional)
rm -rf storage/framework/sessions/*

# Regenerar autoloader
composer dump-autoload --optimize
```

### 🔄 Após Limpar o Cache

**IMPORTANTE:** Reinicie o PHP-FPM para garantir que o OPcache seja limpo:

```bash
# Para systemd
sudo systemctl restart php8.4-fpm

# Para service
sudo service php8.4-fpm restart

# Ou reinicie o servidor web (Apache/Nginx)
sudo systemctl restart apache2
# ou
sudo systemctl restart nginx
```

## Verificação

Após limpar o cache, acesse:
- `https://app2.secretariogreen.com/public/settings/whatsapp`

A página deve carregar sem erros.

## Nota de Segurança

⚠️ **IMPORTANTE:** Após resolver o problema, considere remover a rota temporária `/admin/clear-cache` do arquivo `routes/web.php` por questões de segurança.

## Troubleshooting

Se o problema persistir após limpar o cache:

1. Verifique se o arquivo `app/Http/Controllers/WhatsAppEvolutionController.php` existe no servidor
2. Verifique se o autoloader foi regenerado: `composer dump-autoload --optimize`
3. Verifique permissões dos arquivos: `chmod -R 755 storage bootstrap/cache`
4. Verifique logs: `tail -f storage/logs/laravel.log`
