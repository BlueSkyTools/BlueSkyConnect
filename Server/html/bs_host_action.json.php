<?php
// bs_host_action.json.php — POST {action, blueskyid} to mutate a host row.
// Auth: HTTP Basic — WEBADMINPASS by default, or the live web-admin password in
//       the DB when WEBADMIN_AUTH=db (or WEBADMINPASS is unset). See bs_auth.php.
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

require __DIR__ . '/bs_auth.php';

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
    // Wipe pubkey line(s) from /home/bluesky/.ssh/authorized_keys.
    //
    // Two key-comment formats exist in the wild:
    //   - Modern (dev2xx / 2.5.x+): each line's trailing comment is the
    //     Mac's hardware serial number, written by keymaster.sh on first
    //     check-in. Match `\b<serial>\b`.
    //   - Legacy (older BSC): each line's trailing comment was the
    //     literal string "BlueSky-NN" where NN is the blueskyid. Match
    //     `\bBlueSky-NN\b`. The \b word boundary keeps "BlueSky-2" from
    //     matching "BlueSky-22".
    //
    // We try BOTH patterns so the delete works on either layout. The
    // serial lookup happens BEFORE the DELETE so we still have it.
    //
    // BUG HISTORY: an earlier version had a second preg_match clause
    // intended as a fallback for the legacy format, but its ternary
    // collapsed to the literal pattern `/\bBlueSky-/` — which matches
    // EVERY BlueSky-tagged key line, and since array_filter required
    // BOTH clauses to fail, deleting any single host silently wiped
    // reverse-tunnel SSH authorization for the entire fleet. The two
    // explicit boundary-anchored patterns below are the correct version.
    $authKeys = '/home/bluesky/.ssh/authorized_keys';
    $removedKey = false;

    // Look up this host's serial number before we delete the DB row,
    // so we can match the modern key-comment format too.
    $serial = '';
    if ($s = $mysqli->prepare('SELECT serialnum FROM computers WHERE blueskyid=? LIMIT 1')) {
        $s->bind_param('i', $bid);
        $s->execute();
        $res = $s->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $serial = trim((string)($row['serialnum'] ?? ''));
        }
        $s->close();
    }

    if (is_writable($authKeys)) {
        // Match by the LAST whitespace-separated token of each line
        // (the SSH key comment), not by regex against the full line —
        // base64 key material can incidentally contain the serial
        // bounded by `+`, `/`, or `=` and a naive `\b<serial>\b`
        // would match inside it, removing the wrong key.
        $needles = ['BlueSky-' . $bid];
        if ($serial !== '') $needles[] = $serial;
        $needleSet = array_flip($needles);

        // Hold an exclusive flock on the file for the entire
        // read-modify-write cycle so concurrent BSC keymaster.sh writes
        // (a host registering, a key rotating) and a delete request
        // can't interleave and lose updates. The lock is best-effort —
        // file_put_contents() doesn't honor flock, so we open the file
        // ourselves, ftruncate + fwrite while holding the lock.
        $fp = @fopen($authKeys, 'c+');
        if ($fp !== false) {
            if (@flock($fp, LOCK_EX)) {
                // Re-read inside the lock so we see the latest content
                // even if another writer ran between is_writable and
                // here.
                $contents = '';
                while (!feof($fp)) {
                    $chunk = fread($fp, 8192);
                    if ($chunk === false) break;
                    $contents .= $chunk;
                }
                $lines = preg_split("/\r\n|\n|\r/", $contents);
                if ($lines === false) $lines = [];
                $kept = array_filter($lines, function($l) use ($needleSet) {
                    $stripped = trim($l);
                    if ($stripped === '' || $stripped[0] === '#') return true;
                    $parts = preg_split('/\s+/', $stripped);
                    if ($parts === false || count($parts) === 0) return true;
                    $comment = end($parts);
                    return !isset($needleSet[$comment]);
                });
                if (count($kept) !== count($lines)) {
                    rewind($fp);
                    ftruncate($fp, 0);
                    $bytes = fwrite($fp, implode("\n", $kept) . "\n");
                    fflush($fp);
                    if ($bytes !== false) $removedKey = true;
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
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
