<?php
// bs_host_action.json.php — POST {action, blueskyid} to mutate a host row.
// Auth: HTTP Basic — WEBADMINPASS by default, or the live web-admin password in
//       the DB when WEBADMIN_AUTH=db (or WEBADMINPASS is unset). See bs_auth.php.
// Actions:
//   "selfdestruct"  → UPDATE computers SET selfdestruct=1 (client uninstalls on next check-in)
//   "delete"        → DELETE FROM computers WHERE blueskyid=N (also wipes the corresponding pubkey from /home/bluesky/.ssh/authorized_keys)
//   "block"         → adds the host's serial to BlueSky.blocked_serials, installs a BEFORE INSERT trigger on `computers` that rejects future inserts with that serial, and runs the same delete teardown. Used to permanently keep a sold/transferred Mac off the fleet even though its BlueSky agent will keep retrying. Pair with examples/bluesky/scripts/purge-blocked.sh (cron, every minute) for belt-and-suspenders cleanup if a rogue host somehow re-registers via a different path.
//   "unblock"       → DELETE FROM blocked_serials WHERE serial=? (POSTs with {serial: "..."} instead of {blueskyid: N} since the host row is already gone). After unblocking, the next time the Mac's BlueSky agent reconnects, the row is recreated normally and the host reappears in BlueConnect.
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
if (!in_array($action, ['selfdestruct', 'delete', 'block', 'unblock'], true)) {
    bs_fail(400, 'unknown action', ['allowed' => ['selfdestruct', 'delete', 'block', 'unblock']]);
}

// `unblock` is keyed on serial (the host row is already gone, so there's
// no blueskyid to look up). Every other action is keyed on blueskyid.
$bid = 0;
if ($action !== 'unblock') {
    $bid = (int)($data['blueskyid'] ?? 0);
    if ($bid <= 0) bs_fail(400, 'invalid blueskyid');
}

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
if ($mysqli->connect_errno) bs_fail(500, 'db: ' . $mysqli->connect_error);

if ($action === 'unblock') {
    $serial = trim((string)($data['serial'] ?? ''));
    if ($serial === '' || strlen($serial) > 64) {
        bs_fail(400, 'invalid serial — must be 1..64 chars');
    }
    $stmt = $mysqli->prepare('DELETE FROM blocked_serials WHERE serial=?');
    $stmt->bind_param('s', $serial);
    if (!$stmt->execute()) {
        // ER_NO_SUCH_TABLE (1146) — table doesn't exist yet, treat as no-op.
        if ($mysqli->errno === 1146) {
            echo json_encode(['ok' => true, 'action' => 'unblock', 'serial' => $serial, 'affected' => 0,
                              'note' => 'blocked_serials table does not exist (no host has ever been blocked) — nothing to unblock']);
            exit;
        }
        bs_fail(500, 'unblock failed: ' . $mysqli->error);
    }
    $affected = $stmt->affected_rows;
    echo json_encode([
        'ok'       => true,
        'action'   => 'unblock',
        'serial'   => $serial,
        'affected' => $affected,
        'note'     => $affected > 0
            ? 'serial removed from blocklist; trigger will allow the host to re-register on its next reconnect'
            : 'serial was not in the blocklist — no change',
    ]);
    exit;
}

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

// Block: add the host's serial to a server-side blocklist, then run the same
// teardown as `delete`. The `blocked_serials` table and `bc_block_rogue_insert`
// trigger are created by migrations/2026-05-27-blocked-serials.sql — this
// endpoint only checks they exist and surfaces a clear error if not. Idempotent
// — running on an already-blocked host just re-runs delete teardown.
if ($action === 'block') {
    // Verify the schema migration has been applied — earlier versions
    // of this endpoint auto-created the table inline; we no longer do.
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'blocked_serials'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        bs_fail(500, 'blocked_serials table missing — apply migrations/2026-05-27-blocked-serials.sql on the BSC server');
    }

    // Detect whether the BEFORE INSERT trigger is installed. The migration
    // installs it, but CREATE TRIGGER needs SUPER on some MySQL builds and
    // may have been skipped. We surface the install status in the response
    // so the caller (and admin log) knows which layer of defense is active.
    $triggerInstalled  = false;
    $triggerInstallErr = null;
    $trgRes = $mysqli->query(
        "SELECT 1 FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND TRIGGER_NAME   = 'bc_block_rogue_insert'
         LIMIT 1"
    );
    if ($trgRes && $trgRes->num_rows > 0) {
        $triggerInstalled = true;
    } else {
        $triggerInstallErr = 'trigger bc_block_rogue_insert not installed — re-run migrations/2026-05-27-blocked-serials.sql with a MySQL user that has SUPER, or rely on the cron sweeper';
    }

    // Look up the host's serial so we can store it in the blocklist.
    $serialToBlock = '';
    if ($s = $mysqli->prepare('SELECT serialnum FROM computers WHERE blueskyid=? LIMIT 1')) {
        $s->bind_param('i', $bid);
        $s->execute();
        $res = $s->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $serialToBlock = trim((string)($row['serialnum'] ?? ''));
        }
        $s->close();
    }
    if ($serialToBlock === '') {
        bs_fail(409, "host #$bid has no serial number — cannot block by serial; use selfdestruct or delete instead");
    }

    $note = isset($data['note']) ? substr((string)$data['note'], 0, 255) : null;
    $ins = $mysqli->prepare("INSERT INTO blocked_serials (serial, blueskyid_at_block, note)
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                                 blueskyid_at_block = VALUES(blueskyid_at_block),
                                 note               = COALESCE(VALUES(note), note)");
    $ins->bind_param('sis', $serialToBlock, $bid, $note);
    if (!$ins->execute()) bs_fail(500, 'insert blocked_serials failed: ' . $mysqli->error);

    // Fall through to the existing delete path so the row + key are scrubbed
    // immediately. Marking $action as delete lets the existing code do its
    // thing and report `affected` / `authorizedKeyRemoved`.
    $action = 'delete';
    $isBlockFollowup = true;
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

    $wasBlock = !empty($isBlockFollowup);
    $blockNote = ($triggerInstalled ?? false)
        ? 'serial added to blocked_serials; row + key removed; DB trigger active — future re-registration rejected at INSERT'
        : 'serial added to blocked_serials; row + key removed; DB trigger could NOT be installed (likely SUPER privilege missing) — relying on cron sweeper for ongoing enforcement';
    echo json_encode([
        'ok'                   => true,
        'action'               => $wasBlock ? 'block' : 'delete',
        'blueskyid'            => $bid,
        'affected'             => $affected,
        'authorizedKeyRemoved' => $removedKey,
        'serialBlocked'        => $wasBlock ? ($serialToBlock ?? null) : null,
        'triggerInstalled'     => $wasBlock ? ($triggerInstalled ?? false) : null,
        'triggerInstallError'  => $wasBlock ? ($triggerInstallErr ?? null) : null,
        'note'                 => $wasBlock
            ? $blockNote
            : 'row deleted; client tunnel will fail on next reconnect',
    ]);
    exit;
}
