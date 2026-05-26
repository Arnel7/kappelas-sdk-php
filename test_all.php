<?php

declare(strict_types=1);

/**
 * Tests live du SDK Kappela PHP.
 *
 * Usage :
 *   php test_all.php <TOKEN> [CHAT_ID]
 *
 * ou via variables d'environnement :
 *   KAPPELA_TOKEN=xxx CHAT_ID=130 php test_all.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Kappelas\KappelaBot;
use Kappelas\KappelaError;
use Kappelas\Types\CallbackQuery;

// ─── Fichiers de test en mémoire ─────────────────────────────────────────────

// PNG 1×1 pixel transparent valide
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

// WAV silence PCM 16bit 44100Hz mono
$wavBytes = hex2bin(
    '52494646' . '26000000' . '57415645' .
    '666d7420' . '10000000' . '01000100' . '44ac0000' . '88580100' . '02001000' .
    '64617461' . '02000000' . '0000'
);

// PDF minimal valide
$pdfBytes = implode('', [
    "%PDF-1.0\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj ",
    "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj ",
    "3 0 obj<</Type/Page/MediaBox[0 0 3 3]>>endobj\n",
    "xref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n",
    "0000000058 00000 n\n0000000115 00000 n\n",
    "trailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF",
]);

// ─── Helpers ─────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function run(string $label, callable $fn): mixed
{
    global $passed, $failed;
    echo "\n→ $label\n";
    try {
        $result = $fn();
        $json   = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "  [✓] OK  $json\n";
        $passed++;
        return $result;
    } catch (KappelaError $e) {
        echo "  [✗] FAIL  KappelaError {$e->code} ({$e->status}): {$e->errorMessage}\n";
        $failed++;
        return null;
    } catch (\Throwable $e) {
        echo "  [✗] FAIL  " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failed++;
        return null;
    }
}

// ─── Arguments ───────────────────────────────────────────────────────────────

$token = getenv('KAPPELA_TOKEN') ?: ($argv[1] ?? '');
if ($token === '') {
    fwrite(STDERR, "Usage: php test_all.php <TOKEN> [CHAT_ID]\n");
    exit(1);
}

$chatId = 130;
if ($env = getenv('CHAT_ID')) {
    $chatId = (int) $env;
} elseif (isset($argv[2])) {
    $chatId = (int) $argv[2];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

$bot = new KappelaBot($token);

// Use a temp file for IPC — the forked child writes to it, the parent reads it.
// A plain PHP variable cannot be shared across pcntl_fork() address spaces.
$connFlag = sys_get_temp_dir() . '/kappela_ws_connected_' . getmypid();

$bot->onConnected(function () use ($connFlag, $chatId) {
    file_put_contents($connFlag, '1');
    echo "[✓] Connecté — chat_id cible : $chatId\n\n";
});

$bot->onError(function (\Throwable $e) {
    echo "[!] Erreur : " . $e->getMessage() . "\n";
});

// Répondre aux clics de boutons pendant les tests
$bot->onCallbackQuery(function (CallbackQuery $cb) use ($bot) {
    $nom = $cb->senderNom ?? $cb->senderId;
    echo "\n[→] Bouton cliqué — chat_id={$cb->chatId} sender=\"$nom\" data=\"{$cb->callbackData}\"\n";
    try {
        $bot->messages->send([
            'chat_id' => $cb->chatId,
            'text'    => 'Tu as cliqué : ' . $cb->callbackData,
        ]);
    } catch (\Throwable $e) {
        echo "[✗] Erreur réponse callback : " . $e->getMessage() . "\n";
    }
});

// ─── Lancer la connexion WebSocket dans un processus enfant ──────────────────
// All handlers and the bot object are configured before fork so the child
// inherits a complete copy. IPC via a temp file lets the parent detect connection.

if (!function_exists('pcntl_fork')) {
    echo "[!] pcntl_fork non disponible — tests HTTP uniquement (pas de WS)\n\n";
    runHttpTests($bot, $chatId, $pngBytes, $wavBytes, $pdfBytes);
    printSummary();
    exit($failed > 0 ? 1 : 0);
}

$pid = pcntl_fork();
if ($pid === -1) {
    fwrite(STDERR, "pcntl_fork() a échoué\n");
    exit(1);
}

if ($pid === 0) {
    // Processus enfant : boucle WebSocket (blocking)
    $bot->start();
    exit(0);
}

// Processus parent : attendre que l'enfant signale la connexion via le fichier IPC
$deadline = time() + 10;
while (!file_exists($connFlag) && time() < $deadline) {
    usleep(100_000);
}
if (!file_exists($connFlag)) {
    echo "[✗] Timeout connexion WebSocket\n";
    posix_kill($pid, SIGTERM);
    @unlink($connFlag);
    exit(1);
}
@unlink($connFlag);

runHttpTests($bot, $chatId, $pngBytes, $wavBytes, $pdfBytes);
printSummary();

posix_kill($pid, SIGTERM);
pcntl_waitpid($pid, $status);
exit($failed > 0 ? 1 : 0);

// ─── Tests HTTP ──────────────────────────────────────────────────────────────

function runHttpTests(KappelaBot $bot, int $chatId, string $png, string $wav, string $pdf): void
{
    // 1. Profil
    run('profile->get()', fn() => $bot->profile->get());

    // 2. Chats
    run('chats->list(limit=3)', fn() => $bot->chats->list(['limit' => 3]));

    // 3. Texte simple
    $sent = run('messages->send() — texte simple', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => '👋 Test SDK PHP — message texte',
    ]));

    // 4. Inline keyboard
    run('messages->send() — inline keyboard', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Test avec boutons inline :',
        'reply_markup' => [
            'inline_keyboard' => [[
                ['text' => '✅ Oui', 'callback_data' => 'yes'],
                ['text' => '❌ Non', 'callback_data' => 'no'],
            ]],
        ],
    ]));

    // 5. Reply keyboard
    run('messages->send() — reply keyboard', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Test reply keyboard :',
        'reply_markup' => [
            'keyboard' => [['Option A', 'Option B'], ['Annuler']],
        ],
    ]));

    // 6. Scroll keyboard
    run('messages->send() — scroll keyboard', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Test scroll keyboard :',
        'reply_markup' => [
            'scroll_keyboard' => ['Petit', 'Moyen', 'Grand', 'XL'],
        ],
    ]));

    // 7. Typing indicator
    run('messages->sendTyping() — show', fn() => $bot->messages->sendTyping(['chat_id' => $chatId]));
    run('messages->sendTyping() — hide', fn() => $bot->messages->sendTyping(['chat_id' => $chatId, 'is_typing' => false]));

    // 8. Photo
    run('messages->sendPhoto()', fn() => $bot->messages->sendPhoto([
        'chat_id' => $chatId,
        'file'    => ['data' => $png, 'filename' => 'test.png', 'content_type' => 'image/png'],
        'caption' => 'Test photo depuis le SDK PHP',
    ]));

    // 9. Document
    run('messages->sendDocument()', fn() => $bot->messages->sendDocument([
        'chat_id' => $chatId,
        'file'    => ['data' => $pdf, 'filename' => 'test.pdf', 'content_type' => 'application/pdf'],
        'caption' => 'Test document depuis le SDK PHP',
    ]));

    // 10. Audio
    run('messages->sendAudio()', fn() => $bot->messages->sendAudio([
        'chat_id' => $chatId,
        'file'    => ['data' => $wav, 'filename' => 'test.wav', 'content_type' => 'audio/wav'],
        'caption' => 'Test audio depuis le SDK PHP',
    ]));

    // 11. Carousel
    run('messages->sendCarousel()', fn() => $bot->messages->sendCarousel([
        'chat_id'              => $chatId,
        'text'                 => 'Test carousel :',
        'carousel'             => [
            ['id' => 'p1', 'title' => 'Produit A', 'subtitle' => '9,99 €',  'button_text' => 'Voir'],
            ['id' => 'p2', 'title' => 'Produit B', 'subtitle' => '19,99 €', 'button_text' => 'Voir'],
        ],
        'quick_reply_buttons'  => ['Voir plus', 'Annuler'],
    ]));

    // 12. Send + Edit
    $sentEdit = run('messages->send() — pour edit', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Message à modifier :',
        'reply_markup' => [
            'inline_keyboard' => [[['text' => '🔴 Avant', 'callback_data' => 'before']]],
        ],
    ]));
    if ($sentEdit !== null) {
        run('messages->edit() — texte seul', fn() => $bot->messages->edit([
            'chat_id'    => $chatId,
            'message_id' => $sentEdit->messageId,
            'new_text'   => 'Message modifié ✅',
        ]));
    }

    // 13. Delete
    if ($sent !== null) {
        run("messages->delete() — message_id={$sent->messageId}", fn() => $bot->messages->delete([
            'chat_id'    => $chatId,
            'message_id' => $sent->messageId,
        ]));
    }

    // 14. Webhook info
    run('webhooks->getInfo()', fn() => $bot->webhooks->getInfo());
}

function printSummary(): void
{
    global $passed, $failed;
    echo "\n" . str_repeat('─', 40) . "\n";
    echo "[✓] $passed passés   [✗] $failed échoués\n";
}
