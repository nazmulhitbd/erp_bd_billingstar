<?php
ob_start();
require_once dirname(__DIR__, 2) . "/addon/conf/dbconnect.php";
require_once dirname(__DIR__, 2) . "/addon/conf/client_theme.php";
require_once dirname(__DIR__, 2) . "/addon/conf/configuration.php";

$busAdminProjectRoot = realpath(dirname(__DIR__, 2));
$busAdminDocumentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
$busAdminBaseUrl = '';
if ($busAdminProjectRoot && $busAdminDocumentRoot
        && str_starts_with($busAdminProjectRoot, $busAdminDocumentRoot . DIRECTORY_SEPARATOR)) {
            $busAdminBaseUrl = str_replace(DIRECTORY_SEPARATOR, '/', substr($busAdminProjectRoot, strlen($busAdminDocumentRoot)));
        }
$busAdminLoginUrl = rtrim($busAdminBaseUrl, '/') . '/index.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$idle_minutes = max(10, (int) ($session_idle_time ?? 30));
$admin_cookie = (string) ($_COOKIE['admin_sesid'] ?? '');
if ($admin_cookie === '') {
    header('Location: ' . $busAdminLoginUrl, true, 302);
    exit;
}

$admin_cookie_sql = mysql_real_escape_string($admin_cookie);
$admin_result = mysql_query("SELECT user_id, user_name, level, lastaccess FROM admin_user WHERE sesid='$admin_cookie_sql' LIMIT 1");
$admin = $admin_result ? mysql_fetch_assoc($admin_result) : false;
if (!$admin || (int) $admin['level'] < 0 || (int) $admin['lastaccess'] + ($idle_minutes * 60) < time()) {
    header('Location: ' . $busAdminLoginUrl, true, 302);
    exit;
}

$GLOBALS['user_id'] = (int) $admin['user_id'];
$GLOBALS['user_name'] = (string) $admin['user_name'];
$GLOBALS['level'] = (int) $admin['level'];
mysql_query('UPDATE admin_user SET lastaccess=' . time() . ' WHERE user_id=' . (int) $admin['user_id']);
chkauthenticate('bus administration');
require_once dirname(__DIR__, 2) . '/addon/inc/perm.php';
perm_guard();

if (empty($_SESSION['bus_admin_csrf'])) {
    $_SESSION['bus_admin_csrf'] = bin2hex(random_bytes(32));
}

function bus_admin_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bus_admin_sql($value)
{
    return mysql_real_escape_string((string) $value);
}

function bus_admin_csrf_valid()
{
    return !empty($_SESSION['bus_admin_csrf'])
        && hash_equals($_SESSION['bus_admin_csrf'], (string) ($_POST['csrf_token'] ?? ''));
}

function bus_admin_flash_redirect($url, $kind, $message)
{
    $_SESSION['bus_admin_flash'] = array('kind' => $kind, 'message' => $message);
    header('Location: ' . $url, true, 303);
    exit;
}

function bus_admin_take_flash()
{
    $flash = $_SESSION['bus_admin_flash'] ?? null;
    unset($_SESSION['bus_admin_flash']);
    return is_array($flash) ? $flash : null;
}

function bus_admin_valid_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

function bus_admin_valid_datetime($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
    return $date && $date->format('Y-m-d\TH:i') === $value
        ? $date->format('Y-m-d H:i:s')
        : false;
}

function bus_admin_audit($action, $entity, $entity_id, $details = array())
{
    $details_json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($details_json === false) {
        return false;
    }
    $actor_id = (int) ($GLOBALS['user_id'] ?? 0);
    $actor_value = $actor_id > 0 ? (string) $actor_id : 'NULL';
    $action_sql = bus_admin_sql($action);
    $entity_sql = bus_admin_sql($entity);
    $details_sql = bus_admin_sql($details_json);
    $ip_sql = bus_admin_sql(substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
    $entity_value = (int) $entity_id > 0 ? (string) (int) $entity_id : 'NULL';
    return mysql_query("INSERT INTO bus_admin_audit (actor_user_id, action_code, entity_type, entity_id, details_json, ip_address)
        VALUES ($actor_value, '$action_sql', '$entity_sql', $entity_value, '$details_sql', '$ip_sql')");
}
