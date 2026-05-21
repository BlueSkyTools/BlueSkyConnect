<?php
// bs_health.json.php — unauthenticated lightweight health probe for monitoring.
// Returns active-tunnel count + total registered hosts + version info.
// Drop into /var/docker/bluesky/ on the host (bind-mounted into container).

ini_set('display_errors', '0');
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

header('Content-Type: application/json');

$active = 0;
$tcp = @file('/proc/net/tcp');
if ($tcp !== false) {
    foreach (array_slice($tcp, 1) as $line) {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 4 || $cols[3] !== '0A') continue;
        if (!preg_match('/^0100007F:([0-9A-F]{4})$/i', $cols[1], $m)) continue;
        $port = hexdec($m[1]);
        if ($port >= 22000 && $port < 23000) $active++;
    }
}

$total = 0;
$dbHost = bs_env('MYSQLSERVER') ?: 'db';
$dbPass = bs_env('MYSQLROOTPASS');
if ($dbPass !== '') {
    $mysqli = @new mysqli($dbHost, 'root', $dbPass, 'BlueSky');
    if (!$mysqli->connect_errno) {
        if ($r = $mysqli->query('SELECT COUNT(*) AS c FROM computers')) {
            $row = $r->fetch_assoc();
            $total = (int)$row['c'];
        }
    }
}

echo json_encode([
    'healthy'        => true,
    'active'         => $active,
    'total'          => $total,
    'blueSkyVersion' => bs_env('BLUESKY_VERSION') ?: '',
    'phpVersion'     => PHP_VERSION,
    'timestamp'      => date('c'),
]);
