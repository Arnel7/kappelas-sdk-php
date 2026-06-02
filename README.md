# Kappela SDK — PHP

[![Packagist](https://img.shields.io/packagist/v/kappelas/kappelas-sdk-php)](https://packagist.org/packages/kappelas/kappelas-sdk-php)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Official PHP SDK for the [Kappela](https://kappelas.com) messaging platform.  
Build bots and personal automations with a clean, typed API.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Install](#install)
- [Quick start](#quick-start)
- [PHP type hints & autocompletion](#php-type-hints--autocompletion)
- [Events — WebSocket vs Webhook](#events--websocket-vs-webhook)
- [bot->reply()](#bot-reply)
- [Message fields](#message-fields)
- [CallbackQuery fields](#callbackquery-fields)
- [API reference](#api-reference)
  - [messages](#messages)
  - [delete_previous](#delete_previous)
  - [chats](#chats)
  - [Groups & channels](#groups--channels)
    - [Receiving group messages](#receiving-group-messages)
    - [Replying in a group](#replying-in-a-group)
    - [Getting member IDs](#getting-member-ids)
    - [Detecting conversation type](#detecting-conversation-type)
    - [Full group bot example](#full-group-bot-example)
  - [Chat member management](#chat-member-management)
  - [Invite links](#invite-links-admin-only)
  - [getMyGroups](#getmygroups)
  - [communities](#communities)
  - [webhooks](#webhooks)
  - [profile](#profile)
- [Keyboards](#keyboards)
  - [Comparison](#comparison)
  - [Inline keyboard](#inline-keyboard)
  - [Reply keyboard](#reply-keyboard)
  - [Scroll keyboard](#scroll-keyboard)
  - [Full example](#full-example)
- [Text formatting](#text-formatting)
  - [Inline styles](#inline-styles)
  - [Block code](#block-code)
  - [Blockquote / citation](#blockquote--citation)
  - [Mentions and commands](#mentions-and-commands)
  - [Auto-detected links](#auto-detected-links)
- [Error handling](#error-handling)
- [File input](#file-input)

---

## Prerequisites

- PHP 8.1+
- Composer

---

## Install

```bash
composer require kappelas/kappelas-sdk-php
```

---

## Quick start

```php
<?php
require 'vendor/autoload.php';

use Kappelas\KappelaBot;
use Kappelas\Types\Message;

$bot = new KappelaBot('YOUR_BOT_TOKEN');

$bot->onMessage(function (Message $msg) use ($bot) {
    if ($msg->text === '/start') {
        $bot->messages->send([
            'chat_id' => $msg->chatId,
            'text'    => 'Hello! 👋',
        ]);
    }
});

$bot->start(); // blocks — WebSocket loop
```

For a **webhook** setup, call `$bot->handleWebhook($payload)` instead of `$bot->start()`:

```php
$payload = json_decode(file_get_contents('php://input'), true);
$bot->handleWebhook($payload);
```

---

## PHP type hints & autocompletion

Every method has full PHPDoc with typed `@param` shapes and `@return` types. IDEs (PhpStorm, VS Code + Intelephense) provide autocompletion on all result properties:

```php
$result = $bot->messages->send(['chat_id' => 123, 'text' => 'Hi']);
$result->messageId; // int
$result->createdAt; // int|null
```

---

## Events — WebSocket vs Webhook

| Feature | WebSocket (`start()`) | Webhook (`handleWebhook()`) |
|---|---|---|
| Setup | No HTTPS required | Requires public HTTPS URL |
| Connection | Persistent TCP | Stateless HTTP |
| Use case | Development, VPS bots | Serverless, shared hosting |

**WebSocket:**
```php
$bot->onMessage(fn(Message $msg) => ...);
$bot->onCallbackQuery(fn(CallbackQuery $cb) => ...);
$bot->onConnected(fn() => ...);
$bot->onDisconnected(fn(int $code, string $reason) => ...);
$bot->onError(fn(Throwable $e) => ...);
$bot->start();
```

**Webhook:**
```php
// In your HTTP handler:
$payload = json_decode(file_get_contents('php://input'), true);
$bot->handleWebhook($payload);
// Same onMessage / onCallbackQuery handlers are called synchronously.
```

---

## bot->reply()

Reply to a received message in one call — `reply_to_id` is injected automatically:

```php
$bot->onMessage(function (Message $msg) use ($bot) {
    $bot->reply($msg, '↩️ Got your message!');
});
```

With a keyboard:

```php
$bot->reply($msg, 'Choose an option:', [
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => '✅ Yes', 'callback_data' => 'yes'],
            ['text' => '❌ No',  'callback_data' => 'no'],
        ]],
    ],
]);
```

---

## Message fields

```php
$msg->id               // int   — message ID
$msg->chatId           // int   — chat ID
$msg->senderId         // ?string
$msg->type             // ?string — 'text'|'image'|'video'|'audio'|'document'|...
$msg->text             // ?string
$msg->mediaId          // ?string
$msg->extraData        // mixed  — inline keyboard definition when received
$msg->status           // string — 'sent'|'delivered'|'read'
$msg->editedAt         // ?int   — Unix timestamp
$msg->deletedAt        // ?int
$msg->createdAt        // int    — Unix timestamp
$msg->replyToId        // ?int
$msg->replyToSnapshot  // ?ReplySnapshot
$msg->mentions         // array
$msg->forwardedFrom    // mixed
$msg->expiresAt        // ?int
$msg->senderName       // ?string
$msg->senderUsername   // ?string
$msg->senderAvatarUrl  // ?string
$msg->clientMsgId      // ?string
$msg->width            // ?int   — media width in pixels
$msg->height           // ?int   — media height in pixels
$msg->chatType         // ?string — 'private'|'group'|'channel'
```

---

## CallbackQuery fields

```php
$cb->chatId          // int
$cb->senderId        // string
$cb->callbackData    // string
$cb->senderName      // ?string
$cb->senderUsername  // ?string
$cb->sentAt          // ?int
```

---

## API reference

### messages

```php
// Send text
$bot->messages->send([
    'chat_id'         => 123,
    'text'            => 'Hello!',
    'reply_markup'    => [...],    // optional keyboard
    'reply_to_id'     => 456,     // optional — reply to message ID
    'delete_previous' => true,    // optional
]);
// → SendResult { messageId: int, createdAt: ?int }

// Send media
$bot->messages->sendPhoto([
    'chat_id'         => 123,
    'file'            => ['data' => $bytes, 'filename' => 'photo.jpg', 'content_type' => 'image/jpeg'],
    'caption'         => 'Caption text',
    'reply_to_id'     => 456,
    'delete_previous' => true,
    'reply_markup'    => [...],
]);
// sendVideo(), sendDocument(), sendAudio() — same signature
// → SendMediaResult { messageId: int, createdAt: ?int, mediaId: string }

// Carousel
$bot->messages->sendCarousel([
    'chat_id'             => 123,
    'text'                => 'Our products:',
    'carousel'            => [
        ['id' => 'p1', 'title' => 'Product A', 'subtitle' => '$9.99', 'button_text' => 'View'],
    ],
    'quick_reply_buttons' => ['See more', ['text' => '❌ Cancel', 'callback_data' => 'cancel']],
    'reply_to_id'         => 456,
]);
// → SendCarouselResult { messageId: int, createdAt: ?int, type: 'carousel' }

// Typing indicator
$bot->messages->sendTyping(['chat_id' => 123]);
$bot->messages->sendTyping(['chat_id' => 123, 'is_typing' => false]);
// → TypingResult { typing: bool }

// Edit
$bot->messages->edit([
    'chat_id'        => 123,
    'message_id'     => 456,
    'new_text'       => 'Updated text',
    'new_extra_data' => [...],  // replacement inline keyboard
]);
// → EditMessageResult { edited: bool, messageId: int }

// Delete
$bot->messages->delete(['chat_id' => 123, 'message_id' => 456]);
// → DeleteResult { deleted: bool }
```

### delete_previous

When `delete_previous: true`, the bot's last message in the chat is deleted before sending the new one. Useful for menus that should replace themselves:

```php
// First send
$bot->messages->send(['chat_id' => $chatId, 'text' => 'Step 1']);

// Next send — the "Step 1" message is deleted first
$bot->messages->send([
    'chat_id'         => $chatId,
    'text'            => 'Step 2',
    'delete_previous' => true,
]);
```

### chats

```php
// Paginated list
$result = $bot->chats->list(['limit' => 20, 'offset' => 0]);
// → ChatsResult { chats: Chat[], hasMore: bool }

// Auto-pagination — return false from $fn to stop early
$bot->chats->iterate(50, function (Chat $chat): bool {
    echo $chat->title . "\n";
    return true; // continue
});
```

**Chat fields:**

```php
$chat->chatId              // int
$chat->id                  // int
$chat->type                // 'private'|'group'|'channel'
$chat->title               // ?string
$chat->participants        // Participant[]
$chat->lastMessageAt       // mixed
$chat->createdAt           // string
$chat->createdBy           // string
$chat->isPinned            // bool
$chat->isPremium           // bool
$chat->isPublic            // bool
$chat->onlyAdminsCanWrite  // bool
$chat->labels              // array
$chat->description         // ?string
$chat->avatarUrl           // ?string
```

**Participant fields:**

```php
$p->id         // string
$p->nom        // string
$p->isBot      // bool
$p->isPremium  // bool
$p->avatarUrl  // ?string
$p->role       // ?string — 'member'|'admin' (null in private chats)
```

### Groups & channels

#### Receiving group messages

Group messages arrive via the same `onMessage` handler:

```php
$bot->onMessage(function (Message $msg) use ($bot) {
    if ($msg->chatType === 'group') {
        // handle group message
    }
});
```

#### Replying in a group

```php
$bot->reply($msg, 'Reply to group message');
```

#### Getting member IDs

```php
$admins = $bot->chats->getAdministrators(['chat_id' => $groupId]);
foreach ($admins->admins as $admin) {
    echo $admin->userId . ' — ' . $admin->role . "\n";
}
```

#### Detecting conversation type

```php
$bot->onMessage(function (Message $msg) use ($bot) {
    $context = match($msg->chatType) {
        'private' => 'private chat',
        'group'   => 'group',
        'channel' => 'channel',
        default   => 'unknown',
    };
    $bot->messages->send(['chat_id' => $msg->chatId, 'text' => "You're in a $context"]);
});
```

#### Full group bot example

```php
<?php
require 'vendor/autoload.php';

use Kappelas\KappelaBot;
use Kappelas\Types\Message;
use Kappelas\Types\CallbackQuery;

$bot = new KappelaBot('YOUR_BOT_TOKEN');

// Get groups the bot belongs to
$groups = $bot->chats->getMyGroups();
foreach ($groups->groups as $g) {
    echo "Group: {$g->title} ({$g->type}) — bot role: {$g->botRole}\n";
}

$bot->onMessage(function (Message $msg) use ($bot) {
    if ($msg->chatType !== 'group') return;

    if ($msg->text === '/members') {
        $admins = $bot->chats->getAdministrators(['chat_id' => $msg->chatId]);
        $list = implode(', ', array_map(fn($a) => $a->userId, $admins->admins));
        $bot->reply($msg, "Admins: $list");
    }
});

$bot->onCallbackQuery(function (CallbackQuery $cb) use ($bot) {
    $bot->messages->send([
        'chat_id' => $cb->chatId,
        'text'    => 'You clicked: ' . $cb->callbackData,
    ]);
});

$bot->start();
```

### Chat member management

> Admin-only operations.

```php
// Add a member
$bot->chats->addMember(['chat_id' => 123, 'user_id' => 'abc456']);
// → AddChatMemberResult { description: string }

// Ban a member
$bot->chats->banMember(['chat_id' => 123, 'user_id' => 'abc456']);
// → BanChatMemberResult { description: string }

// Leave a chat
$bot->chats->leaveChat(['chat_id' => 123]);
// → LeaveChatResult { description: string }

// Promote / demote
$bot->chats->promoteMember(['chat_id' => 123, 'user_id' => 'abc456', 'role' => 'admin']);
$bot->chats->promoteMember(['chat_id' => 123, 'user_id' => 'abc456', 'role' => 'member']);
// → PromoteChatMemberResult { userId: string, role: string }

// Get all admins
$result = $bot->chats->getAdministrators(['chat_id' => 123]);
// → GetChatAdministratorsResult { admins: ChatMemberInfo[] }

// Get one member
$info = $bot->chats->getMember(['chat_id' => 123, 'user_id' => 'abc456']);
// → ChatMemberInfo { userId: string, role: string }
```

### Invite links (admin only)

```php
// Create a permanent link (no limit)
$link = $bot->chats->createInviteLink(['chat_id' => 123]);

// Create with options
$link = $bot->chats->createInviteLink([
    'chat_id'    => 123,
    'max_uses'   => 10,
    'expires_in' => 86400, // seconds
]);

// Single-use shorthand (max_uses=1)
$link = $bot->chats->createSingleUseInviteLink(['chat_id' => 123]);

// → ChatInviteLink { code, url, maxUses, useCount, expiresAt, createdAt }

// List active links
$result = $bot->chats->getInviteLinks(['chat_id' => 123]);
// → GetChatInviteLinksResult { inviteLinks: ChatInviteLink[] }

// Revoke a link
$bot->chats->revokeInviteLink(['chat_id' => 123, 'code' => $link->code]);
// → RevokeChatInviteLinkResult { revoked: bool, code: string }
```

### getMyGroups

```php
$result = $bot->chats->getMyGroups();
// → GetMyGroupsResult { groups: BotGroupEntry[] }

foreach ($result->groups as $group) {
    echo "{$group->title} — {$group->type} — {$group->participantCount} members — bot: {$group->botRole}\n";
}
```

**BotGroupEntry fields:** `chatId`, `type`, `title`, `participantCount`, `botRole`

### communities

Manage communities a bot belongs to: CRUD, members & roles, invite links, join
requests, and group requests. A bot can only administer a community where it is an
**admin**. Note: the community role (`member`/`admin`) is distinct from a group role.

```php
use Kappelas\KappelaBot;

$bot = new KappelaBot('YOUR_BOT_TOKEN');

// --- CRUD ---
$c = $bot->communities->create(['name' => 'Devs', 'description' => 'Notre commu', 'requires_approval' => true]);
$all   = $bot->communities->list();        // Community[] (each with ->role)
$admin = $bot->communities->listAdmin();   // only those where the bot is admin
$one   = $bot->communities->get(['community_id' => $c->id]);   // CommunityDetail (with members)
$bot->communities->update(['community_id' => $c->id, 'description' => 'Nouvelle desc']); // only sent fields change
$bot->communities->delete(['community_id' => $c->id]);
$bot->communities->join(['community_id' => 42]); // ->pending === true if approval required

// --- Members & roles ---
// To make someone (person OR bot) admin: add them as member, then promote.
$bot->communities->addMember(['community_id' => $c->id, 'user_id' => 'uuid', 'role' => 'member']);
$bot->communities->promoteMember(['community_id' => $c->id, 'user_id' => 'uuid', 'role' => 'admin']);
$bot->communities->banMember(['community_id' => $c->id, 'user_id' => 'uuid']); // remove a member
$bot->communities->leave(['community_id' => $c->id]);

// --- Invite links ---
$inv  = $bot->communities->createInviteLink(['community_id' => $c->id, 'max_uses' => 10, 'expires_in' => '24h']);
$list = $bot->communities->getInviteLinks(['community_id' => $c->id]); // CommunityInvite[]
$bot->communities->revokeInviteLink(['community_id' => $c->id, 'code' => $inv->code]);
$preview = $bot->communities->previewInvite(['code' => $inv->code]); // CommunityInvitePreview (no auth needed)
$communityId = $bot->communities->acceptInvite(['code' => $inv->code]); // bot joins via code

// --- Join requests (user -> community) ---
$reqs = $bot->communities->getJoinRequests(['community_id' => $c->id]); // CommunityJoinRequest[]
$bot->communities->approveJoinRequest(['community_id' => $c->id, 'request_id' => $reqs[0]->id]);
$bot->communities->rejectJoinRequest(['community_id' => $c->id, 'request_id' => $reqs[0]->id]);

// --- Group requests + linking groups ---
$greqs = $bot->communities->getGroupRequests(['community_id' => $c->id]); // CommunityGroupRequest[]
$bot->communities->approveGroupRequest(['community_id' => $c->id, 'request_id' => $greqs[0]->id]);
$bot->communities->rejectGroupRequest(['community_id' => $c->id, 'request_id' => $greqs[0]->id]);
$bot->communities->addGroup(['community_id' => $c->id, 'conversation_id' => 123]);
$bot->communities->removeGroup(['community_id' => $c->id, 'conversation_id' => 123]);
```

**Community fields:** `id`, `name`, `description`, `avatarUrl`, `createdBy`,
`announcementChannelId`, `requiresApproval`, `createdAt`, `role` (only in `list()`).
`CommunityDetail` adds `members` (each with `userId`, `name`, `avatarUrl`, `role`).

### webhooks

```php
// Register
$bot->webhooks->set(['url' => 'https://yourserver.com/webhook']);
// → WebhookSetResult { url: string, active: bool }

// Get info
$info = $bot->webhooks->getInfo();
// → WebhookInfo { active: bool, url: ?string, createdAt: mixed }

// Remove
$bot->webhooks->delete();
// → WebhookDeleteResult { active: bool }
```

### profile

```php
// Bot profile
$profile = $bot->profile->get();
// → BotProfile { userId, username, isBot, about, description, avatarUrl }

// User profile (KappelaUser only)
$profile = $user->profile->get();
// → UserProfile { id, username, nom, isBot, isPremium, avatarUrl, about }
```

---

## Keyboards

### Comparison

| Type | Usage | Rendered |
|---|---|---|
| **Inline keyboard** | Buttons attached to a message | Below the message |
| **Reply keyboard** | Grid of buttons (replaces input bar) | Bottom of screen |
| **Scroll keyboard** | Horizontal scrollable buttons | Above input bar |

### Inline keyboard

Buttons are passed as a 2D array — rows × columns.

```php
// Short form: text = callback_data
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Choose:',
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => '✅ Yes', 'callback_data' => 'yes'],
            ['text' => '❌ No',  'callback_data' => 'no'],
        ]],
    ],
]);

// Long form: separate text and callback_data
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Choose action:',
    'reply_markup' => [
        'inline_keyboard' => [
            [['text' => '📦 Orders',    'callback_data' => 'action_orders']],
            [['text' => '⚙️ Settings',  'callback_data' => 'action_settings']],
        ],
    ],
]);
```

### Reply keyboard

Grid of buttons shown at the bottom. Each item can be a plain string or an array with `text` + `callback_data`.

```php
// Short form — text is also the callback_data
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Pick a size:',
    'reply_markup' => ['keyboard' => [['S', 'M'], ['L', 'XL']]],
]);

// Long form — separate text and callback_data
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Confirm?',
    'reply_markup' => [
        'keyboard' => [[
            ['text' => '✅ Yes', 'callback_data' => 'confirm'],
            ['text' => '❌ No',  'callback_data' => 'cancel'],
        ]],
    ],
]);

// Mixed — strings and objects in the same row
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Mixed keyboard:',
    'reply_markup' => [
        'keyboard' => [
            [['text' => '✅ Yes', 'callback_data' => 'yes'], 'No'],
        ],
    ],
]);
```

### Scroll keyboard

Flat horizontal list. Items can be plain strings or arrays with `text` + `callback_data`.

```php
// Short form
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Filter by:',
    'reply_markup' => ['scroll_keyboard' => ['All', 'Active', 'Closed']],
]);

// Long form
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Menu:',
    'reply_markup' => [
        'scroll_keyboard' => [
            ['text' => '📦 Orders',   'callback_data' => 'menu_orders'],
            ['text' => '❓ Help',     'callback_data' => 'menu_help'],
            ['text' => '⚙️ Settings', 'callback_data' => 'menu_settings'],
        ],
    ],
]);

// Mixed
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => 'Mixed scroll:',
    'reply_markup' => [
        'scroll_keyboard' => [
            ['text' => '📦 Orders', 'callback_data' => 'menu_orders'],
            '❓ Help',
        ],
    ],
]);
```

### Full example

```php
<?php
require 'vendor/autoload.php';

use Kappelas\KappelaBot;
use Kappelas\Types\CallbackQuery;
use Kappelas\Types\Message;

$bot = new KappelaBot('YOUR_BOT_TOKEN');
$chatId = 123;

// Show a menu with inline keyboard
$bot->messages->send([
    'chat_id'      => $chatId,
    'text'         => '🗂 What do you need?',
    'reply_markup' => [
        'inline_keyboard' => [
            [['text' => '📦 Orders',    'callback_data' => 'menu_orders']],
            [['text' => '❓ Help',      'callback_data' => 'menu_help']],
            [['text' => '⚙️ Settings',  'callback_data' => 'menu_settings']],
        ],
    ],
]);

// Handle button clicks
$bot->onCallbackQuery(function (CallbackQuery $cb) use ($bot) {
    $response = match($cb->callbackData) {
        'menu_orders'   => '📦 Here are your orders...',
        'menu_help'     => '❓ How can I help you?',
        'menu_settings' => '⚙️ Opening settings...',
        default         => 'Unknown option',
    };
    $bot->messages->send(['chat_id' => $cb->chatId, 'text' => $response]);
});

$bot->start();
```

---

## Text formatting

### Inline styles

```
*bold*           → **bold**
__italic__       → _italic_
~strikethrough~  → ~~strikethrough~~
`inline code`    → `code`
```

```php
$bot->messages->send([
    'chat_id' => $chatId,
    'text'    => '*bold*  __italic__  ~strikethrough~  `code`',
]);
```

### Block code

Wrap with triple backticks. Optionally specify a language:

```php
$bot->messages->send([
    'chat_id' => $chatId,
    'text'    => "Your API key:\n```\nsk_live_abc123\n```",
]);
```

### Blockquote / citation

Lines starting with `>` are rendered as a blockquote:

```php
$bot->messages->send([
    'chat_id' => $chatId,
    'text'    => "> Original question\n\nDetailed answer here.",
]);
```

### Mentions and commands

```php
$bot->messages->send([
    'chat_id' => $chatId,
    'text'    => 'Thanks @alice! Type /help for available commands.',
]);
```

### Auto-detected links

Plain URLs and bare domains are automatically made clickable:

```php
$bot->messages->send([
    'chat_id' => $chatId,
    'text'    => 'Visit kappelas.com or https://kappelas.com/docs',
]);
```

---

## Error handling

All API errors throw `KappelaError`. Catch it for structured error info:

```php
use Kappelas\KappelaError;

try {
    $bot->messages->send(['chat_id' => $chatId, 'text' => 'Hi']);
} catch (KappelaError $e) {
    echo $e->errorCode;     // 'NOT_FOUND', 'FORBIDDEN', ...
    echo $e->errorMessage;  // human-readable message from the API
    echo $e->status;        // HTTP status code (int)
    echo $e->requestId;     // trace ID (include in bug reports)
}
```

| `errorCode` | HTTP | Meaning |
|---|---|---|
| `UNAUTHORIZED` | 401 | Invalid or expired token |
| `FORBIDDEN` | 403 | Missing permission for this action |
| `NOT_FOUND` | 404 | Resource doesn't exist |
| `MISSING_FIELD` | 400 | Required parameter missing |
| `INVALID_FIELD` | 400 | Parameter has wrong type/format |
| `CONFLICT` | 409 | Resource already exists |
| `INTERNAL_ERROR` | 500 | Unexpected server error |
| `SERVICE_UNAVAILABLE` | 503 | Platform temporarily unavailable |

---

## File input

Pass file content as a raw string plus metadata:

```php
// From bytes in memory
$bot->messages->sendPhoto([
    'chat_id' => 123,
    'file'    => [
        'data'         => file_get_contents('/path/to/photo.jpg'),
        'filename'     => 'photo.jpg',
        'content_type' => 'image/jpeg',
    ],
    'caption' => 'My photo',
]);

// From a file path (the SDK reads it automatically)
$bot->messages->sendDocument([
    'chat_id' => 123,
    'file'    => '/path/to/document.pdf',
    'caption' => 'My document',
]);
```

Supported methods: `sendPhoto`, `sendVideo`, `sendDocument`, `sendAudio`.
