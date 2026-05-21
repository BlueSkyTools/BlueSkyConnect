<?php
// bs_hosts.json.php — JSON list of BlueSky hosts + active-tunnel state
// Auth: HTTP Basic, password = WEBADMINPASS env var (any username)
// Drop into /var/www/html/ inside the bluesky container.

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Read an env var by name, falling back to /proc/1/environ when Apache
// has stripped the process env (which it usually has).
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
if ($expectedPass === '') {
    bs_fail(500, 'WEBADMINPASS not set on server');
}

$givenPass = trim($_SERVER['PHP_AUTH_PW'] ?? '');
if ($givenPass === '' || !hash_equals($expectedPass, $givenPass)) {
    header('WWW-Authenticate: Basic realm="BlueSky Hosts"');
    bs_fail(401, 'unauthorized');
}

// Active reverse-tunnel ports = listeners on 127.0.0.1:22000-22999
$activeIDs = [];
$tcp = @file('/proc/net/tcp');
if ($tcp !== false) {
    foreach (array_slice($tcp, 1) as $line) {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 4 || $cols[3] !== '0A') continue;
        if (!preg_match('/^0100007F:([0-9A-F]{4})$/i', $cols[1], $m)) continue;
        $port = hexdec($m[1]);
        if ($port >= 22000 && $port < 23000) {
            $activeIDs[$port - 22000] = true;
        }
    }
}

$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbUser = 'root';
$dbPass = bs_env('MYSQLROOTPASS');
$dbName = 'BlueSky';

if ($dbPass === '') {
    bs_fail(500, 'MYSQLROOTPASS not set on server');
}

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    bs_fail(500, 'db connect failed', ['detail' => $mysqli->connect_error]);
}
$mysqli->set_charset('utf8mb4');

// --- Idempotent schema migration ---
// Stock sphen/bluesky / blueskytools images ship a `computers` table
// with only the upstream columns. BlueConnect Admin needs a handful more
// (category, favorite, notes, serialnum, notify, alert, email + an index
// on category). This block adds anything missing on first hit so an
// operator never has to remember to run the SQL file by hand.
//
// One information_schema query covers all columns; ALTERs only fire on
// a missing column. After the first successful request against this
// endpoint the steady-state cost is one fast lookup.
$bsNeedCols = [
    'category'  => 'VARCHAR(100) NULL DEFAULT NULL',
    'favorite'  => 'TINYINT(1) NOT NULL DEFAULT 0',
    'notes'     => 'TEXT NULL',
    'serialnum' => 'VARCHAR(64) NULL DEFAULT NULL',
    'notify'    => 'TINYINT(1) NOT NULL DEFAULT 0',
    'alert'     => 'TINYINT(1) NOT NULL DEFAULT 0',
    'email'     => 'VARCHAR(255) NULL DEFAULT NULL',
];
$bsExistingCols = [];
if ($r = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME   = 'computers'")) {
    while ($row = $r->fetch_row()) $bsExistingCols[$row[0]] = true;
}
foreach ($bsNeedCols as $bsCol => $bsType) {
    // Names hardcoded above — interpolation is safe.
    if (!isset($bsExistingCols[$bsCol])) {
        $mysqli->query("ALTER TABLE computers ADD COLUMN `$bsCol` $bsType");
    }
}
// Index on category — speeds up the grouped-by-category host list query
// on large fleets. Idempotent.
$bsIdxR = $mysqli->query("SELECT 1 FROM information_schema.STATISTICS
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME   = 'computers'
                            AND INDEX_NAME   = 'idx_computers_category'
                          LIMIT 1");
if ($bsIdxR && $bsIdxR->num_rows === 0) {
    $mysqli->query("CREATE INDEX idx_computers_category ON computers (category)");
}

/** Resolve the actual installed BlueSky version, in this order:
 *   1. /usr/local/bin/BlueSky/Server/version.json — authoritative; ships in
 *      the dev2xx image.
 *   2. /usr/local/bin/BlueSky/version.json — earlier path (pre-2.5.0).
 *   3. BLUESKY_VERSION env var — fallback for the 2.3.2 image which
 *      doesn't ship version.json.
 *  Decoupling the reported version from the env var means an upgrade no
 *  longer requires hand-editing .env. */
function bs_bluesky_version(): string {
    $candidates = [
        '/usr/local/bin/BlueSky/Server/version.json',
        '/usr/local/bin/BlueSky/version.json',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && $raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j)) {
                    foreach (['version', 'Version', 'VERSION'] as $key) {
                        if (isset($j[$key]) && is_string($j[$key]) && $j[$key] !== '') {
                            return $j[$key];
                        }
                    }
                }
                // version.json could also be a bare string.
                $trim = trim($raw);
                if ($trim !== '' && $trim !== '{}' && strlen($trim) < 32) return $trim;
            }
        }
    }
    return bs_env('BLUESKY_VERSION');
}

/** Coerce a string to valid UTF-8 by substituting invalid byte sequences.
 *  Avoids mbstring (not installed in this php image).
 *
 *  Also unwinds double-encoded UTF-8 ("mojibake"). The BSC daemon used
 *  to round-trip latin1 columns through UTF-8 encoding on write, so the
 *  original UTF-8 bytes for a smart quote (e.g. 0xE2 0x80 0x99 for `’`)
 *  ended up encoded *again* as if they were Windows-1252 — landing in
 *  the database as a perfectly valid (but wrong) UTF-8 string like
 *  `â€™`. preg_match('//u') passes that string fine, so callers display
 *  the garbled glyphs.
 *
 *  Strategy: if the string is valid UTF-8 *and* contains telltale
 *  mojibake glyph pairs, try iconv-converting back to Windows-1252.
 *  If the resulting bytes are themselves valid UTF-8, that's the
 *  original text — return it. If anything fails, leave the string
 *  untouched. */
function bs_utf8($s) {
    if ($s === null || $s === '') return '';
    if (!is_string($s)) $s = (string)$s;
    if (@preg_match('//u', $s)) {
        // Already valid UTF-8 — but maybe double-encoded. Look for the
        // common 2-byte glyph pairs that fall out of Latin-1-as-UTF-8
        // re-encoding of original UTF-8 (curly quotes, é, è, à, ™, €).
        // Each marker is the UTF-8 byte sequence of glyphs that only show
        // up when latin1/cp1252 bytes get re-encoded as UTF-8:
        //   "\xC3\xA2\xE2\x82\xAC" → "â€" (curly quote / em-dash families)
        //   "\xC3\x83\xC2"          → "Ã" + start of another 2-byte seq
        //                              (covers é/è/à/í/ñ/ü/ö double-encoded)
        $mojibake_markers = ["\xC3\xA2\xE2\x82\xAC", "\xC3\x83\xC2"];
        $looks_double_encoded = false;
        foreach ($mojibake_markers as $m) {
            if (strpos($s, $m) !== false) { $looks_double_encoded = true; break; }
        }
        if ($looks_double_encoded && function_exists('iconv')) {
            $unwrapped = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
            if ($unwrapped !== false && $unwrapped !== '' && @preg_match('//u', $unwrapped)) {
                return $unwrapped;
            }
        }
        return $s;
    }
    if (function_exists('iconv')) {
        $out = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($out !== false && $out !== '') return $out;
        $out = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);
        if ($out !== false && $out !== '') return $out;
    }
    // Last resort: replace any high-byte runs with '?'.
    return preg_replace('/[\x80-\xFF]+/', '?', $s);
}

$rows = [];
$res = $mysqli->query(
    'SELECT blueskyid, hostname, sharingname, username, status, datetime, timestamp, category, favorite, notes, serialnum, notify, alert, email '
    . 'FROM computers ORDER BY blueskyid'
);
if ($res === false) {
    bs_fail(500, 'query failed', ['detail' => $mysqli->error]);
}
while ($row = $res->fetch_assoc()) {
    $bid = (int)$row['blueskyid'];
    $rows[] = [
        'blueskyid'   => $bid,
        'hostname'    => bs_utf8($row['hostname'] ?? ''),
        'sharingname' => bs_utf8($row['sharingname'] ?? ''),
        'username'    => bs_utf8($row['username'] ?? ''),
        'status'      => bs_utf8($row['status'] ?? ''),
        'lastSeen'    => bs_utf8($row['datetime'] ?? ''),
        'timestamp'   => (int)($row['timestamp'] ?? 0),
        'active'      => isset($activeIDs[$bid]),
        'sshPort'     => 22000 + $bid,
        'vncPort'     => 24000 + $bid,
        'category'    => bs_utf8($row['category'] ?? ''),
        'favorite'    => ((int)($row['favorite'] ?? 0)) === 1,
        'notes'       => bs_utf8($row['notes'] ?? ''),
        'serialnum'   => bs_utf8($row['serialnum'] ?? ''),
        'notify'      => ((int)($row['notify'] ?? 0)) === 1,
        'alert'       => ((int)($row['alert'] ?? 0)) === 1,
        'email'       => bs_utf8($row['email'] ?? ''),
    ];
}

// Categories from bs_categories ∪ those in use on computers (in case a row's
// category isn't yet registered as a standalone category).
$cats = [];
$catRes = $mysqli->query('SELECT name FROM bs_categories ORDER BY sort_order, name');
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $name = bs_utf8($r['name']);
        if ($name !== '') $cats[$name] = true;
    }
}
foreach ($rows as $h) {
    if (!empty($h['category'])) $cats[$h['category']] = true;
}
$catList = array_keys($cats);
sort($catList, SORT_NATURAL | SORT_FLAG_CASE);

$json = json_encode([
    'hosts'           => $rows,
    'serverFqdn'      => bs_utf8(bs_env('SERVERFQDN') ?: ($_SERVER['HTTP_HOST'] ?? '')),
    'activeCount'     => count($activeIDs),
    'categories'      => $catList,
    'blueSkyVersion'  => bs_utf8(bs_bluesky_version()),
    'phpVersion'      => PHP_VERSION,
    'apiVersion'      => '1.1',
]);
if ($json === false) {
    bs_fail(500, 'json_encode failed', ['detail' => json_last_error_msg()]);
}
echo $json;
