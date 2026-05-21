<?php
// bs_auth.php — DB-backed HTTP Basic auth for the BlueConnect-Admin endpoints.
//
// LOCAL ADDITION (not part of upstream BlueConnect-Admin). Replaces upstream's
// static WEBADMINPASS env-var check so the accepted password tracks the live
// web-admin password AppGini stores in membership_users.passMD5 (plain md5; see
// the login in incCommon.php). The env var is only a snapshot from container
// start, so it goes stale once the password is changed in the web admin.
//
// Re-applied over the freshly-fetched (pristine) endpoints by
// tools/refresh-blueconnect.sh via Server/blueconnect/patches/db-auth.patch.
//
// Included by an endpoint after it has defined bs_env() and bs_fail(); both are
// reused here. Variables are bsAuth*-prefixed so they don't clash with the
// including endpoint's own state.

if (!function_exists('bs_env') || !function_exists('bs_fail')) {
    // direct hit (e.g. GET /bs_auth.php) — nothing to authenticate against.
    http_response_code(404);
    exit;
}

$bsAuthUser = trim($_SERVER['PHP_AUTH_USER'] ?? '');
$bsAuthPass = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
if ($bsAuthUser === '' || $bsAuthPass === '') {
    header('WWW-Authenticate: Basic realm="BlueSky"');
    bs_fail(401, 'unauthorized');
}

$bsAuthDbPass = bs_env('MYSQLROOTPASS');
if ($bsAuthDbPass === '') {
    bs_fail(500, 'MYSQLROOTPASS not set on server');
}

$bsAuthDb = @new mysqli(bs_env('MYSQLSERVER') ?: 'db', 'root', $bsAuthDbPass, 'BlueSky');
if ($bsAuthDb->connect_errno) {
    bs_fail(500, 'auth db connection failed');
}

// Mirror AppGini's own login: md5(password) vs membership_users.passMD5,
// restricted to approved, non-banned accounts.
$bsAuthStmt = $bsAuthDb->prepare(
    'SELECT passMD5 FROM membership_users'
    . ' WHERE LCASE(memberID) = LCASE(?) AND isApproved = 1 AND isBanned = 0'
);
if ($bsAuthStmt === false) {
    bs_fail(500, 'auth query failed');
}
$bsAuthStmt->bind_param('s', $bsAuthUser);
$bsAuthStmt->execute();
$bsAuthStmt->bind_result($bsAuthHash);
$bsAuthOk = $bsAuthStmt->fetch() && hash_equals((string) $bsAuthHash, md5($bsAuthPass));
$bsAuthStmt->close();
$bsAuthDb->close();

if (!$bsAuthOk) {
    header('WWW-Authenticate: Basic realm="BlueSky"');
    bs_fail(401, 'unauthorized');
}
// authenticated — fall through to the endpoint body.
