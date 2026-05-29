<?php

declare(strict_types=1);

/**
 * Tests live du SDK Kappela PHP — v0.2.0
 *
 * Sections :
 *   1.  Profil bot
 *   2.  Chats : list (avec offset), iterate, getMyGroups
 *   3.  Texte simple
 *   4.  Formatage (gras, italique, barré, code inline, bloc, citation)
 *   5.  reply_to_id
 *   6.  bot->reply()
 *   7.  Keyboards (inline, reply keyboard, scroll keyboard — formes courte/longue/mixte)
 *   8.  Médias (photo, vidéo, document, audio)
 *   9.  Carousel (quick_reply forme courte, longue ; reply_to_id)
 *  10.  Edit / Delete
 *  11.  delete_previous
 *  12.  Typing indicator
 *  13.  Membres du groupe : getAdministrators, getMember, NOT_FOUND
 *  14.  Liens d'invitation : createInviteLink, createSingleUseInviteLink,
 *                            getInviteLinks, revokeInviteLink
 *  15.  Webhooks : getInfo
 *  16.  Gestion d'erreurs (codes KappelaError)
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
use Kappelas\Types\Message;

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

$passed  = 0;
$failed  = 0;
$skipped = 0;

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
        echo "  [✗] FAIL  KappelaError {$e->errorCode} ({$e->status}): {$e->errorMessage}\n";
        $failed++;
        return null;
    } catch (\Throwable $e) {
        echo "  [✗] FAIL  " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failed++;
        return null;
    }
}

function skipTest(string $label, string $reason): void
{
    global $skipped;
    echo "\n→ $label\n";
    echo "  [–] SKIP  $reason\n";
    $skipped++;
}

function expectError(string $label, string $expectedCode, callable $fn): void
{
    global $passed, $failed;
    echo "\n→ $label\n";
    try {
        $fn();
        echo "  [✗] FAIL  Expected KappelaError[$expectedCode] but no exception thrown\n";
        $failed++;
    } catch (KappelaError $e) {
        if ($e->errorCode === $expectedCode) {
            echo "  [✓] OK  KappelaError[$expectedCode] ({$e->status}) — {$e->errorMessage}\n";
            $passed++;
        } else {
            echo "  [✗] FAIL  Expected [$expectedCode] but got [{$e->errorCode}] — {$e->errorMessage}\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  [✗] FAIL  Unexpected exception: " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failed++;
    }
}

function printSummary(): void
{
    global $passed, $failed, $skipped;
    echo "\n" . str_repeat('─', 50) . "\n";
    echo "[✓] $passed passés   [✗] $failed échoués   [–] $skipped ignorés\n";
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

$connFlag = sys_get_temp_dir() . '/kappela_ws_connected_' . getmypid();

$bot->onConnected(function () use ($connFlag, $chatId) {
    file_put_contents($connFlag, '1');
    echo "[✓] Connecté — chat_id cible : $chatId\n\n";
});

$bot->onError(function (\Throwable $e) {
    echo "[!] Erreur WS : " . $e->getMessage() . "\n";
});

// Répondre aux clics de boutons pendant les tests
$bot->onCallbackQuery(function (CallbackQuery $cb) use ($bot) {
    $nom = $cb->senderName ?? $cb->senderId;
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

if (!function_exists('pcntl_fork')) {
    echo "[!] pcntl_fork non disponible — tests HTTP uniquement (pas de WS)\n\n";
    runAllTests($bot, $chatId, $pngBytes, $wavBytes, $pdfBytes);
    printSummary();
    exit($GLOBALS['failed'] > 0 ? 1 : 0);
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

// Processus parent : attendre la connexion
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

runAllTests($bot, $chatId, $pngBytes, $wavBytes, $pdfBytes);
printSummary();

// Laisser 30 secondes pour tester les clics interactifs
echo "\n[→] En attente de clics de boutons (30s)... Clique dans Kappela !\n";
sleep(30);
echo "[→] Fin du test interactif.\n";

posix_kill($pid, SIGTERM);
pcntl_waitpid($pid, $status);
exit($GLOBALS['failed'] > 0 ? 1 : 0);

// ─── Tests complets ──────────────────────────────────────────────────────────

function runAllTests(KappelaBot $bot, int $chatId, string $png, string $wav, string $pdf): void
{
    // ── 1. Profil ─────────────────────────────────────────────────────────────
    echo "\n── 1. Profil ──────────────────────────────────────────────────────────\n";
    run('profile->get()', fn() => $bot->profile->get());

    // ── 2. Chats ──────────────────────────────────────────────────────────────
    echo "\n── 2. Chats ───────────────────────────────────────────────────────────\n";
    $chatsRes = run('chats->list(limit=3)', fn() => $bot->chats->list(['limit' => 3]));
    run('chats->list(limit=2, offset=1)', fn() => $bot->chats->list(['limit' => 2, 'offset' => 1]));

    $iterCount = 0;
    run('chats->iterate(pageSize=2, max 4)', function () use ($bot, &$iterCount) {
        $bot->chats->iterate(2, function ($chat) use (&$iterCount) {
            $iterCount++;
            return $iterCount < 4;
        });
        return ['iterated' => $iterCount];
    });

    $groupsRes = run('chats->getMyGroups()', fn() => $bot->chats->getMyGroups());
    $adminGroupId = null;
    if ($groupsRes !== null) {
        foreach ($groupsRes->groups as $g) {
            if ($g->botRole === 'admin') {
                $adminGroupId = $g->chatId;
                break;
            }
        }
        echo "  [i] " . count($groupsRes->groups) . " groupe(s) trouvé(s)"
            . ($adminGroupId !== null ? " — admin dans $adminGroupId" : " — aucun groupe admin") . "\n";
    }

    // ── 3. Texte simple ───────────────────────────────────────────────────────
    echo "\n── 3. Texte simple ────────────────────────────────────────────────────\n";
    $sentFirst = run('messages->send() — texte simple', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => '👋 Test SDK PHP v0.2.0 — message texte',
    ]));

    // ── 4. Formatage ──────────────────────────────────────────────────────────
    echo "\n── 4. Formatage ───────────────────────────────────────────────────────\n";
    run('messages->send() — gras/italique/barré/code', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => '*gras*  __italique__  ~barré~  `code inline`',
    ]));
    run('messages->send() — bloc de code', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => "Ta clé API :\n```\nsk_live_test_abc123xyz\n```",
    ]));
    run('messages->send() — blockquote / citation', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => "> Question originale de l'utilisateur\n\nVoici la réponse détaillée.",
    ]));
    run('messages->send() — mentions & commandes', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => 'Merci @test ! Tape /help pour voir les commandes disponibles.',
    ]));
    run('messages->send() — liens auto-détectés', fn() => $bot->messages->send([
        'chat_id' => $chatId,
        'text'    => 'Visitez kappelas.com ou https://kappelas.com/docs',
    ]));

    // ── 5. reply_to_id ────────────────────────────────────────────────────────
    echo "\n── 5. reply_to_id ─────────────────────────────────────────────────────\n";
    if ($sentFirst !== null) {
        run('messages->send() — reply_to_id', fn() => $bot->messages->send([
            'chat_id'     => $chatId,
            'text'        => '↩️ Réponse ciblée via reply_to_id',
            'reply_to_id' => $sentFirst->messageId,
        ]));
    } else {
        skipTest('messages->send() — reply_to_id', 'premier message non envoyé');
    }

    // ── 6. bot->reply() ───────────────────────────────────────────────────────
    echo "\n── 6. bot->reply() ────────────────────────────────────────────────────\n";
    if ($sentFirst !== null) {
        // Construire un Message synthétique à partir du résultat SendResult
        $syntheticMsg = new Message(
            id:        $sentFirst->messageId,
            chatId:    $chatId,
            type:      'text',
            text:      'message original',
            createdAt: $sentFirst->createdAt ?? 0,
        );
        run('bot->reply(msg, text) — reply_to_id injecté automatiquement',
            fn() => $bot->reply($syntheticMsg, '↩️ bot->reply() — reply_to_id injecté automatiquement')
        );
        run('bot->reply(msg, text, options=[reply_markup]) — avec inline keyboard',
            fn() => $bot->reply($syntheticMsg, 'bot->reply() avec clavier inline :', [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '👍 Super', 'callback_data' => 'reply_ok'],
                        ['text' => '👎 Non',   'callback_data' => 'reply_no'],
                    ]],
                ],
            ])
        );
    } else {
        skipTest('bot->reply()', 'message de référence absent');
    }

    // ── 7. Keyboards ──────────────────────────────────────────────────────────
    echo "\n── 7. Keyboards ───────────────────────────────────────────────────────\n";

    // Inline keyboard — forme courte (text = callback_data)
    run('inline keyboard — forme courte', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Inline keyboard — forme courte :',
        'reply_markup' => [
            'inline_keyboard' => [[
                ['text' => '✅ Oui', 'callback_data' => 'yes'],
                ['text' => '❌ Non', 'callback_data' => 'no'],
            ]],
        ],
    ]));

    // Inline keyboard — plusieurs lignes
    run('inline keyboard — plusieurs lignes', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Inline keyboard — 2 lignes :',
        'reply_markup' => [
            'inline_keyboard' => [
                [['text' => '📦 Mes commandes', 'callback_data' => 'orders'],
                 ['text' => '❓ Aide',           'callback_data' => 'help']],
                [['text' => '⚙️ Paramètres',     'callback_data' => 'settings']],
            ],
        ],
    ]));

    // Reply keyboard — forme courte (strings purs)
    run('reply keyboard — forme courte (strings)', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Reply keyboard — strings purs :',
        'reply_markup' => [
            'keyboard' => [['Option A', 'Option B'], ['Annuler']],
        ],
    ]));

    // Reply keyboard — forme longue (text + callback_data)
    run('reply keyboard — forme longue {text, callback_data}', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Reply keyboard — forme longue :',
        'reply_markup' => [
            'keyboard' => [
                [
                    ['text' => '✅ Confirmer', 'callback_data' => 'confirm'],
                    ['text' => '❌ Annuler',   'callback_data' => 'cancel'],
                ],
            ],
        ],
    ]));

    // Reply keyboard — mixte (strings + objets)
    run('reply keyboard — mixte strings + objets', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Reply keyboard — mixte :',
        'reply_markup' => [
            'keyboard' => [
                [
                    ['text' => '✅ Oui', 'callback_data' => 'confirm_yes'],
                    ['text' => '❌ Non', 'callback_data' => 'confirm_no'],
                ],
                ['↩ Annuler'],
            ],
        ],
    ]));

    // Scroll keyboard — strings purs
    run('scroll keyboard — strings purs', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Scroll keyboard — strings :',
        'reply_markup' => [
            'scroll_keyboard' => ['Petit', 'Moyen', 'Grand', 'XL'],
        ],
    ]));

    // Scroll keyboard — forme longue (text + callback_data)
    run('scroll keyboard — forme longue {text, callback_data}', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Scroll keyboard — forme longue :',
        'reply_markup' => [
            'scroll_keyboard' => [
                ['text' => '📦 Commandes', 'callback_data' => 'menu_orders'],
                ['text' => '❓ Aide',       'callback_data' => 'menu_help'],
                ['text' => '⚙️ Paramètres', 'callback_data' => 'menu_settings'],
            ],
        ],
    ]));

    // Scroll keyboard — mixte
    run('scroll keyboard — mixte strings + objets', fn() => $bot->messages->send([
        'chat_id'      => $chatId,
        'text'         => 'Scroll keyboard — mixte :',
        'reply_markup' => [
            'scroll_keyboard' => [
                ['text' => '📦 Commandes', 'callback_data' => 'menu_orders'],
                '❓ Aide',
                ['text' => '⚙️ Paramètres', 'callback_data' => 'menu_settings'],
            ],
        ],
    ]));

    // ── 8. Médias ─────────────────────────────────────────────────────────────
    echo "\n── 8. Médias ──────────────────────────────────────────────────────────\n";
    run('messages->sendPhoto()', fn() => $bot->messages->sendPhoto([
        'chat_id' => $chatId,
        'file'    => ['data' => $png, 'filename' => 'test.png', 'content_type' => 'image/png'],
        'caption' => 'Test photo depuis le SDK PHP',
    ]));
    run('messages->sendVideo()', fn() => $bot->messages->sendVideo([
        'chat_id' => $chatId,
        'file'    => ['data' => $png, 'filename' => 'test.mp4', 'content_type' => 'video/mp4'],
        'caption' => 'Test vidéo depuis le SDK PHP',
    ]));
    run('messages->sendDocument()', fn() => $bot->messages->sendDocument([
        'chat_id' => $chatId,
        'file'    => ['data' => $pdf, 'filename' => 'test.pdf', 'content_type' => 'application/pdf'],
        'caption' => 'Test document depuis le SDK PHP',
    ]));
    run('messages->sendAudio()', fn() => $bot->messages->sendAudio([
        'chat_id' => $chatId,
        'file'    => ['data' => $wav, 'filename' => 'test.wav', 'content_type' => 'audio/wav'],
        'caption' => 'Test audio depuis le SDK PHP',
    ]));

    // sendPhoto avec reply_markup
    run('messages->sendPhoto() — avec reply_markup', fn() => $bot->messages->sendPhoto([
        'chat_id'      => $chatId,
        'file'         => ['data' => $png, 'filename' => 'test.png', 'content_type' => 'image/png'],
        'caption'      => 'Photo avec bouton inline',
        'reply_markup' => [
            'inline_keyboard' => [[['text' => '📷 Voir', 'callback_data' => 'photo_view']]],
        ],
    ]));

    // ── 9. Carousel ───────────────────────────────────────────────────────────
    echo "\n── 9. Carousel ────────────────────────────────────────────────────────\n";
    run('messages->sendCarousel() — quick_reply forme courte', fn() => $bot->messages->sendCarousel([
        'chat_id'             => $chatId,
        'text'                => 'Test carousel :',
        'carousel'            => [
            ['id' => 'p1', 'title' => 'Produit A', 'subtitle' => '9,99 €',  'button_text' => 'Voir'],
            ['id' => 'p2', 'title' => 'Produit B', 'subtitle' => '19,99 €', 'button_text' => 'Voir'],
        ],
        'quick_reply_buttons' => ['Voir plus', 'Annuler'],
    ]));

    $carousel2 = run('messages->sendCarousel() — quick_reply forme longue', fn() => $bot->messages->sendCarousel([
        'chat_id'             => $chatId,
        'text'                => 'Carousel — quick_reply {text, callback_data} :',
        'carousel'            => [
            ['id' => 'c1', 'title' => 'Article X', 'subtitle' => '5,00 €', 'button_text' => 'Ajouter'],
        ],
        'quick_reply_buttons' => [
            ['text' => '✅ Confirmer', 'callback_data' => 'confirm'],
            ['text' => '❌ Annuler',   'callback_data' => 'cancel'],
        ],
    ]));

    if ($carousel2 !== null) {
        run('messages->sendCarousel() — avec reply_to_id', fn() => $bot->messages->sendCarousel([
            'chat_id'     => $chatId,
            'text'        => 'Carousel en réponse :',
            'carousel'    => [
                ['id' => 'r1', 'title' => 'Produit réponse', 'subtitle' => '0,00 €', 'button_text' => 'OK'],
            ],
            'reply_to_id' => $carousel2->messageId,
        ]));
    } else {
        skipTest('messages->sendCarousel() — avec reply_to_id', 'carousel précédent non envoyé');
    }

    // ── 10. Edit / Delete ─────────────────────────────────────────────────────
    echo "\n── 10. Edit / Delete ──────────────────────────────────────────────────\n";
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
        run('messages->edit() — new_extra_data (inline keyboard)', fn() => $bot->messages->edit([
            'chat_id'        => $chatId,
            'message_id'     => $sentEdit->messageId,
            'new_extra_data' => [
                'inline_keyboard' => [[['text' => '🟢 Après', 'callback_data' => 'after']]],
            ],
        ]));
    }

    if ($sentFirst !== null) {
        run("messages->delete() — message_id={$sentFirst->messageId}", fn() => $bot->messages->delete([
            'chat_id'    => $chatId,
            'message_id' => $sentFirst->messageId,
        ]));
    }

    // ── 11. delete_previous ───────────────────────────────────────────────────
    echo "\n── 11. delete_previous ────────────────────────────────────────────────\n";
    run('messages->send() — delete_previous=true', fn() => $bot->messages->send([
        'chat_id'         => $chatId,
        'text'            => 'Message avec delete_previous — premier',
        'delete_previous' => true,
    ]));
    run('messages->send() — delete_previous (efface le précédent)', fn() => $bot->messages->send([
        'chat_id'         => $chatId,
        'text'            => 'Message avec delete_previous — remplace le précédent ✅',
        'delete_previous' => true,
    ]));

    // ── 12. Typing ────────────────────────────────────────────────────────────
    echo "\n── 12. Typing indicator ───────────────────────────────────────────────\n";
    run('messages->sendTyping() — show', fn() => $bot->messages->sendTyping(['chat_id' => $chatId]));
    run('messages->sendTyping() — hide', fn() => $bot->messages->sendTyping([
        'chat_id'   => $chatId,
        'is_typing' => false,
    ]));

    // ── 13. Membres du groupe ─────────────────────────────────────────────────
    echo "\n── 13. Membres du groupe ──────────────────────────────────────────────\n";
    if ($adminGroupId === null) {
        skipTest('chats->getAdministrators()', 'aucun groupe trouvé');
        skipTest('chats->getMember() — le bot lui-même', 'aucun groupe trouvé');
        skipTest('chats->getMember() — NOT_FOUND', 'aucun groupe trouvé');
    } else {
        $adminsRes = run('chats->getAdministrators()', fn() => $bot->chats->getAdministrators([
            'chat_id' => $adminGroupId,
        ]));

        // Récupérer l'ID du bot pour tester getMember
        $botProfile = null;
        try { $botProfile = $bot->profile->get(); } catch (\Throwable) {}

        if ($botProfile !== null) {
            run("chats->getMember() — le bot ({$botProfile->userId})", fn() => $bot->chats->getMember([
                'chat_id' => $adminGroupId,
                'user_id' => $botProfile->userId,
            ]));
        }

        expectError(
            'chats->getMember() — utilisateur inexistant → NOT_FOUND',
            KappelaError::NOT_FOUND,
            fn() => $bot->chats->getMember([
                'chat_id' => $adminGroupId,
                'user_id' => '99999999',
            ])
        );
    }

    // ── 14. Liens d'invitation ────────────────────────────────────────────────
    echo "\n── 14. Liens d'invitation ─────────────────────────────────────────────\n";
    if ($adminGroupId === null) {
        skipTest('chats->createInviteLink()', 'aucun groupe admin');
        skipTest('chats->createSingleUseInviteLink()', 'aucun groupe admin');
        skipTest('chats->getInviteLinks()', 'aucun groupe admin');
        skipTest('chats->revokeInviteLink()', 'aucun groupe admin');
    } else {
        $permLink = run('chats->createInviteLink() — permanent illimité', fn() => $bot->chats->createInviteLink([
            'chat_id' => $adminGroupId,
        ]));

        $singleLink = run('chats->createSingleUseInviteLink()', fn() => $bot->chats->createSingleUseInviteLink([
            'chat_id' => $adminGroupId,
        ]));

        run('chats->createInviteLink() — max_uses=5, expires_in=86400', fn() => $bot->chats->createInviteLink([
            'chat_id'    => $adminGroupId,
            'max_uses'   => 5,
            'expires_in' => 86400,
        ]));

        run('chats->getInviteLinks()', fn() => $bot->chats->getInviteLinks([
            'chat_id' => $adminGroupId,
        ]));

        if ($permLink !== null) {
            run("chats->revokeInviteLink() — code={$permLink->code}", fn() => $bot->chats->revokeInviteLink([
                'chat_id' => $adminGroupId,
                'code'    => $permLink->code,
            ]));
        }
        if ($singleLink !== null) {
            run("chats->revokeInviteLink() — single-use code={$singleLink->code}", fn() => $bot->chats->revokeInviteLink([
                'chat_id' => $adminGroupId,
                'code'    => $singleLink->code,
            ]));
        }
    }

    // ── 15. Webhooks ──────────────────────────────────────────────────────────
    echo "\n── 15. Webhooks ───────────────────────────────────────────────────────\n";
    run('webhooks->getInfo()', fn() => $bot->webhooks->getInfo());

    // ── 16. Gestion d'erreurs ─────────────────────────────────────────────────
    echo "\n── 16. Gestion d'erreurs ──────────────────────────────────────────────\n";

    expectError(
        'messages->send() — chat_id=0 → NOT_FOUND',
        KappelaError::NOT_FOUND,
        fn() => $bot->messages->send(['chat_id' => 0, 'text' => 'test'])
    );

    expectError(
        'messages->delete() — message_id inexistant → NOT_FOUND',
        KappelaError::NOT_FOUND,
        fn() => $bot->messages->delete(['chat_id' => $chatId, 'message_id' => 999999999])
    );

    expectError(
        'chats->createInviteLink() sur chat privé → FORBIDDEN',
        KappelaError::FORBIDDEN,
        fn() => $bot->chats->createInviteLink(['chat_id' => $chatId])
    );
}
