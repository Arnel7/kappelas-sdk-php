<?php

declare(strict_types=1);

// Test LIVE de l'API user (stories + parité) contre le backend déployé.
//   php test_user.php <SK_API_KEY>
require_once __DIR__ . '/vendor/autoload.php';

use Kappelas\KappelaError;
use Kappelas\KappelaUser;

$key = $argv[1] ?? getenv('KAPPELA_API_KEY');
if (!$key) {
    fwrite(STDERR, "Usage: php test_user.php <SK_API_KEY>\n");
    exit(1);
}

$me = new KappelaUser($key);
$ok = 0;
$ko = 0;
$step = function (string $name, callable $fn) use (&$ok, &$ko) {
    try {
        $r = $fn();
        $ok++;
        echo "  [OK] $name\n";
        return $r;
    } catch (\Throwable $e) {
        $ko++;
        $m = $e instanceof KappelaError ? $e->errorCode . ': ' . $e->getMessage() : $e->getMessage();
        echo "  [KO] $name — " . strtok($m, "\n") . "\n";
        return null;
    }
};

$p = $step('getMe', fn () => $me->profile->get());
if ($p) {
    echo "     -> {$p->username}\n";
}

$step('stories.getPreferences', fn () => $me->stories->getPreferences());
$step('stories.listMine', fn () => $me->stories->listMine());
$step('stories.list', fn () => $me->stories->list());

$st = $step('stories.create(text)', fn () => $me->stories->create(['type' => 'text', 'caption' => 'PHP SDK live test', 'audience' => 'all']));
if ($st) {
    $step('stories.get', fn () => $me->stories->get($st->id));
    $step('stories.getViewers', fn () => $me->stories->getViewers($st->id));
    $step('stories.delete', fn () => $me->stories->delete($st->id));
}

$data = @file_get_contents('https://picsum.photos/400/600');
if ($data !== false) {
    $img = $step('stories.create(image)', fn () => $me->stories->create([
        'type'  => 'image',
        'media' => ['data' => $data, 'filename' => 't.jpg', 'content_type' => 'image/jpeg'],
        'caption' => 'img',
    ]));
    if ($img) {
        $step('stories.delete(image)', fn () => $me->stories->delete($img->id));
    }
}

$step('communities.list', fn () => $me->communities->list());
$c = $step('chats.list', fn () => $me->chats->list(['limit' => 5]));
if ($c) {
    echo '     -> chats: ' . count($c->chats) . "\n";
}

echo "\n" . ($ko === 0 ? 'ALL OK' : 'FAIL') . ": $ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
