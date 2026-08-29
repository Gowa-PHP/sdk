# gowa-php (SDK para GOWA)

SDK em PHP puro para integração com o servidor **go-whatsapp-web-multidevice** ([GOWA](https://github.com/aldinokemal/go-whatsapp-web-multidevice)), permitindo pareamento de aparelhos, envio e recebimento de mensagens e gestão de mídias via WhatsApp Web.

> 🇺🇸 For English documentation, see [README.md](README.md).

## Instalação

```bash
composer require gowa/gowa-php
```

## Requisitos

- PHP >= 8.2
- Extensão `json` e `hash`
- Cliente HTTP compatível com PSR-18 ou Guzzle Client

## Exemplo de Uso

### 1. Inicializar as Configurações e o Cliente

```php
use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;

$config = new Config(
    baseUrl: 'https://gowa.suaempresa.com',
    username: 'admin',
    password: 'secretpassword',
    timeout: 15
);

$client = new GowaClient($config);
```

### 2. Pareamento de Aparelho (QR Code ou Código de 8 Dígitos)

```php
// Criar ou registrar o dispositivo e webhook
$device = $client->createDevice(
    deviceId: 'minha-instancia-uuid',
    webhookUrl: 'https://minhaapi.com/webhooks/gowa/minha-instancia-uuid',
    webhookSecret: 'minha_chave_hmac_48_chars',
    events: ['message', 'message.ack', 'message.reaction']
);

// Iniciar pareamento por QR Code
$pairing = $client->startQrPairing('minha-instancia-uuid');
echo $pairing->qrLink; // URL do QR Code

// Ou pedir código de 8 dígitos para digitar no celular
$codePairing = $client->startCodePairing('minha-instancia-uuid', '5511999998888');
echo $codePairing->pairCode; // Ex: ABCD-1234
```

### 3. Envio de Mensagens e Mídias

```php
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\MediaPayload;

// Enviar texto
$client->sendText('minha-instancia-uuid', '5511999998888', 'Olá! Mensagem enviada via gowa-php SDK.');

// Enviar recado de voz (PTT) a partir de URL externa
$upload = MediaUpload::fromUrl('https://minhaempresa.com/storage/recado.m4a');
$media = new MediaPayload(type: MediaType::Audio, upload: $upload, voice: true);

$client->sendMedia('minha-instancia-uuid', '5511999998888', $media);

// Enviar link com prévia visual
$client->sendLink('minha-instancia-uuid', '5511999998888', 'https://fazz.ai', 'Confira nosso site');

// Enviar enquete interativa
$client->sendPoll('minha-instancia-uuid', '5511999998888', 'Qual seu horário preferido?', ['Manhã', 'Tarde', 'Noite']);
```

### 4. Validação de Webhooks (HMAC SHA-256)

```php
use Gowa\Sdk\Security\WebhookSignature;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$secret = 'minha_chave_hmac_48_chars';

if (!WebhookSignature::verify($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Assinatura inválida');
}
```

## Executando os Testes (Pest PHP)

```bash
vendor/bin/pest
```

## Licença

MIT
