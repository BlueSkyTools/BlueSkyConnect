<?php
// bs_blocklist.json.php — GET the current list of serials in
// BlueSky.blocked_serials, plus the BID + note recorded when each was
// blocked. Used by BlueConnect's "Blocked Hosts" sheet to render an
// unblock list.
//
// Auth: shared HTTP Basic via bs_auth.php (same gate as bs_host_action,
// bs_hosts, etc.). No mutation — read-only, GET only.
//
// Response shape:
//   { "count": N,
//     "items": [
//       { "serial": "C02ABC123",
//         "added_at": "2026-05-23 14:01:33",
//         "blueskyid_at_block": 42,
//         "note": "sold to Alice" },
//       ...
//     ] }
//
// 200 also when the `blocked_serials` table doesn't exist yet — returns
// {count: 0, items: []} so the UI can render a clean empty state.

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function bs_env(string $name): string {
    $v = getenv($name);
    if ($v !== false && $v !== '') return $v;
    $proc = @file_get_contents('/proc/1/environ');
    if ($proc === false) return '';
    foreach (explode("\0", $proc) as $pair) {
        if (strncmp($pair, $name . '=', strlen($name) + 1) === 0) {
            return substr($pair, strlen($name) + 1);
        }
    }
    return '';
}

function bs_fail(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['error' => $msg], $extra));
    exit;
}

header('Content-Type: application/json');

require __DIR__ . '/bs_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    bs_fail(405, 'GET required');
}

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
if ($mysqli->connect_errno) bs_fail(500, 'db: ' . $mysqli->connect_error);

$res = @$mysqli->query(
    'SELECT serial, DATE_FORMAT(added_at, "%Y-%m-%d %H:%i:%s") AS added_at,
            blueskyid_at_block, note
       FROM blocked_serials
       ORDER BY added_at DESC'
);

if (!$res) {
    // ER_NO_SUCH_TABLE (1146) — table doesn't exist yet because no host
    // has ever been blocked. Return an empty list so the UI renders the
    // "no blocked hosts" state cleanly instead of an error.
    if ($mysqli->errno === 1146) {
        echo json_encode(['count' => 0, 'items' => []]);
        exit;
    }
    bs_fail(500, 'query failed: ' . $mysqli->error);
}

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'serial'             => $row['serial'],
        'added_at'           => $row['added_at'],
        'blueskyid_at_block' => $row['blueskyid_at_block'] !== null
                                ? (int)$row['blueskyid_at_block'] : null,
        'note'               => $row['note'],
    ];
}

echo json_encode([
    'count' => count($items),
    'items' => $items,
]);
