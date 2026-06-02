<?php

declare(strict_types=1);

// Test LIVE du module communautés contre le backend déployé.
//   php test_communities.php <TOKEN>
require_once __DIR__ . '/vendor/autoload.php';

use Kappelas\KappelaBot;

$token = $argv[1] ?? getenv('KAPPELA_TOKEN');
if (!$token) {
    fwrite(STDERR, "Usage: php test_communities.php <TOKEN>\n");
    exit(1);
}

$bot = new KappelaBot($token);
$fail = 0;
$ok = static function (string $m): void { echo "  [OK] $m\n"; };
$ko = function (string $m) use (&$fail): void { $fail++; echo "  [FAIL] $m\n"; };

$c = $bot->communities->create(['name' => 'PHP SDK live test (auto)']);
$ok("create id={$c->id}");
try {
    $list = $bot->communities->list();
    $role = null;
    foreach ($list as $x) {
        if ($x->id === $c->id) { $role = $x->role; }
    }
    $role === 'admin' ? $ok("list role=admin") : $ko("list role=$role (attendu admin)");

    $inv = $bot->communities->createInviteLink(['community_id' => $c->id, 'max_uses' => 1, 'expires_in' => '1h']);
    $ok("invite code={$inv->code}");

    $bot->communities->revokeInviteLink(['community_id' => $c->id, 'code' => $inv->code]);
    $ok("revoke");

    $admins = $bot->communities->listAdmin();
    in_array($c->id, array_map(static fn ($x) => $x->id, $admins), true)
        ? $ok("listAdmin contient la commu") : $ko("listAdmin ne contient pas la commu");
} finally {
    $bot->communities->delete(['community_id' => $c->id]);
    $ok("delete id={$c->id}");
}

echo $fail ? "\n=== ECHECS: $fail ===\n" : "\n=== TOUT PASSE ===\n";
exit($fail ? 1 : 0);
