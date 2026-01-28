# Guia de Teste da API - Postman

## 📋 Índice
1. [API Interna (Laravel)](#api-interna-laravel)
2. [API Evolution (Direta)](#api-evolution-direta)
3. [Como Testar no Postman](#como-testar-no-postman)

---

## 🔧 API Interna (Laravel)

### Criar Instância WhatsApp

**Endpoint:** `POST /settings/whatsapp/connect`

**URL Completa:**
```
https://app2.secretariogreen.com/settings/whatsapp/connect
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
X-CSRF-TOKEN: {seu_token_csrf}
X-Requested-With: XMLHttpRequest
```

**Body (JSON):**
```json
{
  "whatsapp_number": "5531973372872",
  "webhook_url": "https://app2.secretariogreen.com/public/webhook/evolution",
  "events": [
    "APPLICATION_STARTUP",
    "QRCODE_UPDATED",
    "MESSAGES_UPSERT",
    "MESSAGES_UPDATE",
    "CONNECTION_UPDATE"
  ],
  "webhook_base64": false
}
```

**Body (Form-Data) - Alternativo:**
```
whatsapp_number: 5531973372872
webhook_url: https://app2.secretariogreen.com/public/webhook/evolution
events[]: APPLICATION_STARTUP
events[]: QRCODE_UPDATED
events[]: MESSAGES_UPSERT
events[]: MESSAGES_UPDATE
events[]: CONNECTION_UPDATE
webhook_base64: 0
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Instância criada com sucesso! Escaneie o QR Code para conectar.",
  "status": "not_found",
  "qrcode": {
    "base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAVwAAAFcCAYAAACEFgYs..."
  },
  "pairingCode": null,
  "instanceName": "5531973372872",
  "note": "Configure o webhook separadamente após conectar o WhatsApp"
}
```

**Resposta com Pairing Code:**
```json
{
  "success": true,
  "message": "Instância criada com sucesso! Escaneie o QR Code para conectar.",
  "status": "not_found",
  "qrcode": null,
  "pairingCode": "2@LGE/BM63kTw0On9mzgntPoHxGaq1rW5wWNk044+oCe06URUZUsEX9Wu0D7f8FHNFw8Rd/AXDi+lONsxHQtSgH6TKpAD91aynsQY=,K7hJltN4j3iEry8Vvc6wQU/PPt0vxomjRAIXR6H/TV8=,50UU5PoQ9MoShCkMoKLX3JT0YMq6mJe31LsNZqvcDzc=,Kd5OIiVUvObgQfUZfE7M+OTRDspQYHU+GYHFeF6P0Dc=",
  "instanceName": "5531973372872",
  "note": "Configure o webhook separadamente após conectar o WhatsApp"
}
```

---

### Obter QR Code

**Endpoint:** `GET /settings/whatsapp/qrcode`

**URL Completa:**
```
https://app2.secretariogreen.com/settings/whatsapp/qrcode
```

**Headers:**
```
Accept: application/json
X-Requested-With: XMLHttpRequest
```

**Resposta:**
```json
{
  "success": true,
  "qrcode": {
    "base64": "data:image/png;base64,..."
  },
  "pairingCode": null,
  "status": "connecting"
}
```

---

### Verificar Status

**Endpoint:** `GET /settings/whatsapp/status`

**URL Completa:**
```
https://app2.secretariogreen.com/settings/whatsapp/status
```

**Headers:**
```
Accept: application/json
X-Requested-With: XMLHttpRequest
```

**Resposta:**
```json
{
  "status": "open",
  "instanceName": "5531973372872"
}
```

---

### Configurar Webhook (Separadamente)

**Endpoint:** `POST /settings/whatsapp/webhook`

**URL Completa:**
```
https://app2.secretariogreen.com/settings/whatsapp/webhook
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
X-CSRF-TOKEN: {seu_token_csrf}
```

**Body (JSON):**
```json
{
  "url": "https://app2.secretariogreen.com/public/webhook/evolution",
  "events": [
    "APPLICATION_STARTUP",
    "QRCODE_UPDATED",
    "MESSAGES_UPSERT",
    "MESSAGES_UPDATE",
    "CONNECTION_UPDATE"
  ],
  "webhook_base64": false
}
```

---

## 🌐 API Evolution (Direta)

### Criar Instância

**Endpoint:** `POST /instance/create`

**URL Completa:**
```
https://evolution.pedrodrumond.com/instance/create
```

**Headers:**
```
Content-Type: application/json
apikey: 3704373c9accc5918a564f469c99809c
```

**Body (JSON):**
```json
{
  "instanceName": "5531973372872",
  "qrcode": true,
  "integration": "WHATSAPP-BAILEYS"
}
```

**Resposta de Sucesso (201):**
```json
{
  "instance": {
    "instanceName": "5531973372872",
    "instanceId": "8d161d9e-33c4-4822-a3b2-6a0864022ca6",
    "integration": "WHATSAPP-BAILEYS",
    "status": "connecting"
  },
  "qrcode": {
    "code": "2@...",
    "base64": "data:image/png;base64,..."
  }
}
```

---

### Obter QR Code

**Endpoint:** `GET /instance/connect/{instanceName}?qrcode=true`

**URL Completa:**
```
https://evolution.pedrodrumond.com/instance/connect/5531973372872?qrcode=true
```

**Headers:**
```
apikey: 3704373c9accc5918a564f469c99809c
```

**Resposta:**
```json
{
  "qrcode": {
    "code": "2@...",
    "base64": "data:image/png;base64,..."
  }
}
```

---

### Verificar Status da Instância

**Endpoint:** `GET /instance/connectionState/{instanceName}`

**URL Completa:**
```
https://evolution.pedrodrumond.com/instance/connectionState/5531973372872
```

**Headers:**
```
apikey: 3704373c9accc5918a564f469c99809c
```

**Resposta:**
```json
{
  "instance": {
    "instanceName": "5531973372872",
    "state": "open"
  }
}
```

---

### Configurar Webhook

**Endpoint:** `POST /webhook/set/{instanceName}`

**URL Completa:**
```
https://evolution.pedrodrumond.com/webhook/set/5531973372872
```

**Headers:**
```
Content-Type: application/json
apikey: 3704373c9accc5918a564f469c99809c
```

**Body (JSON) - Formato conforme exemplo fornecido:**
```json
{
  "url": "https://app2.secretariogreen.com/public/webhook/evolution/",
  "events": [
    "APPLICATION_STARTUP",
    "QRCODE_UPDATED",
    "MESSAGES_UPSERT",
    "MESSAGES_UPDATE",
    "CONNECTION_UPDATE"
  ],
  "webhook_by_events": false,
  "webhook_base64": false,
  "headers": {
    "Content-Type": "application/json"
  }
}
```

**⚠️ IMPORTANTE:** 
- A URL do webhook deve terminar com `/` (barra final)
- Use `webhook_by_events` e `webhook_base64` (não `byEvents` ou `base64`)

---

## 📮 Como Testar no Postman

### 1. Configurar Variáveis de Ambiente no Postman

Crie um ambiente no Postman com as seguintes variáveis:

```
base_url_laravel: https://app2.secretariogreen.com
base_url_evolution: https://evolution.pedrodrumond.com
api_key: 3704373c9accc5918a564f469c99809c
instance_name: 5531973372872
```

### 2. Testar API Laravel (Criar Instância)

**Método:** `POST`

**URL:** `{{base_url_laravel}}/settings/whatsapp/connect`

**Headers:**
- `Content-Type`: `application/json`
- `Accept`: `application/json`
- `X-CSRF-TOKEN`: (obter do cookie ou sessão)
- `X-Requested-With`: `XMLHttpRequest`

**Body (raw JSON):**
```json
{
  "whatsapp_number": "{{instance_name}}"
}
```

**Nota:** Para obter o CSRF token, primeiro faça uma requisição GET para a página de login ou use o cookie da sessão.

---

### 3. Testar API Evolution Diretamente (Recomendado)

**Método:** `POST`

**URL:** `{{base_url_evolution}}/instance/create`

**Headers:**
- `Content-Type`: `application/json`
- `apikey`: `{{api_key}}`

**Body (raw JSON):**
```json
{
  "instanceName": "{{instance_name}}",
  "qrcode": true,
  "integration": "WHATSAPP-BAILEYS"
}
```

**Resposta Esperada:**
- Status: `201 Created`
- Body contém `qrcode.base64` ou `qrcode.code` (pairing code)

---

### 4. Testar Configuração de Webhook

**Método:** `POST`

**URL:** `{{base_url_evolution}}/webhook/set/{{instance_name}}`

**Headers:**
- `Content-Type`: `application/json`
- `apikey`: `{{api_key}}`

**Body (raw JSON):**
```json
{
  "url": "https://app2.secretariogreen.com/public/webhook/evolution/",
  "events": [
    "APPLICATION_STARTUP",
    "QRCODE_UPDATED",
    "MESSAGES_UPSERT",
    "MESSAGES_UPDATE",
    "CONNECTION_UPDATE"
  ],
  "webhook_by_events": false,
  "webhook_base64": false,
  "headers": {
    "Content-Type": "application/json"
  }
}
```

**⚠️ IMPORTANTE:**
- URL deve terminar com `/`
- Use `webhook_by_events` (não `byEvents`)
- Use `webhook_base64` (não `base64`)

---

## 🔍 Exemplos de Respostas

### Sucesso (201):
```json
{
  "instance": {
    "instanceName": "5531973372872",
    "instanceId": "8d161d9e-33c4-4822-a3b2-6a0864022ca6",
    "integration": "WHATSAPP-BAILEYS",
    "status": "connecting"
  },
  "qrcode": {
    "code": "2@...",
    "base64": "data:image/png;base64,..."
  }
}
```

### Erro 400 (Bad Request):
```json
{
  "status": 400,
  "error": "Bad Request",
  "response": {
    "message": ["Instância já existe"]
  }
}
```

### Erro 401 (Unauthorized):
```json
{
  "status": 401,
  "error": "Unauthorized",
  "response": {
    "message": "API Key inválida"
  }
}
```

---

## 📝 Checklist para Teste no Postman

- [ ] Configurar variáveis de ambiente
- [ ] Testar criar instância (POST `/instance/create`)
- [ ] Verificar resposta (deve ter `qrcode` ou `pairingCode`)
- [ ] Testar obter QR code (GET `/instance/connect/{instanceName}?qrcode=true`)
- [ ] Testar verificar status (GET `/instance/connectionState/{instanceName}`)
- [ ] Testar configurar webhook (POST `/webhook/set/{instanceName}`)
- [ ] Verificar logs do Laravel para debug

---

## 🐛 Troubleshooting

### Erro 400 Bad Request no Webhook:
1. Verifique se a URL termina com `/`
2. Verifique se está usando `webhook_by_events` e `webhook_base64`
3. Verifique se a instância existe antes de configurar webhook
4. Verifique os logs do Laravel para detalhes

### Erro 401 Unauthorized:
1. Verifique se a API Key está correta
2. Verifique se o header `apikey` está sendo enviado
3. Tente usar `Authorization: Bearer {api_key}` se `apikey` não funcionar

### QR Code não aparece:
1. Verifique se `qrcode: true` está no payload
2. Verifique se a resposta contém `qrcode.base64` ou `qrcode.code`
3. Se vier `qrcode.code` com formato `2@...`, é pairing code (não imagem)

---

## 🔗 URLs de Referência

- **Evolution API Base:** `https://evolution.pedrodrumond.com`
- **Laravel App Base:** `https://app2.secretariogreen.com`
- **Webhook URL:** `https://app2.secretariogreen.com/public/webhook/evolution/`
