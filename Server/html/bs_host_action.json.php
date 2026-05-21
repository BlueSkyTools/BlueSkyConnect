<?php
// bs_host_action.json.php — POST {action, blueskyid} to mutate a host row.
// Auth: HTTP Basic, password = WEBADMINPASS env var (any username).
// Actions:
//   "selfdestruct"  → UPDATE computers SET selfdestruct=1 (client uninstalls on next check-in)
//   "delete"        → DELETE FROM computers WHERE blueskyid=N (also wipes the corresponding pubkey from /home/bluesky/.ssh/authorized_keys)
// Drop into /var/docker/bluesky/ on the host (it's bind-mounted into /var/www/html/).

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

$expectedPass = trim(bs_env('WEBADMINPASS'));
if ($expectedPass === '') bs_fail(500, 'WEBADMINPASS not set');

$givenPass = trim($_SERVER['PHP_AUTH_PW'] ?? '');
if ($givenPass === '' || !hash_equals($expectedPass, $givenPass)) {
    header('WWW-Authenticate: Basic realm="BlueSky Hosts"');
    bs_fail(401, 'unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bs_fail(405, 'POST required');
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) bs_fail(400, 'bad json body');

$action = (string)($data['action'] ?? '');
$bid    = (int)($data['blueskyid'] ?? 0);
if ($bid <= 0) bs_fail(400, 'invalid blueskyid');
if (!in_array($action, ['selfdestruct', 'delete'], true)) {
    bs_fail(400, 'unknown action', ['allowed' => ['selfdestruct', 'delete']]);
}

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
if ($mysqli->connect_errno) bs_fail(500, 'db: ' . $mysqli->connect_error);

if ($action === 'selfdestruct') {
    $stmt = $mysqli->prepare('UPDATE computers SET selfdestruct=1 WHERE blueskyid=?');
    $stmt->bind_param('i', $bid);
    if (!$stmt->execute()) bs_fail(500, 'update failed: ' . $mysqli->error);
    $affected = $stmt->affected_rows;
    echo json_encode([
        'ok'        => true,
        'action'    => 'selfdestruct',
        'blueskyid' => $bid,
        'affected'  => $affected,
        'note'      => 'client will uninstall on next check-in',
    ]);
    exit;
}

if ($action === 'delete') {
    // Wipe pubkey line from /home/bluesky/.ssh/authorized_keys (best-effort).
    // Each line ends with comment containing "BlueSky-NN".
    $authKeys = '/home/bluesky/.ssh/authorized_keys';
    $removedKey = false;
    if (is_writable($authKeys)) {
        $needle = 'BlueSky-' . $bid;
        $lines = @file($authKeys, FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            $kept = array_filter($lines, function($l) use ($needle) {
                // Strip lines whose comment exactly matches the BlueSky ID.
                // Match either "BlueSky-NN" or "BlueSky-NN<space>" or end-of-line.
                return !preg_match('/\bBlueSky-' . preg_quote((string)(intval($needle) ? '' : ''), '/') . '/', $l)
                    && !preg_match('/\b' . preg_quote($needle, '/') . '\b/', $l);
            });
            if (count($kept) !== count($lines)) {
                file_put_contents($authKeys, implode("\n", $kept) . "\n");
                $removedKey = true;
            }
        }
    }

    $stmt = $mysqli->prepare('DELETE FROM computers WHERE blueskyid=?');
    $stmt->bind_param('i', $bid);
    if (!$stmt->execute()) bs_fail(500, 'delete failed: ' . $mysqli->error);
    $affected = $stmt->affected_rows;
    echo json_encode([
        'ok'              => true,
        'action'          => 'delete',
        'blueskyid'       => $bid,
        'affected'        => $affected,
        'authorizedKeyRemoved' => $removedKey,
        'note'            => 'row deleted; client tunnel will fail on next reconnect',
    ]);
    exit;
}
