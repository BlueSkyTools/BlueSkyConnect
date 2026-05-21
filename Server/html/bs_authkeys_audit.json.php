<?php
// bs_authkeys_audit.json.php — authenticated audit of the BSC reverse-
// tunnel authorized_keys file. Lists every key entry and flags those
// whose comment doesn't correspond to any current host in the
// `computers` table ("orphans"). Read-only — never modifies the file.
//
// Auth: HTTP Basic — WEBADMINPASS by default, or the live web-admin password
//       in the DB when WEBADMIN_AUTH=db (or WEBADMINPASS is unset). See bs_auth.php.
//
// Two comment formats are recognised:
//   - Modern (dev2xx / 2.5.x+): trailing comment = Mac hardware serial
//   - Legacy (older BSC): trailing comment = "BlueSky-NN" where NN is
//     the blueskyid
//
// Returns JSON of shape:
//   {
//     "readable": true,
//     "total":   25,
//     "orphans": 3,
//     "entries": [
//       {"line": 1, "comment": "C02XYZ", "orphan": false,
//        "hostname": "lab-mini-3", "blueskyid": 42, "match": "serial"},
//       ...
//     ]
//   }
//
// If the keys file isn't readable by PHP (typically because the file
// is mode 0600 owned by the in-container `bluesky` user and PHP runs as
// `www-data`), returns:
//   {"readable": false, "reason": "...", "fixHint": "..."}
// — so the Mac app can render a clean "permissions need adjusting"
// message instead of a generic 500.

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

$authKeys = '/home/bluesky/.ssh/authorized_keys';
if (!@is_readable($authKeys)) {
    echo json_encode([
        'readable' => false,
        'reason'   => 'PHP (www-data) cannot read /home/bluesky/.ssh/authorized_keys — typically because the file is mode 0600 owned by the in-container bluesky user.',
        'fixHint'  => 'To enable this audit, the keys file must be readable by www-data. Either chmod 0640 + add www-data to the bluesky group, or expose a read-only sidecar copy. The audit is purely informational; the rest of BSC still works.',
    ]);
    exit;
}

// FILE_IGNORE_NEW_LINES only — NOT FILE_SKIP_EMPTY_LINES, which
// reindexes and would make our reported line numbers diverge from the
// actual file line numbers. We skip blanks in the loop and use the
// original 0-based index from the array.
$lines = @file($authKeys, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    bs_fail(500, 'authorized_keys read failed');
}

// Parse: each non-comment line's LAST whitespace-separated token is the
// SSH key comment (`ssh-rsa AAAA... <COMMENT>`).
$entries = [];
foreach ($lines as $idx => $raw) {
    $line = trim($raw);
    if ($line === '' || $line[0] === '#') continue;
    $parts = preg_split('/\s+/', $line);
    if ($parts === false || count($parts) === 0) continue;
    $comment = end($parts);
    $entries[] = [
        'line'    => $idx + 1,
        'comment' => $comment,
    ];
}

// Build known sets from the DB. If the DB is unreachable, set a flag
// and report entries with `orphan: false, unverified: true` so the UI
// doesn't falsely brand every key as orphaned during an outage.
$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$serialIndex = [];   // serial → ['blueskyid' => N, 'hostname' => 'foo']
$bidIndex    = [];   // "BlueSky-N" → same shape
$dbOk = false;
$dbError = null;

if ($dbPass === '') {
    $dbError = 'MYSQLROOTPASS not set on server';
} else {
    $mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
    if ($mysqli->connect_errno) {
        $dbError = 'db connect: ' . $mysqli->connect_error;
    } else {
        if ($r = $mysqli->query('SELECT blueskyid, hostname, serialnum FROM computers')) {
            while ($row = $r->fetch_assoc()) {
                $bid = (int)$row['blueskyid'];
                $hostname = (string)$row['hostname'];
                $serial = trim((string)($row['serialnum'] ?? ''));
                $bidIndex['BlueSky-' . $bid] = [
                    'blueskyid' => $bid, 'hostname' => $hostname,
                ];
                if ($serial !== '') {
                    $serialIndex[$serial] = [
                        'blueskyid' => $bid, 'hostname' => $hostname,
                    ];
                }
            }
            $dbOk = true;
        } else {
            $dbError = 'computers query failed: ' . $mysqli->error;
        }
    }
}

$annotated = [];
$orphanCount = 0;
foreach ($entries as $e) {
    $comment = $e['comment'];
    $hit = null;
    $matchKind = null;
    if (isset($serialIndex[$comment])) {
        $hit = $serialIndex[$comment];
        $matchKind = 'serial';
    } elseif (isset($bidIndex[$comment])) {
        $hit = $bidIndex[$comment];
        $matchKind = 'legacy';
    }
    // Only flag as orphan when we actually queried the DB. Without
    // verification we can't tell, so mark entries unverified instead.
    $isOrphan = $dbOk && $hit === null;
    if ($isOrphan) $orphanCount++;
    $annotated[] = [
        'line'       => $e['line'],
        'comment'    => $comment,
        'orphan'     => $isOrphan,
        'unverified' => !$dbOk,
        'hostname'   => $hit['hostname'] ?? null,
        'blueskyid'  => $hit['blueskyid'] ?? null,
        'match'      => $matchKind,
    ];
}

echo json_encode([
    'readable'  => true,
    'verified'  => $dbOk,
    'dbError'   => $dbError,
    'total'     => count($annotated),
    'orphans'   => $orphanCount,
    'entries'   => $annotated,
]);
