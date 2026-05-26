# kappelas-sdk-php

[![Packagist](https://img.shields.io/packagist/v/kappelas/kappelas-sdk-php.svg)](https://packagist.org/packages/kappelas/kappelas-sdk-php)
[![PHP version](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-source-181717?logo=github)](https://github.com/Arnel7/kappelas-sdk-php)

**Official PHP SDK for the [Kappela](https://kappelas.com) messaging platform.**  
Build bots and personal automations — send messages, handle events, manage chats.

---

## Table of contents

- [Prerequisites](#prerequisites)
- [Install](#install)
- [Quick start](#quick-start)
- [Events — WebSocket vs Webhook](#events--websocket-vs-webhook)
- [API reference](#api-reference)
  - [messages](#messages)
  - [chats](#chats)
  - [webhooks](#webhooks)
  - [profile](#profile)
- [Keyboards](#keyboards)
- [Error handling](#error-handling)
- [File input](#file-input)

---

## Prerequisites

You need a bot token from **BotMother**, the official Kappela bot manager.

1. Open Kappela and start a conversation with [**BotMother**](https://kappelas.com/bot/botmother_bot)
2. Follow the instructions to create a bot
3. BotMother gives you a token — keep it secret, it gives full control over your bot

For personal automation (sending messages as yourself), generate an API key from your Kappela account settings (`sk_...`).

---

## Install

```bash
composer require kappelas/kappelas-sdk-php
```

Requires **PHP 8.1+**.

---

## Quick start

### Bot

```php
<?php

require_once 'vendor/autoload.php';

use Kappelas\KappelaBot;
use Kappelas\Types\Message;
use Kappelas\Types\CallbackQuery;

$bot = new KappelaBot('YOUR_BOT_TOKEN');

$bot->onMessage(function (Message $msg) use ($bot) {
    $bot->messages->send([
        'chat_id' => $msg->chatId,
        'text'    => 'Echo: ' . $msg->text,
    ]);
});

$bot->onCallbackQuery(function (CallbackQuery $cb) use ($bot) {
    $bot->messages->send([
        'chat_id' => $cb->chatId,
        'text'    => 'You clicked: ' . $cb->callbackData,
    ]);
});

$bot->start(); // blocks — runs the WebSocket loop
```

### Personal automation

```php
use Kappelas\KappelaUser;
use Kappelas\Types\Message;

$me = new KappelaUser('sk_...');

$me->onMessage(function (Message $msg) {
    if ($msg->text !== null) {
        echo "[{$msg->chatId}] {$msg->text}\n";
    }
});

$me->start();
```

---

## Events — WebSocket vs Webhook

| Mode | Method | Best for |
|------|--------|----------|
| **WebSocket** | `$bot->start()` | Development, local scripts |
| **Webhook** | `$bot->webhooks->set()` + `$bot->handleWebhook()` | Production servers |

The same `onMessage` and `onCallbackQuery` handlers work in both modes — no code change needed when switching.

### WebSocket (development)

```php
$bot = new KappelaBot('YOUR_BOT_TOKEN');

$bot->onMessage(fn(Message $msg) => /* ... */);
$bot->onCallbackQuery(fn(CallbackQuery $cb) => /* ... */);

$bot->start(); // blocking
```

### Webhook (production)

```php
$bot = new KappelaBot('YOUR_BOT_TOKEN');

// Register once
$bot->webhooks->set(['url' => 'https://your-server.com/kappela-webhook']);

$bot->onMessage(fn(Message $msg) => /* ... */);
$bot->onCallbackQuery(fn(CallbackQuery $cb) => /* ... */);

// In your HTTP handler (e.g. Laravel route, plain PHP):
$payload = json_decode(file_get_contents('php://input'), true);
$bot->handleWebhook($payload);
http_response_code(200);
```

> Do **not** call `$bot->start()` in webhook mode.

### Event reference

| Method | Signature | Description |
|--------|-----------|-------------|
| `onMessage` | `callable(Message)` | Incoming message of any type |
| `onCallbackQuery` | `callable(CallbackQuery)` | Inline button clicked by a user |
| `onConnected` | `callable()` | WebSocket connected or reconnected |
| `onDisconnected` | `callable(int $code, string $reason)` | WebSocket disconnected |
| `onError` | `callable(Throwable)` | Connection or transport error |

### `CallbackQuery` fields

```php
$bot->onCallbackQuery(function (CallbackQuery $cb) {
    $cb->chatId          // int     — chat where the button was clicked
    $cb->senderId        // string  — UUID of the user who clicked
    $cb->senderNom       // ?string — display name (e.g. "Arnel LAWSON")
    $cb->senderUsername  // ?string — username (e.g. "arnell")
    $cb->callbackData    // string  — value set on the button
    $cb->sentAt          // ?int    — Unix timestamp (seconds)
});
```

---

## API reference

### Constructor options

#### `new KappelaBot(string $token, ...)`

| Parameter | Default | Description |
|-----------|---------|-------------|
| `$token` | — | Bot token from BotMother |
| `$baseUrl` | `https://api.kappelas.com` | Override API base URL |
| `$maxRetries` | `2` | HTTP retry count on 429 / 5xx |
| `$timeout` | `30.0` | Per-request timeout (seconds) |
| `$wsMaxRetries` | `12` | Max WebSocket reconnect attempts |

#### `new KappelaUser(string $apiKey, ...)`

Same parameters — pass your `sk_...` API key as `$apiKey`.

---

### `messages`

#### `$bot->messages->send(array $params)` → `SendResult`

```php
$result = $bot->messages->send([
    'chat_id'      => 42,
    'text'         => 'Hello!',
    'reply_to_id'  => 123,   // optional — reply to a message
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => 'Yes', 'callback_data' => 'yes'],
            ['text' => 'No',  'callback_data' => 'no'],
        ]],
    ],
]);
// → SendResult{messageId, createdAt}
```

#### `$bot->messages->sendPhoto(array $params)` → `SendMediaResult`

```php
$data   = file_get_contents('banner.png');
$result = $bot->messages->sendPhoto([
    'chat_id' => 42,
    'file'    => ['data' => $data, 'filename' => 'banner.png', 'content_type' => 'image/png'],
    'caption' => 'Check this out!',
]);
// → SendMediaResult{messageId, createdAt, mediaId}
```

#### `$bot->messages->sendVideo` / `sendDocument` / `sendAudio` → `SendMediaResult`

Same shape — pass the appropriate `file` array.

#### `$bot->messages->sendCarousel(array $params)` → `SendCarouselResult`

```php
$bot->messages->sendCarousel([
    'chat_id'             => 42,
    'text'                => 'Pick a product:',
    'carousel'            => [
        ['id' => 'p1', 'title' => 'Widget A', 'button_text' => 'Buy'],
        ['id' => 'p2', 'title' => 'Widget B', 'button_text' => 'Buy'],
    ],
    'quick_reply_buttons' => ['See more', 'Cancel'],
]);
```

#### `$bot->messages->edit(array $params)` → `EditMessageResult`

```php
// Edit text
$bot->messages->edit([
    'chat_id'    => 42,
    'message_id' => 123,
    'new_text'   => 'Updated!',
]);

// Edit inline keyboard only
$bot->messages->edit([
    'chat_id'        => 42,
    'message_id'     => 123,
    'new_extra_data' => [
        'inline_keyboard' => [[['text' => 'Done ✅', 'callback_data' => 'done']]],
    ],
]);
// → EditMessageResult{edited: true, messageId: 123}
```

#### `$bot->messages->sendTyping(array $params)` → `TypingResult`

```php
$bot->messages->sendTyping(['chat_id' => 42]);                              // show
$bot->messages->sendTyping(['chat_id' => 42, 'is_typing' => false]);        // hide
```

#### `$bot->messages->delete(array $params)` → `DeleteResult`

```php
$bot->messages->delete(['chat_id' => 42, 'message_id' => 123]);
// → DeleteResult{deleted: true}
```

---

### `chats`

#### `$bot->chats->list(array $params)` → `ChatsResult`

```php
$result = $bot->chats->list(['limit' => 20, 'offset' => 0]);
// → ChatsResult{chats: Chat[], hasMore: bool}
```

#### `$bot->chats->iterate(int $pageSize, callable $fn)` → `void`

```php
$bot->chats->iterate(50, function (Chat $chat): bool {
    echo $chat->chatId . ' ' . $chat->type . "\n";
    return true; // return false to stop early
});
```

---

### `webhooks`

#### `$bot->webhooks->set(array $params)` → `WebhookSetResult`

```php
$bot->webhooks->set(['url' => 'https://your-server.com/kappela-webhook']);
```

#### `$bot->webhooks->getInfo()` → `WebhookInfo`

```php
$info = $bot->webhooks->getInfo();
// → WebhookInfo{active: true, url: '...', createdAt: 1234567890}
```

#### `$bot->webhooks->delete()` → `WebhookDeleteResult`

```php
$bot->webhooks->delete();
// → WebhookDeleteResult{active: false}
```

---

### `profile`

#### `$bot->profile->get()` → `BotProfile`

```php
$profile = $bot->profile->get();
// → BotProfile{userId, username, isBot: true, about, description, avatarUrl}
```

#### `$me->profile->get()` → `UserProfile`

```php
$profile = $me->profile->get();
// → UserProfile{id, username, nom, isBot: false, isPremium, avatarUrl}
```

---

## Keyboards

Three types of keyboard can be passed as `reply_markup` on any `send*` call:

```php
// Inline buttons — attached to the message
$inline = [
    'inline_keyboard' => [[
        ['text' => 'Yes', 'callback_data' => 'yes'],
        ['text' => 'No',  'callback_data' => 'no'],
    ]],
];

// Reply keyboard — shown below the input bar
$reply = [
    'keyboard' => [
        ['Option A', 'Option B'],
        ['Cancel'],
    ],
];

// Scroll keyboard — horizontal scrollable chips
$scroll = [
    'scroll_keyboard' => ['Small', 'Medium', 'Large'],
];

$bot->messages->send([
    'chat_id'      => 42,
    'text'         => 'Pick one:',
    'reply_markup' => $inline,
]);
```

---

## Error handling

All API errors throw a `KappelaError` with structured fields:

```php
use Kappelas\KappelaError;

try {
    $bot->messages->send(['chat_id' => 999, 'text' => 'Hi']);
} catch (KappelaError $e) {
    $e->code          // e.g. KappelaError::NOT_FOUND
    $e->status        // 404
    $e->errorMessage  // server error message
    $e->requestId     // mention this when contacting support
    echo $e->getMessage(); // full formatted block with hints and solutions
}
```

### Error codes

| Constant | HTTP | Meaning |
|----------|------|---------|
| `KappelaError::UNAUTHORIZED` | 401 | Token or API key invalid / expired |
| `KappelaError::FORBIDDEN` | 403 | Missing permission or role |
| `KappelaError::NOT_FOUND` | 404 | Resource does not exist |
| `KappelaError::MISSING_FIELD` | 400 | Required parameter missing |
| `KappelaError::INVALID_FIELD` | 400 | Parameter has wrong type or format |
| `KappelaError::CONFLICT` | 409 | Resource already exists |
| `KappelaError::METHOD_NOT_ALLOWED` | 405 | Wrong HTTP method |
| `KappelaError::INVALID_PATH` | 404 | API path does not exist |
| `KappelaError::INTERNAL_ERROR` | 500 | Unexpected server error |
| `KappelaError::SERVICE_UNAVAILABLE` | 503 | Service temporarily down |
| `KappelaError::UPSTREAM_ERROR` | 502 | Upstream service error |

---

## File input

Media methods accept a `file` array:

```php
$file = [
    'data'         => string,   // raw binary content
    'filename'     => string,   // e.g. 'photo.jpg'
    'content_type' => string,   // e.g. 'image/jpeg'
];
```

```php
// From disk
$bot->messages->sendPhoto([
    'chat_id' => 42,
    'file'    => [
        'data'         => file_get_contents('photo.jpg'),
        'filename'     => 'photo.jpg',
        'content_type' => 'image/jpeg',
    ],
]);

// From memory
$bot->messages->sendDocument([
    'chat_id' => 42,
    'file'    => [
        'data'         => $pdfBytes,
        'filename'     => 'report.pdf',
        'content_type' => 'application/pdf',
    ],
]);
```

---

## License

MIT © Arnel LAWSON
