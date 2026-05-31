<?php
// bs_health.json.php — unauthenticated lightweight health probe for monitoring.
//
// Returns ONLY:
//   - healthy   : always true if the script ran
//   - active    : count of established reverse-tunnel ports (22000..22999)
//                 derived from /proc/net/tcp, no DB call
//   - timestamp : ISO-8601 server time
//
// Deliberately excluded from the unauthenticated payload (was here in prior
// versions; moved to the auth-gated `bs_hosts.json.php` payload instead):
//   - phpVersion / blueSkyVersion — version fingerprinting CVE-matching aid
//   - total (host count) — required a MySQL root connection on every hit,
//     amplifying any unauthenticated request into a DB connect (DoS surface)
//   - any DB connection at all — this endpoint must not touch the database
//
// The Mac client reads versions and counts from `bs_hosts.json.php`, which
// is HTTP Basic-auth gated. External monitors that need this endpoint
// only need "is the server returning 200 with a json body" — that's
// preserved.

ini_set('display_errors', '0');
error_reporting(E_ALL);

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

echo json_encode([
    'healthy'   => true,
    'active'    => $active,
    'timestamp' => date('c'),
]);
