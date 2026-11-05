<?php
/**
 * Shared base of the public portals (customer_portal, sr_portal): security headers, database connection, session,
 * query and formatting helpers, flash messages, CSRF and the sign-in rules. Each portal defines PORTAL_SESSION_NAME
 * (its own cookie) before including this file and keeps its own accounts and log tables.
 */

require_once __DIR__ . '/security.php';

const CP_IDLE_MINUTES = 30;
const CP_MAX_FAILS = 5;            // failed passwords before an account is locked
const CP_LOCK_MINUTES = 15;
const CP_IP_MAX_FAILS = 20;        // failed sign-ins from one address in CP_LOCK_MINUTES before it is refused
const CP_MIN_PASSWORD = 8;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

// addon/display/dbconfig.php opens the connection itself (as $conn); use it rather than opening a second one.
try {
    require dirname(__DIR__) . '/display/dbconfig.php';
    $cp_db = $conn;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $cp_db->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(503);
    error_log('Portal database: ' . $e->getMessage());
    exit('The portal is not available right now. Please try again later.');
}
unset($dbpass, $password, $conn, $link, $db);

$https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name(defined('PORTAL_SESSION_NAME') ? PORTAL_SESSION_NAME : 'PORTALSESSID');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
bs_gate();
require_once __DIR__ . '/perm.php';
require_once __DIR__ . '/notify.php';
perm_portal_bridge(PORTAL_SESSION_NAME === 'CPSESSID' ? 'cp_account' : 'sr_account', CP_IDLE_MINUTES);

/* ---------------- Database helpers ---------------- */

function cp_exec($sql, $types = '', array $params = [])
{
    $stmt = $GLOBALS['cp_db']->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $audit = audit_sql($GLOBALS['cp_db'], $sql, $types, $params);
    try {
        $stmt->execute();
    } catch (Throwable $e) {
        audit_sql_end($audit, false);
        throw $e;
    }
    audit_sql_end($audit, true, $stmt->insert_id);
    return $stmt;
}

function cp_rows($sql, $types = '', array $params = [])
{
    $stmt = cp_exec($sql, $types, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function cp_row($sql, $types = '', array $params = [])
{
    return cp_rows($sql, $types, $params)[0] ?? null;
}

function cp_insert($sql, $types, array $params)
{
    cp_exec($sql, $types, $params)->close();
    return (int) $GLOBALS['cp_db']->insert_id;
}

function cp_table_exists($table)
{
    try {
        $GLOBALS['cp_db']->query('SELECT 1 FROM `' . preg_replace('/[^a-z_]/', '', $table) . '` LIMIT 0');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------------- Formatting ---------------- */

function cp_h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cp_money($v)
{
    return number_format((float) $v, 2);
}

function cp_qty($v)
{
    return rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
}

function cp_date($d)
{
    return $d ? date('d/m/Y', strtotime(substr((string) $d, 0, 10))) : '';
}

/** Last 10 digits of a phone number, so 017..., +88017... and 17... compare equal. */
function cp_phone_key($v)
{
    return substr(preg_replace('/\D/', '', (string) $v), -10);
}

/** Warranty end date from text like "1 Year", "6 Months", "90 Days" and the sale date; null when unknown. */
function cp_warranty_end($warranty, $soldOn)
{
    if (!preg_match('/(\d+)\s*(day|month|mon|year|yr)/i', (string) $warranty, $m) || !$soldOn) {
        return null;
    }
    $u = strtolower($m[2]);
    return (new DateTime(substr($soldOn, 0, 10)))->add(new DateInterval('P' . (int) $m[1] . (str_starts_with($u, 'd') ? 'D' : (str_starts_with($u, 'y') ? 'Y' : 'M'))))->format('Y-m-d');
}

/* ---------------- Flash, CSRF ---------------- */

function cp_flash($kind, $message)
{
    $_SESSION['cp_flash'] = [$kind, $message];
}

function cp_take_flash()
{
    $f = $_SESSION['cp_flash'] ?? null;
    unset($_SESSION['cp_flash']);
    return $f ? '<div class="alert ' . ($f[0] === 'ok' ? 'ok' : 'err') . '">' . cp_h($f[1]) . '</div>' : '';
}

function cp_redirect($url, $kind = null, $message = '')
{
    if ($kind !== null) {
        cp_flash($kind, $message);
    }
    header('Location: ' . $url, true, 303);
    exit;
}

function cp_csrf_token()
{
    if (empty($_SESSION['cp_csrf'])) {
        $_SESSION['cp_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cp_csrf'];
}

function cp_csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . cp_h(cp_csrf_token()) . '">';
}

function cp_check_csrf($back)
{
    if (!hash_equals(cp_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        audit_security('csrf_failed', 'Form with a bad or expired check');
        cp_redirect($back, 'err', 'Your form expired. Please try again.');
    }
}

function cp_post($key, $max = 255)
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

/* ---------------- Sign-in (shared rules) ---------------- */

function cp_ip()
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Record a sign-in attempt in the portal's log table ($auth['log']). */
function cp_auth_log(array $auth, $accountId, $login, $success, $reason)
{
    cp_insert('INSERT INTO ' . $auth['log'] . ' (account_id, login, ip_address, user_agent, success, reason) VALUES (?, ?, ?, ?, ?, ?)', 'isssis',
        [$accountId, substr($login, 0, 60), cp_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $success ? 1 : 0, $reason]);
    $type = $auth['session'] === 'cp_account' ? 'customer' : 'employee';
    audit_login($success ? $type : 'anonymous', $success ? (int) $accountId : 0, $login, (bool) $success, $success ? '' : $type . ' portal, ' . str_replace('_', ' ', (string) $reason));
}

/**
 * Check a login and password against a portal's accounts. $auth: accounts (table), log (table), mobile_sql (finds
 * the account row by the last 10 digits of a mobile number, one ? placeholder), session (session key).
 * Returns [true, ''] and starts the session, or [false, message]. An unknown login and a wrong password get the
 * same message, an account locks after CP_MAX_FAILS wrong passwords, and an address after CP_IP_MAX_FAILS failures.
 */
function cp_auth_sign_in(array $auth, $login, $password)
{
    $login = trim($login);
    $ipFails = (int) cp_row('SELECT COUNT(*) AS n FROM ' . $auth['log'] . ' WHERE ip_address=? AND success=0 AND created_at > NOW() - INTERVAL ? MINUTE', 'si', [cp_ip(), CP_LOCK_MINUTES])['n'];
    if ($ipFails >= CP_IP_MAX_FAILS) {
        cp_auth_log($auth, null, $login, false, 'ip_throttled');
        return [false, 'Too many failed sign-ins from this connection. Please wait ' . CP_LOCK_MINUTES . ' minutes.'];
    }
    $acc = cp_row('SELECT a.* FROM ' . $auth['accounts'] . ' a WHERE a.login=?', 's', [$login]);
    if (!$acc && strlen(cp_phone_key($login)) === 10) {
        $acc = cp_row($auth['mobile_sql'], 's', [cp_phone_key($login)]);
    }
    $generic = 'The login or password is not correct.';
    if (!$acc) {
        password_verify($password, password_hash(random_bytes(8), PASSWORD_DEFAULT)); // take as long as a real check
        cp_auth_log($auth, null, $login, false, 'unknown_login');
        return [false, $generic];
    }
    $id = (int) $acc['account_id'];
    if ($acc['locked_until'] !== null && strtotime($acc['locked_until']) > time()) {
        cp_auth_log($auth, $id, $login, false, 'locked');
        return [false, 'This account is locked after too many failed sign-ins. Try again after ' . date('h:i A', strtotime($acc['locked_until'])) . '.'];
    }
    if (!password_verify($password, $acc['password_hash'])) {
        $fails = (int) $acc['failed_attempts'] + 1;
        cp_exec('UPDATE ' . $auth['accounts'] . ' SET failed_attempts=?, locked_until=IF(? >= ?, NOW() + INTERVAL ? MINUTE, NULL) WHERE account_id=?', 'iiiii', [$fails, $fails, CP_MAX_FAILS, CP_LOCK_MINUTES, $id])->close();
        cp_auth_log($auth, $id, $login, false, 'bad_password');
        return [false, $fails >= CP_MAX_FAILS ? 'Too many failed sign-ins. The account is locked for ' . CP_LOCK_MINUTES . ' minutes.' : $generic];
    }
    if ($acc['status'] !== 'active') {
        cp_auth_log($auth, $id, $login, false, 'disabled');
        return [false, 'This account is disabled. Please contact the office.'];
    }
    if (password_needs_rehash($acc['password_hash'], PASSWORD_DEFAULT)) {
        cp_exec('UPDATE ' . $auth['accounts'] . ' SET password_hash=? WHERE account_id=?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id])->close();
    }
    cp_exec('UPDATE ' . $auth['accounts'] . ' SET failed_attempts=0, locked_until=NULL, last_login_at=NOW(), last_login_ip=? WHERE account_id=?', 'si', [cp_ip(), $id])->close();
    cp_auth_log($auth, $id, $login, true, 'ok');
    session_regenerate_id(true);
    $_SESSION[$auth['session']] = $id;
    $_SESSION['cp_seen'] = time();
    return [true, ''];
}

function cp_sign_out()
{
    audit_logout();
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Change a signed-in account's password after checking the current one. Returns '' or an error message. */
function cp_auth_change_password(array $auth, $accountId, $current, $new, $repeat)
{
    $acc = cp_row('SELECT password_hash FROM ' . $auth['accounts'] . ' WHERE account_id=?', 'i', [(int) $accountId]);
    if (!$acc || !password_verify((string) $current, $acc['password_hash'])) {
        return 'Your current password is not correct.';
    }
    if (strlen((string) $new) < CP_MIN_PASSWORD || $new !== $repeat) {
        return 'Choose a new password of at least ' . CP_MIN_PASSWORD . ' characters and type it twice.';
    }
    if (password_verify((string) $new, $acc['password_hash'])) {
        return 'Choose a password different from the current one.';
    }
    cp_exec('UPDATE ' . $auth['accounts'] . ' SET password_hash=?, must_change_password=0 WHERE account_id=?', 'si', [password_hash((string) $new, PASSWORD_DEFAULT), (int) $accountId])->close();
    session_regenerate_id(true);
    return '';
}
