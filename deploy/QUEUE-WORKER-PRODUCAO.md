# Deixar o job da fila ligado em produção

Para automações com **“Aguardar X min”** executarem no horário exato, o worker da fila precisa estar rodando em produção.

## Opção 1: Supervisor (recomendado)

1. **Instale o Supervisor** no servidor (se ainda não tiver):
   ```bash
   # Debian/Ubuntu
   sudo apt install supervisor
   ```

2. **Copie e ajuste o arquivo de configuração**
   - Copie `deploy/supervisor-laravel-worker.conf` para o Supervisor.
   - Ajuste no arquivo:
     - `command`: path completo do `artisan` (ex.: `/var/www/SecretarioApp/artisan`).
     - `user`: usuário do servidor (ex.: `www-data`, `deploy`).
     - `stdout_logfile`: path do log do worker (ex.: `/var/www/SecretarioApp/storage/logs/worker.log`).

   ```bash
   sudo cp deploy/supervisor-laravel-worker.conf /etc/supervisor/conf.d/secretarioapp-worker.conf
   sudo nano /etc/supervisor/conf.d/secretarioapp-worker.conf
   ```

3. **Crie o log e dê permissão**
   ```bash
   touch /var/www/SecretarioApp/storage/logs/worker.log
   chown www-data:www-data /var/www/SecretarioApp/storage/logs/worker.log
   ```

4. **Recarregue e inicie o worker**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start secretarioapp-worker:*
   ```

5. **Comandos úteis**
   ```bash
   sudo supervisorctl status              # Ver status
   sudo supervisorctl restart secretarioapp-worker:*   # Reiniciar após deploy
   sudo supervisorctl stop secretarioapp-worker:*      # Parar
   ```

---

## Opção 2: systemd

Se preferir systemd em vez de Supervisor, crie um service:

```bash
# /etc/systemd/system/secretarioapp-queue.service
[Unit]
Description=SecretarioApp Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/SecretarioApp
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Depois:
```bash
sudo systemctl daemon-reload
sudo systemctl enable secretarioapp-queue
sudo systemctl start secretarioapp-queue
sudo systemctl status secretarioapp-queue
```

---

## Opção 3: Um único processo (temporário)

Só para testar ou em servidor sem Supervisor/systemd:

```bash
cd /var/www/SecretarioApp
php artisan queue:work database --sleep=3 --tries=3
```

O processo precisa ficar rodando (ex.: com `screen` ou `tmux`). Se fechar o terminal, o worker para.

---

## Conferir se está funcionando

- **Fila:** no `.env` deve ter `QUEUE_CONNECTION=database` (ou `redis` se usar Redis).
- **Tabela de jobs:** o Laravel usa a tabela `jobs`. Jobs com delay aparecem lá até chegar a hora.
- **Log do worker:** erros e processamento aparecem em `storage/logs/worker.log` (se configurou no Supervisor) ou no terminal onde rodou `queue:work`.

Após deploy, reinicie o worker para carregar o código novo:
`sudo supervisorctl restart secretarioapp-worker:*`
