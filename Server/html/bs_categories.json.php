<?php
// bs_categories.json.php — manage category names independent of any host.
// GET    → list categories
// POST   {name}                        → create a (possibly empty) category
// DELETE {name, clearFromHosts?:bool}  → delete a category; optionally null-out
//                                          all computers.category that match
// Auth: HTTP Basic, password = WEBADMINPASS

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

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
$mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
if ($mysqli->connect_errno) bs_fail(500, 'db: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

// --- Idempotent schema migration ---
// Pre-2026-05-02 schemas didn't have bs_categories at all. Older intermediate
// schemas have the table without sort_order. Both heal here.
function bs_table_has_column(mysqli $db, string $table, string $col): bool {
    static $cache = [];
    $k = "$table.$col";
    if (isset($cache[$k])) return $cache[$k];
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $r = $stmt->get_result();
    $cache[$k] = ($r && $r->fetch_row() !== null);
    $stmt->close();
    return $cache[$k];
}

$mysqli->query("CREATE TABLE IF NOT EXISTS bs_categories (
    name        VARCHAR(100) NOT NULL PRIMARY KEY,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!bs_table_has_column($mysqli, 'bs_categories', 'sort_order')) {
    $mysqli->query("ALTER TABLE bs_categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
    // Bust the cache after the ALTER.
    bs_table_has_column($mysqli, 'bs_categories', 'sort_order');
}
$hasSortOrder = bs_table_has_column($mysqli, 'bs_categories', 'sort_order');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $names = [];
    $sql = $hasSortOrder
        ? 'SELECT name FROM bs_categories ORDER BY sort_order, name'
        : 'SELECT name FROM bs_categories ORDER BY name';
    if ($r = $mysqli->query($sql)) {
        while ($row = $r->fetch_assoc()) $names[] = $row['name'];
    }
    echo json_encode(['categories' => $names]);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) bs_fail(400, 'bad json body');

// Reorder: PUT with {"order": ["catA","catB",...]} sets sort_order in that order.
if ($method === 'PUT') {
    $order = $data['order'] ?? null;
    if (!is_array($order)) bs_fail(400, 'order array required');
    if (!$hasSortOrder) {
        bs_fail(409, 'sort_order column missing — run migrations/2026-05-03-categories-sort-order.sql');
    }
    foreach ($order as $i => $name) {
        if (!is_string($name) || $name === '') continue;
        $stmt = $mysqli->prepare('UPDATE bs_categories SET sort_order=? WHERE name=?');
        $idx = (int)$i;
        $stmt->bind_param('is', $idx, $name);
        $stmt->execute();
    }
    echo json_encode(['ok' => true, 'reordered' => count($order)]);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
if ($name === '') bs_fail(400, 'name required');
if (strlen($name) > 100) bs_fail(400, 'name too long (max 100)');

if ($method === 'POST') {
    if ($hasSortOrder) {
        // Insert at the end of the current sort order.
        $maxRes = $mysqli->query('SELECT COALESCE(MAX(sort_order), -1) AS m FROM bs_categories');
        $next = 0;
        if ($maxRes && ($r = $maxRes->fetch_assoc())) {
            $next = (int)$r['m'] + 1;
        }
        $stmt = $mysqli->prepare('INSERT IGNORE INTO bs_categories (name, sort_order) VALUES (?, ?)');
        $stmt->bind_param('si', $name, $next);
    } else {
        $stmt = $mysqli->prepare('INSERT IGNORE INTO bs_categories (name) VALUES (?)');
        $stmt->bind_param('s', $name);
    }
    if (!$stmt->execute()) bs_fail(500, 'insert failed: ' . $mysqli->error);
    echo json_encode(['ok' => true, 'name' => $name, 'created' => $stmt->affected_rows > 0]);
    exit;
}

if ($method === 'DELETE') {
    $clear = !empty($data['clearFromHosts']);
    $cleared = 0;
    if ($clear) {
        $u = $mysqli->prepare("UPDATE computers SET category='' WHERE category=?");
        $u->bind_param('s', $name);
        if ($u->execute()) $cleared = $u->affected_rows;
    }
    $d = $mysqli->prepare('DELETE FROM bs_categories WHERE name=?');
    $d->bind_param('s', $name);
    if (!$d->execute()) bs_fail(500, 'delete failed: ' . $mysqli->error);
    echo json_encode(['ok' => true, 'name' => $name, 'deleted' => $d->affected_rows > 0, 'cleared' => $cleared]);
    exit;
}

bs_fail(405, 'method not allowed', ['allowed' => ['GET', 'POST', 'DELETE']]);
