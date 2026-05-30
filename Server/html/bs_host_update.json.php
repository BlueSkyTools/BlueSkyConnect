<?php
// bs_host_update.json.php — POST {blueskyid, hostname?} updates a computers row.
// Auth: HTTP Basic — WEBADMINPASS by default, or the live web-admin password in
//       the DB when WEBADMIN_AUTH=db (or WEBADMINPASS is unset). See bs_auth.php.
// Drop into /var/docker/bluesky/ on the host (bind-mounted into /var/www/html/).

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bs_fail(405, 'POST required');

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) bs_fail(400, 'bad json body');
$bid = (int)($data['blueskyid'] ?? 0);
if ($bid <= 0) bs_fail(400, 'invalid blueskyid');

// sharingname is the "subtitle" column BSC displays underneath hostname
// in clients like BlueConnect Admin. Kept aligned with hostname when
// the operator renames a Mac so the host card shows a single name
// instead of "newname / oldname".
$allowed = ['hostname', 'sharingname', 'username', 'notes', 'email', 'category', 'favorite', 'notify', 'alert'];

// Build update set from allowed fields only.
$updates = [];
$params  = [];
$types   = '';
$boolFields = ['favorite', 'notify', 'alert'];
foreach ($allowed as $f) {
    if (array_key_exists($f, $data)) {
        if (in_array($f, $boolFields, true)) {
            $val = !empty($data[$f]) ? 1 : 0;
            $updates[] = "$f = ?";
            $params[]  = $val;
            $types    .= 'i';
        } else {
            $val = (string)$data[$f];
            $updates[] = "$f = ?";
            $params[]  = $val;
            $types    .= 's';
        }
    }
}
if (empty($updates)) bs_fail(400, 'no updatable fields supplied', ['allowed' => $allowed]);

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
if ($mysqli->connect_errno) bs_fail(500, 'db: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

// If a non-empty category is being set, register it in bs_categories so it
// shows up in the sidebar even if no host currently has it.
//
// `CREATE TABLE IF NOT EXISTS` is idempotent and matches the schema that
// bs_categories.json.php creates — without this, a brand-new BSC where
// nobody has visited bs_categories.json.php yet would fatal here:
// prepare() returns false on the missing table and bind_param() throws.
if (array_key_exists('category', $data)) {
    $cat = trim((string)$data['category']);
    if ($cat !== '') {
        $mysqli->query("CREATE TABLE IF NOT EXISTS bs_categories (
            name        VARCHAR(100) NOT NULL PRIMARY KEY,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sort_order  INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($up = $mysqli->prepare('INSERT IGNORE INTO bs_categories (name) VALUES (?)')) {
            $up->bind_param('s', $cat);
            $up->execute();
            $up->close();
        }
        // Silent fallthrough on prepare failure — the column update
        // below is the user-facing action; the category-registration
        // side effect can be skipped without breaking the request.
    }
}

$sql = 'UPDATE computers SET ' . implode(', ', $updates) . ' WHERE blueskyid = ?';
$types .= 'i';
$params[] = $bid;

$stmt = $mysqli->prepare($sql);
if (!$stmt) bs_fail(500, 'prepare failed: ' . $mysqli->error);
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) bs_fail(500, 'update failed: ' . $mysqli->error);
$affected = $stmt->affected_rows;

echo json_encode([
    'ok'        => true,
    'blueskyid' => $bid,
    'affected'  => $affected,
    'updated'   => array_values(array_filter(
        $allowed,
        function($f) use ($data) { return array_key_exists($f, $data); }
    )),
]);
