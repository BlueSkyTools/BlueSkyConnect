<?php
// bs_auth.php — shared HTTP Basic auth for the authenticated bs_*.json.php endpoints.
//
// Two modes, selected by the WEBADMIN_AUTH env var:
//
//   (unset, default)  Compare the supplied password against the WEBADMINPASS env
//                     var, exactly as before. The username is ignored.
//                     If WEBADMINPASS is also unset, fall through to "db" so a
//                     server that only configures the DB still authenticates.
//   WEBADMIN_AUTH=db  Verify the supplied username/password against the live
//                     web-admin account in the database — md5(password) vs
//                     membership_users.passMD5 (matching AppGini's own login),
//                     restricted to approved, non-banned accounts. This tracks
//                     the password the admin actually uses, which WEBADMINPASS
//                     (a snapshot taken at container start) does not once the
//                     password is changed in the web admin.
//
// Included by an endpoint after it has defined bs_env() and bs_fail() and sent
// its Content-Type header; both helpers are reused here. All variables are
// bsAuth*-prefixed so they don't clash with the including endpoint's state.

if (!function_exists('bs_env') || !function_exists('bs_fail')) {
    // direct hit (e.g. GET /bs_auth.php) — nothing to authenticate against.
    http_response_code(404);
    exit;
}

$bsAuthMode = strtolower(trim(bs_env('WEBADMIN_AUTH')));
$bsAuthEnvPass = trim(bs_env('WEBADMINPASS'));
$bsAuthUseDb = ($bsAuthMode === 'db') || ($bsAuthMode === '' && $bsAuthEnvPass === '');

if (!$bsAuthUseDb) {
    // Legacy: shared password from the environment, username ignored.
    if ($bsAuthEnvPass === '') {
        bs_fail(500, 'WEBADMINPASS not set on server');
    }
    $bsAuthGiven = trim($_SERVER['PHP_AUTH_PW'] ?? '');
    if ($bsAuthGiven === '' || !hash_equals($bsAuthEnvPass, $bsAuthGiven)) {
        header('WWW-Authenticate: Basic realm="BlueSky Hosts"');
        bs_fail(401, 'unauthorized');
    }
    return;
}

// DB-backed: match the web-admin credentials AppGini stores.
$bsAuthUser = trim($_SERVER['PHP_AUTH_USER'] ?? '');
$bsAuthGiven = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
if ($bsAuthUser === '' || $bsAuthGiven === '') {
    header('WWW-Authenticate: Basic realm="BlueSky Hosts"');
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
$bsAuthOk = $bsAuthStmt->fetch() && hash_equals((string) $bsAuthHash, md5($bsAuthGiven));
$bsAuthStmt->close();
$bsAuthDb->close();

if (!$bsAuthOk) {
    header('WWW-Authenticate: Basic realm="BlueSky Hosts"');
    bs_fail(401, 'unauthorized');
}
// authenticated — fall through to the endpoint body.
