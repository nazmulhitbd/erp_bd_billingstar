<?php
/**
 * Protection every request gets before any page code runs. Loaded first by addon/conf/dbconnect.php and
 * addon/inc/portal_base.php, so the older pages, which set their own cookies and headers, are covered without changing each one.
 */
require_once __DIR__ . '/audit.php';

const BS_LOGIN_USER_LIMIT = 5;   // wrong passwords for one user name before it pauses
const BS_LOGIN_IP_LIMIT = 20;    // wrong passwords from one address before it pauses
const BS_LOGIN_LOCK_SECONDS = 900;

if (PHP_SAPI !== 'cli' && !defined('BS_SECURITY_LOADED')) {
    define('BS_SECURITY_LOADED', true);
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN'); // the admin shell shows its pages in frames of its own
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // The older pages use inline scripts, so scripts stay allowed here. This stops being framed, plugins and a changed <base>.
    header("Content-Security-Policy: base-uri 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'self'");
    if (bs_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', bs_https() ? '1' : '0');
    header_register_callback('bs_lock_cookies');
}

/** Called as soon as the database is open, so anything refused here is on the audit trail. */
function bs_gate()
{
    if (PHP_SAPI === 'cli' || defined('BS_GATE_DONE')) {
        return;
    }
    define('BS_GATE_DONE', true);
    audit_boot();
    $ip = audit_ip();
    if (audit_ip_blocked($ip)) {
        if (!bs_limit_reached("blocked:$ip", 1)) { // one record every 10 minutes, however often it knocks
            bs_limit_hit("blocked:$ip", 600);
            audit_security('blocked_request', "Blocked address tried again: $ip");
        }
        http_response_code(403);
        exit('Access from this address has been blocked.');
    }
    audit_scan_request();
    $session = (string) ($_COOKIE['admin_sesid'] ?? '');
    if ($session !== '' && bs_session_id($session) === '') { // never reaches a page or a query
        audit_security('bad_session_cookie', 'Session cookie in the wrong form');
        unset($_COOKIE['admin_sesid']);
    }
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        audit_security('bad_method', "$method request refused");
        http_response_code(405);
        header('Allow: GET, POST, HEAD');
        exit;
    }
    if ($method === 'POST' && !bs_same_site_post()) {
        audit_security('cross_site_post', 'Form from another site refused', ['origin' => (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '')], 'bad');
        http_response_code(403);
        exit('This form came from another site, so it was refused.');
    }
}

function bs_https()
{
    return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** A browser says where a POST came from. Stops another site submitting a form with a signed-in user's cookies. */
function bs_same_site_post()
{
    $from = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($from === '' || $from === 'null') {
        return true; // privacy settings can hide where a form came from; the SameSite cookie below still applies
    }
    $url = parse_url($from);
    $host = strtolower(($url['host'] ?? '') . (isset($url['port']) ? ':' . $url['port'] : ''));
    return in_array($host, array_map('strtolower', array_filter([$_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? '', $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''])), true);
}

/** Every cookie the application sets, whichever page set it: not readable by scripts, not sent by other sites. */
function bs_lock_cookies()
{
    $cookies = array_filter(headers_list(), fn($line) => stripos($line, 'Set-Cookie:') === 0);
    if (!$cookies) {
        return;
    }
    header_remove('Set-Cookie');
    foreach (array_unique($cookies) as $line) {
        foreach (['HttpOnly' => '; HttpOnly', 'SameSite' => '; SameSite=Lax', 'Secure' => bs_https() ? '; Secure' : ''] as $flag => $text) {
            if (stripos($line, $flag) === false) {
                $line .= $text;
            }
        }
        header($line, false);
    }
}

/** True when a key has reached $max in its window. With no audit tables this limits nothing, so sign-in still works. */
function bs_limit_reached($key, $max)
{
    $db = empty($GLOBALS['audit_on']) ? null : audit_db();
    return $db && (int) (audit_query($db, 'SELECT hits FROM security_throttle WHERE bucket=? AND window_end >= NOW()', 's', [hash('sha256', $key)])[0]['hits'] ?? 0) >= $max;
}

function bs_limit_hit($key, $seconds)
{
    $db = empty($GLOBALS['audit_on']) ? null : audit_db();
    if ($db) {
        audit_query($db, 'INSERT INTO security_throttle (bucket, hits, window_end) VALUES (?, 1, NOW() + INTERVAL ? SECOND) ON DUPLICATE KEY UPDATE hits=IF(window_end<NOW(), 1, hits+1), window_end=IF(hits=1, VALUES(window_end), window_end)', 'si', [hash('sha256', $key), (int) $seconds]);
    }
}

function bs_limit_clear($key)
{
    $db = empty($GLOBALS['audit_on']) ? null : audit_db();
    if ($db) {
        audit_query($db, 'DELETE FROM security_throttle WHERE bucket=?', 's', [hash('sha256', $key)]);
    }
}

function bs_login_keys($userName)
{
    return ['login-user:' . strtolower(trim((string) $userName)), 'login-ip:' . ($_SERVER['REMOTE_ADDR'] ?? '')];
}

/** False while a user name or an address has had too many wrong passwords. Asked before any password is checked. */
function bs_login_allowed($userName)
{
    [$user, $ip] = bs_login_keys($userName);
    return !bs_limit_reached($user, BS_LOGIN_USER_LIMIT) && !bs_limit_reached($ip, BS_LOGIN_IP_LIMIT);
}

function bs_login_failed($userName)
{
    foreach (bs_login_keys($userName) as $key) {
        bs_limit_hit($key, BS_LOGIN_LOCK_SECONDS);
    }
}

/** A hexadecimal session id as the application issues them, or ''. Anything else never reaches a query. */
function bs_session_id($value)
{
    $value = (string) $value;
    return preg_match('/^[a-f0-9]{32}$/', $value) ? $value : '';
}

/** Text for an HTML page, for the older pages that have no helper of their own. */
function bs_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Rows per page and the first row, for the older list pages. Whole numbers only, as these go straight into LIMIT. */
function bs_paging()
{
    $limit = (int) ($_POST['record_count'] ?? $_COOKIE['limit'] ?? 25);
    return [$limit >= 1 && $limit <= 1000 ? $limit : 25, max(0, (int) ($_GET['start'] ?? 0))];
}

/** Text placed inside a LIKE, with the wildcards and quotes taken out of the user's hands. */
function bs_like($value)
{
    return addcslashes(mysql_real_escape_string((string) $value), '%_');
}

