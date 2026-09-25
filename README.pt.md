<div align="center">
  <img src="art/banner.png" alt="gowa-php Banner" width="100%" max-width="800">

  # gowa-php

  **SDK em PHP puro para o servidor REST GOWA (go-whatsapp-web-multidevice), alimentado pelo motor whatsmeow**

  [![Última Versão Estável](https://img.shields.io/packagist/v/gowa-php/sdk.svg?style=flat-square)](https://packagist.org/packages/gowa-php/sdk)
  [![Total de Downloads](https://img.shields.io/packagist/dt/gowa-php/sdk.svg?style=flat-square)](https://packagist.org/packages/gowa-php/sdk)
  [![Plumb score](https://plumbphp.dev/badges/gowa-php/sdk/composite.svg)](https://plumbphp.dev/gowa-php/sdk)
  [![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-blue.svg?style=flat-square)](LICENSE)
  [![Versão do PHP](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4.svg?style=flat-square)](https://php.net)

</div>

---

> 🇺🇸 For English documentation, see [README.md](README.md).

---

## ⚡ Agradecimentos e Dependências

Este SDK interage com o ecossistema backend em Go criado pela comunidade open-source:

- **[whatsmeow](https://go.mau.fi/whatsmeow)** — Biblioteca Go criada por [Tulir Asokan](https://github.com/tulir) que faz a engenharia reversa do protocolo WebSocket do WhatsApp Web Multi-Device e criptografia Signal.
- **[go-whatsapp-web-multidevice (GOWA)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** — O servidor API REST criado por [Aldino Kemal](https://github.com/aldinokemal) que expõe o `whatsmeow` via HTTP e Webhooks.
- **[Especificação OpenAPI](https://github.com/aldinokemal/go-whatsapp-web-multidevice/blob/main/docs/openapi.yaml)** — Contrato oficial OpenAPI 3.0 do GOWA ([Visualizar no Swagger Editor](https://editor.swagger.io/?url=https://raw.githubusercontent.com/aldinokemal/go-whatsapp-web-multidevice/main/docs/openapi.yaml)).

---

## Instalação

```bash
composer require gowa-php/sdk
```

## Requisitos

- Uma instância em execução do servidor REST [GOWA (go-whatsapp-web-multidevice)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)
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

// Ou injete um handler customizado do Guzzle (ex: para testes ou HandlerStack do Http::fake() do Laravel) sem perder as opções do Config:
// $client = new GowaClient($config, handler: $customHandler);
```

### 2. Gestão e Pareamento de Aparelhos

```php
// Criar ou registrar o dispositivo e webhook
$device = $client->createDevice(
    deviceId: 'minha-instancia-uuid',
    webhookUrl: 'https://minhaapi.com/webhooks/gowa/minha-instancia-uuid',
    webhookSecret: 'minha_chave_hmac_48_chars',
    events: ['message', 'message.ack', 'message.reaction', 'message.edited', 'message.revoked']
);

// Iniciar pareamento por QR Code
$pairing = $client->startQrPairing('minha-instancia-uuid');
echo $pairing->qrLink; // URL do QR Code

// Ou pedir código de 8 dígitos para digitar no celular
$codePairing = $client->startCodePairing('minha-instancia-uuid', '5511999998888');
echo $codePairing->pairCode; // Ex: ABCD-1234

// Listar todos os aparelhos registrados no servidor
$devices = $client->devices();

// Reconectar ou purgar/excluir permanentemente o slot de um aparelho
$client->reconnectDevice('minha-instancia-uuid');
$client->deleteDevice('minha-instancia-uuid');

// Checar se um número está no WhatsApp antes de disparar mensagens
if ($client->checkUser('minha-instancia-uuid', '5511999998888')) {
    echo "Número possui WhatsApp!";
}
```

### 3. Envio de Mensagens e Mídias

#### Texto, Links, Enquetes e Menções
```php
// Enviar texto com menções (@everyone ou números) e mensagens efêmeras (24h = 86400s)
$client->sendText(
    deviceId: 'minha-instancia-uuid',
    to: '120363xxxx@g.us',
    text: 'Olá @everyone e @5511999998888!',
    mentions: ['5511999998888', '@everyone'],
    duration: 86400,
);

// Enviar link com prévia visual
$client->sendLink('minha-instancia-uuid', '5511999998888', 'https://fazz.ai', 'Confira nosso site');

// Enviar enquete interativa
$client->sendPoll('minha-instancia-uuid', '5511999998888', 'Qual seu horário preferido?', ['Manhã', 'Tarde', 'Noite']);
```

#### Upload de Mídias (Arquivos, URLs, Menções e Visualização Única)
```php
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\MediaPayload;

// Enviar recado de voz (PTT) a partir de URL externa
$upload = MediaUpload::fromUrl('https://minhaempresa.com/storage/recado.m4a');
$media = new MediaPayload(type: MediaType::Audio, upload: $upload, voice: true);
$client->sendMedia('minha-instancia-uuid', '5511999998888', $media);

// Enviar imagem local com menção na legenda e visualização única (view-once) ativada
$imgUpload = MediaUpload::fromPath('/caminho/para/fatura.jpg');
$imgMedia = new MediaPayload(
    type: MediaType::Image,
    upload: $imgUpload,
    caption: 'Fatura para @5511999998888',
    mentions: ['5511999998888'],
    viewOnce: true,
);
$client->sendMedia('minha-instancia-uuid', '5511999998888', $imgMedia);
```

#### Envios Agendados e Recorrentes (GOWA v9.5+)
```php
use Gowa\Sdk\Dto\ScheduleOptions;

// Agendar mensagem pontual ou com recorrência
$schedule = new ScheduleOptions(
    scheduledAt: '2026-10-01T09:00:00Z',
    timezone: 'America/Sao_Paulo',
    recurrence: 'weekly',
    weekdays: [1, 3, 5], // Segunda, Quarta e Sexta
    endAt: '2026-12-31T23:59:59Z',
    occurrenceLimit: 20,
);

$sent = $client->sendText(
    deviceId: 'minha-instancia-uuid',
    to: '5511999998888',
    text: 'Lembrete semanal!',
    schedule: $schedule,
);

echo $sent->scheduleId; // Ex: '0b5c3a8e-7f5d-4d6f-9d1e-2f8c1a7b9e10'

// Gestão de agendamentos
$schedules = $client->listSchedules('minha-instancia-uuid');
$client->pauseSchedule('minha-instancia-uuid', $sent->scheduleId);
$client->resumeSchedule('minha-instancia-uuid', $sent->scheduleId);
$client->cancelSchedule('minha-instancia-uuid', $sent->scheduleId);
```

#### Ações em Mensagens (Encaminhar, Editar, Revogar, Reagir, Favoritar e Histórico)
```php
// Encaminhar mensagem (também suporta agendamento via ScheduleOptions)
$client->forwardMessage('minha-instancia-uuid', '5511999998888', 'WAMID_ORIGINAL_123');

// Editar mensagem enviada
$client->editMessage('minha-instancia-uuid', '5511999998888', 'WAMID_ORIGINAL_123', 'Texto atualizado');

// Enviar reação de emoji
$client->sendReaction('minha-instancia-uuid', '5511999998888', 'WAMID_ORIGINAL_123', '👍');

// Revogar (Apagar para todos)
$client->revokeMessage('minha-instancia-uuid', '5511999998888', 'WAMID_ORIGINAL_123');

// Favoritar ou desfavoritar mensagem
$client->starMessage('minha-instancia-uuid', '5511999998888', 'WAMID_ORIGINAL_123', true);

// Solicitar histórico antigo de mensagens sob demanda direto do telefone
$client->requestChatHistory('minha-instancia-uuid', '5511999998888', count: 50);
```

#### Download de Mídias (Candidatos de Telefone para Eco, Timeout e Limpeza)
```php
use Gowa\Sdk\Exceptions\MediaUnavailableException;

// 1. Consultar mídia com telefones candidatos (ex: eco de saída do vendedor vs contato)
try {
    $remoteMedia = $client->describeMedia(
        deviceId: 'minha-instancia-uuid',
        phones: ['5511888888888', '5511999999999'], // testa os candidatos na ordem
        providerMessageId: 'WAMID_ORIGINAL_123'
    );
} catch (MediaUnavailableException $e) {
    // Recusa permanente da mídia (ex: mensagem sem mídia ou formato não suportado)
    $remoteMedia = null;
}

// 2. Baixar os bytes descriptografados com timeout dedicado
if ($remoteMedia !== null) {
    $client->downloadMedia(
        mediaUrl: $remoteMedia->url,
        destinationPath: '/caminho/para/arquivo.mp4',
        timeout: 120 // timeout dedicado para downloads grandes
    );
}
```

### 4. Validação de Webhooks & Parse de Eventos

```php
use Gowa\Sdk\Dto\EventPayload;
use Gowa\Sdk\Dto\LiveLocationPayload;
use Gowa\Sdk\Dto\PollPayload;
use Gowa\Sdk\Security\WebhookSignature;
use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\WebhookParser;

$payload = file_get_contents('php://input');
// O servidor GOWA envia o cabeçalho no formato "sha256=<hex>"
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$secret = 'minha_chave_hmac_48_chars';

// 1. Validar assinatura HMAC SHA-256 (exige o prefixo "sha256=")
if (!WebhookSignature::verify($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Assinatura inválida');
}

// 2. Converter payload do webhook com roteamento fluente
WebhookParser::parse($payload)
    ->onMessage(function (IncomingMessage $msg) {
        $msg
            ->whenLiveLocation(function (LiveLocationPayload $loc) {
                echo "Coordenadas: {$loc->latitude}, {$loc->longitude}\n";
            })
            ->whenPoll(function (PollPayload $poll) {
                echo "Pergunta: {$poll->question}\n";
            })
            ->whenEvent(function (EventPayload $event) {
                echo "Título do evento: {$event->name}\n";
            })
            ->whenText(function (string $text) {
                echo "Texto: {$text}\n";
            })
            ->otherwise(function (IncomingMessage $msg) {
                echo "Outro tipo de mensagem: {$msg->type}\n";
            });
    })
    ->onAck(function (IncomingAck $ack) {
        echo "Confirmação: {$ack->receiptType}\n";
    })
    ->otherwise(function (mixed $data, Event $event) {
        echo "Evento desconhecido ou não tratado: {$event->value}\n";
    });

// Acesso tradicional via array e métodos de inspeção continuam 100% suportados:
// $event = WebhookParser::parse($payload);
// if ($event->isMessage()) { $msg = $event->message(); ... }
// ou $event['event'] === Event::Message
```

## Resumo dos Recursos Disponíveis

### Gerenciamento de Dispositivos

| Recurso | Método | Endpoint |
|---|---|---|
| Criar Dispositivo & Webhook | `createDevice()` | `POST /devices` |
| Listar Dispositivos Registrados | `devices()`, `listDevices()` | `GET /devices` |
| Atualizar Configurações de Webhook | `updateWebhook()` | `PATCH /devices/:id/webhook` |
| Iniciar Pareamento por QR Code | `startQrPairing()` | `GET /devices/:id/login` |
| Iniciar Pareamento por Código de 8 Dígitos | `startCodePairing()` | `POST /devices/:id/login/code` |
| Consultar Status do Dispositivo | `device()` | `GET /devices/:id` |
| Reconectar Dispositivo | `reconnectDevice()` | `POST /devices/:id/reconnect` |
| Desconectar Dispositivo (Logout) | `logout()` | `POST /devices/:id/logout` |
| Purgar / Excluir Dispositivo | `deleteDevice()` | `DELETE /devices/:id` |

### Envio de Mensagens e Interações

| Recurso | Método | Endpoint |
|---|---|---|
| Mensagem de Texto (Menções, Efêmera, Agendamento) | `sendText()` | `POST /send/message` |
| Imagem (Menção na Legenda, View-Once, Agendamento) | `sendMedia()` | `POST /send/image` |
| Vídeo (Menção na Legenda, View-Once, Agendamento) | `sendMedia()` | `POST /send/video` |
| Áudio / Recado de Voz PTT (Agendamento) | `sendMedia()` (voice: true) | `POST /send/audio` |
| Documento / Arquivo (Menções, Agendamento) | `sendMedia()` | `POST /send/file` |
| Figurinha WebP | `sendSticker()` | `POST /send/sticker` |
| Localização | `sendLocation()` | `POST /send/location` |
| Cartão de Contato | `sendContacts()` | `POST /send/contact` |
| Prévia de Link | `sendLink()` | `POST /send/link` |
| Enquete Interativa | `sendPoll()` | `POST /send/poll` |
| Reação com Emoji | `sendReaction()` | `POST /message/:id/reaction` |
| Encaminhar Mensagem (Agendamento) | `forwardMessage()` | `POST /message/:id/forward` |
| Editar Mensagem | `editMessage()` | `POST /message/:id/update` |
| Revogar (Apagar para todos) | `revokeMessage()` | `POST /message/:id/revoke` |
| Deletar (Local) | `deleteMessage()` | `POST /message/:id/delete` |
| Favoritar / Desfavoritar | `starMessage()` | `POST /message/:id/star`, `POST /message/:id/unstar` |
| Marcar Áudio Ouvido | `markPlayed()` | `POST /message/:id/played` |
| Confirmar Leitura / Digitando | `markRead()` | `POST /message/:id/read` |
| Histórico de Chat Sob Demanda | `requestChatHistory()` | `POST /chat/:jid/history` |

### Envios Agendados e Recorrentes (GOWA v9.5+)

| Recurso | Método | Endpoint |
|---|---|---|
| Listar Agendamentos | `listSchedules()` | `GET /send/schedules` |
| Consultar Agendamento | `getSchedule()` | `GET /send/schedules/:id` |
| Pausar Agendamento | `pauseSchedule()` | `POST /send/schedules/:id/pause` |
| Retomar Agendamento | `resumeSchedule()` | `POST /send/schedules/:id/resume` |
| Cancelar Agendamento | `cancelSchedule()` | `POST /send/schedules/:id/cancel` |

### Contatos, Presença e Download de Mídias

| Recurso | Método | Endpoint |
|---|---|---|
| Verificar se Usuário está no WhatsApp | `checkUser()` | `GET /user/check` |
| Foto de Perfil do Contato | `avatar()` | `GET /user/avatar` |
| Preparar Download de Mídia | `describeMedia()` | `GET /message/:id/download` |
| Baixar Mídia Descriptografada | `downloadMedia()` | GET URL da mídia |

## Tratamento de Erros

O SDK fornece exceções estruturadas para diferenciar claramente falhas de conectividade/transporte, recusas permanentes de download de mídia e respostas de erro/validação do servidor:

```php
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\Exceptions\MediaUnavailableException;

try {
    $client->sendText('meu-device-id', '5511999998888', 'Olá');
} catch (GowaUnreachableException $e) {
    // Falha de rede, timeout de conexão, erro de DNS ou servidor inalcançável
    // Reconcilie o status de entrega antes de retentar operações não-idempotentes (como envio de mensagens)
} catch (MediaUnavailableException $e) {
    // Recusa permanente de download de mídia (ex: mensagem sem mídia ou formato não suportado)
} catch (GowaRequestException $e) {
    // O servidor respondeu com recusa (status 4xx/5xx ou code != SUCCESS)
    $status = $e->statusCode;    // ex: 400
    $code = $e->gowaCode;        // ex: "VALIDATION_ERROR"
    $message = $e->gowaMessage;  // ex: "your audio type is not allowed..."
}
```

## 🔒 Segurança e Considerações de Multi-Tenancy

### Isolamento de Tenants e Escopo por Aparelho
- **O GOWA não isola tenants no nível da API**: Uma única credencial Basic Auth tem acesso a todos os aparelhos conectados no servidor.
- **Escopo por `deviceId`**: Toda operação específica de aparelho deve declarar seu `$deviceId`. Um identificador ausente, vazio ou inválido faria a requisição ser executada pelo aparelho que o servidor escolhesse arbitrariamente.
- **Validação**: Todos os métodos que recebem `$deviceId` rejeitam strings vazias ou compostas apenas por espaços com `InvalidArgumentException` antes de enviar qualquer requisição HTTP.
- **Responsabilidade de quem chama**: O `$deviceId` **deve sempre vir do armazenamento seguro da aplicação** (ex: model do banco de dados) e **nunca diretamente de entrada não-confiável do usuário ou parâmetros da requisição**.
- **Endpoints sem escopo**: Endpoints de leitura ampla (`/chats`, `/user/my/contacts`, `/user/my/groups`) não possuem escopo por aparelho no servidor GOWA e misturam dados de todos os números conectados. Evite utilizá-los quando for necessário isolamento estrito entre números/tenants.

### Validação Anti-SSRF e Proteção contra Redirecionamentos
Qualquer URL de mídia ou imagem de QR code obtida via `downloadMedia()` ou `fetchQrImage()` é rigidamente validada com `GowaHost::assertBelongsToServer()`, garantindo que requisições só atinjam o servidor GOWA configurado. Além disso, redirecionamentos HTTP automáticos são explicitamente desabilitados (`allow_redirects: false`) para impedir que redirecionamentos não validados escapem do host configurado, prevenindo ataques de SSRF e vazamento de credenciais.

## Executando os Testes (Pest PHP)

```bash
vendor/bin/pest
```

## ⚠️ Isenção de Responsabilidade e Termos de Uso (Disclaimer)

Este software é uma biblioteca open-source desenvolvida para fins **educacionais, de pesquisa e laboratório de testes**.

- **Termos de Serviço de Terceiros**: Os usuários desta biblioteca são inteiramente responsáveis pelo cumprimento dos Termos de Serviço do WhatsApp, das Políticas da Plataforma Meta e dos termos de uso de quaisquer serviços de terceiros utilizados.
- **Envio Automatizado e Privacidade**: O envio automatizado ou não autorizado de mensagens pode violar os termos das plataformas. Cabe aos usuários garantir conformidade estrita com as leis de privacidade aplicáveis (ex: LGPD, GDPR), consentimento prévio dos destinatários e diretrizes das ferramentas.
- **Ausência de Garantias e Responsabilidade**: Este software é fornecido "como está" (*as is*), sem garantias de qualquer tipo, expressas ou implícitas. Os autores e contribuidores não se responsabilizam por eventuais bloqueios de números, banimentos de contas, perda de dados ou mau uso desta biblioteca.

## Contribuição

Consulte o guia de [CONTRIBUTING.md](https://github.com/Gowa-PHP/sdk/blob/main/CONTRIBUTING.md) e o [Código de Conduta (CODE_OF_CONDUCT.md)](https://github.com/Gowa-PHP/sdk/blob/main/CODE_OF_CONDUCT.md) para detalhes sobre como colaborar.

## Licença

Este pacote é um software open-source licenciado sob a [Licença MIT](LICENSE).
