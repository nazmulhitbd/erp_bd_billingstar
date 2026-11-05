<?php
/**
 * Service licence from Billing CRM. The panel signs what this install may do (dates, plan, modules); this file checks the
 * signature with the panel's public key, keeps the answer in licence_state, and applies it. A site installed before
 * activation existed has no row there and stays open; one activated by the installer locks if its row is removed. If the panel cannot be reached the last answer keeps working
 * until its own expiry date, so a network fault never locks anyone out.
 * Connected from Settings > Service Licence (billing/user/licence.php).
 * The check runs from cron (cron/licence.php, every minute). Pages only check as a fallback, less often, for hosts without cron.
 */
require_once __DIR__ . '/perm.php';

const LICENCE_RECHECK = 60;       // cron
const LICENCE_PAGE_RECHECK = 300; // page loads, when the licence is fine and cron has not checked lately
const LICENCE_RETRY = 300;
const LICENCE_LOCKED_RETRY = 60;
const LICENCE_WARN_DAYS = 15;
const LICENCE_TIMEOUT = 3;
const LICENCE_CORE = ['dashboard', 'settings'];
const LICENCE_PORTAL_MODULES = ['customer_portal', 'sr_portal'];

function licence_row($fresh = false)
{
    static $row = false;
    if ($row === false || $fresh) {
        $row = perm_q('SELECT * FROM licence_state WHERE id=1')[0] ?? null;
    }
    return $row;
}

/** The panel's answer, or null if it is not one the panel signed. */
function licence_verify($payload, $signature, $publicKey)
{
    if ((string) $payload === '' || @openssl_verify($payload, (string) base64_decode((string) $signature), (string) $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        return null;
    }
    return json_decode($payload, true) ?: null;
}

/** POST with curl where there is one, because many hosts turn off allow_url_fopen. Returns the body or false. */
function licence_post($url, $fields)
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => LICENCE_TIMEOUT, CURLOPT_CONNECTTIMEOUT => LICENCE_TIMEOUT]);
        return curl_exec($curl);
    }
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => LICENCE_TIMEOUT, 'ignore_errors' => true, 'content' => $fields]]);
    return @file_get_contents($url, false, $context);
}

/** Ask the panel. Returns [payload, signature, error]; the answer has been verified and is an answer to this request. */
function licence_fetch($url, $code, $key, $publicKey)
{
    $nonce = bin2hex(random_bytes(16));
    $fields = http_build_query(['service_code' => $code, 'service_key' => $key, 'nonce' => $nonce, 'host' => licence_host(), 'version' => 'billing_star']);
    $body = licence_post(rtrim($url, '/') . '/api/provision.php', $fields);
    if ($body === false) {
        return ['', '', 'The licence panel could not be reached.'];
    }
    $answer = json_decode($body, true);
    if (empty($answer['ok'])) {
        return ['', '', (string) ($answer['error'] ?? 'The licence panel gave an unexpected answer.')];
    }
    $data = licence_verify($answer['payload'] ?? '', $answer['signature'] ?? '', $publicKey);
    if (!$data || ($data['nonce'] ?? '') !== $nonce || (int) ($data['service_code'] ?? 0) !== (int) $code) {
        return ['', '', 'The answer from the licence panel failed its signature check.'];
    }
    return [$answer['payload'], $answer['signature'], ''];
}

/** A host name in a safe form, or ''. */
function licence_clean_host($host)
{
    $host = strtolower(trim((string) $host));
    return preg_match('/^[a-z0-9.\-]{1,190}(:\d{1,5})?$|^\[[0-9a-f:.]{2,60}\](:\d{1,5})?$/', $host) ? $host : '';
}

/**
 * The name this install reports to the panel. It is saved when someone trusted is at the keyboard (the installer, or the SuperAdmin on
 * Service Licence), never taken from whoever opens a page, because any visitor or bot can send any Host header.
 */
function licence_host()
{
    static $host = null;
    return $host ??= (string) (perm_q("SELECT setting_value FROM acc_settings WHERE setting_key='licence_host'")[0]['setting_value'] ?? '');
}

/** Save this request's host as the install's own. Only for requests from the installer or a signed-in SuperAdmin. */
function licence_save_host()
{
    $host = licence_clean_host($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && $host !== licence_host()) {
        perm_q("INSERT INTO acc_settings (setting_key, setting_value) VALUES ('licence_host', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", 's', [$host]);
    }
}

/** Take the check for this request: true only for the one request whose update moves attempted_at on, so two pages at once do not both call the panel. */
function licence_claim($seen)
{
    $db = perm_db();
    try {
        $stmt = $db ? $db->prepare('UPDATE licence_state SET attempted_at=? WHERE id=1 AND attempted_at=?') : null;
        if (!$stmt) {
            return false;
        }
        $now = time();
        $seen = (int) $seen;
        $stmt->bind_param('ii', $now, $seen);
        $ok = $stmt->execute() && $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('licence: ' . $e->getMessage());
        return false;
    }
}

/** Ask the panel now. $claimed: the caller already took the check with licence_claim(). */
function licence_refresh($claimed = false)
{
    $row = licence_row();
    if (!$row) {
        return;
    }
    $before = licence_status();
    if (!$claimed) {
        perm_q('UPDATE licence_state SET attempted_at=? WHERE id=1', 'i', [time()]);
    }
    [$payload, $signature, $error] = licence_fetch($row['crm_url'], (int) $row['service_code'], $row['service_key'], $row['public_key']);
    if ($error === '') {
        perm_q("UPDATE licence_state SET payload=?, signature=?, checked_at=?, last_error='' WHERE id=1", 'ssi', [$payload, $signature, time()]);
    } else {
        perm_q('UPDATE licence_state SET last_error=? WHERE id=1', 's', [mb_substr($error, 0, 255)]);
    }
    licence_row(true);
    $after = licence_status(true);
    // licence_state itself is not audited (it changes every minute); only a change the users will notice is recorded, as the system's.
    if ($after['state'] !== $before['state'] && function_exists('audit_event')) {
        audit_event('activity', 'licence', $after['locked'] ? 'warn' : 'info', [
            'actor' => ['system', 0, 'Licence check', 0],
            'target_table' => 'licence_state',
            'target_id' => '1',
            'summary' => 'Service licence ' . $before['state'] . ' → ' . $after['state'] . ($after['message'] !== '' ? ': ' . $after['message'] : ''),
            'detail' => ['from' => $before['state'], 'to' => $after['state'], 'expiry_date' => $after['data']['expiry_date'] ?? null, 'error' => $error],
        ]);
    }
}

/**
 * Check with the panel when due. Cron checks every minute, so a change in the panel applies by itself; a page only checks when cron has
 * not for a while. A locked install checks every minute, so a renewal opens it quickly. After a failure it waits longer, so a panel
 * that is down does not slow every page.
 */
function licence_tick($cron = false)
{
    $row = licence_row();
    if (!$row) {
        return;
    }
    $gap = licence_status()['locked'] ? LICENCE_LOCKED_RETRY : ((string) $row['last_error'] !== '' ? LICENCE_RETRY : ($cron ? LICENCE_RECHECK : LICENCE_PAGE_RECHECK));
    if (time() - (int) $row['attempted_at'] >= $gap - ($cron ? 5 : 0) && licence_claim($row['attempted_at'])) { // cron: a few seconds' slack, as its minutes are not exact
        licence_refresh(true);
    }
}

/** state: none, not_activated, ok, warn, grace, expired, suspended, cancelled, wrong_server or invalid. */
function licence_status($fresh = false)
{
    static $status = null;
    if ($status !== null && !$fresh) {
        return $status;
    }
    $row = licence_row();
    if (!$row) {
        if (perm_q("SELECT 1 AS x FROM acc_settings WHERE setting_key='licence_required' AND setting_value='1'")) {
            return $status = ['state' => 'not_activated', 'locked' => true, 'message' => 'This system is not activated.', 'days' => null, 'data' => null];
        }
        return $status = ['state' => 'none', 'locked' => false, 'message' => '', 'days' => null, 'data' => null];
    }
    $data = licence_verify($row['payload'], $row['signature'], $row['public_key']);
    if (!$data) {
        return $status = ['state' => 'invalid', 'locked' => true, 'message' => 'The service licence could not be verified.', 'days' => null, 'data' => null];
    }
    $today = max(date('Y-m-d'), (string) ($data['today'] ?? '')); // setting the server clock back does not buy time
    $days = (int) round((strtotime($data['expiry_date']) - strtotime($today)) / 86400);
    $support = trim((string) ($data['support'] ?? ''));
    $stop = fn($state, $text) => ['state' => $state, 'locked' => true, 'message' => trim($text . ' ' . $support), 'days' => $days, 'data' => $data];
    $serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? (PHP_SAPI === 'cli-server' ? '127.0.0.1' : ''));
    if ($data['status'] === 'Suspended') {
        return $status = $stop('suspended', 'This service is suspended.');
    }
    if ($data['status'] === 'Cancelled') {
        return $status = $stop('cancelled', 'This service has been cancelled.');
    }
    if ($data['ips'] && !in_array($serverAddr, $data['ips'], true)) {
        return $status = $stop('wrong_server', 'This service is not licensed for this server.');
    }
    if ($days < -(int) $data['grace_days']) {
        return $status = $stop('expired', 'This service expired on ' . $data['expiry_date'] . '.');
    }
    $state = $days < 0 ? 'grace' : ($days <= LICENCE_WARN_DAYS ? 'warn' : 'ok');
    $message = $days < 0 ? 'This service expired on ' . $data['expiry_date'] . '. It locks in ' . ($days + (int) $data['grace_days'] + 1) . ' day(s).' : ($state === 'warn' ? 'This service expires on ' . $data['expiry_date'] . ' (' . $days . ' days left).' : '');
    return $status = ['state' => $state, 'locked' => false, 'message' => $message !== '' ? trim($message . ' ' . $support) : '', 'days' => $days, 'data' => $data];
}

function licence_slug($label)
{
    return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
}

/** Whether this install may use a module. A code the panel has never heard of stays on, so a new menu is not lost by mistake. */
function licence_module_on($code)
{
    $data = licence_status()['data'];
    if (!$data || in_array($code, LICENCE_CORE, true) || !in_array($code, $data['all_modules'] ?? [], true)) {
        return true;
    }
    return in_array($code, $data['modules'] ?? [], true);
}

/** Module codes owning each page and, for helper scripts that are not in the menu, each folder. */
function licence_owners()
{
    static $owners = null;
    if ($owners !== null) {
        return $owners;
    }
    require_once dirname(__DIR__, 2) . '/billing/menu_catalog.php';
    $owners = ['pages' => [], 'dirs' => []];
    $add = function ($url, $code) use (&$owners) {
        $path = strtok((string) $url, '?');
        $owners['pages'][$path][$code] = true;
        $owners['dirs'][dirname($path)][$code] = true;
    };
    foreach (menu_catalog() as $cat) {
        $code = licence_slug($cat['label']);
        if (isset($cat['url'])) {
            $add($cat['url'], $code);
        }
        foreach ($cat['groups'] ?? [] as $group) {
            foreach ($group['items'] as $item) {
                $add($item[1], $code);
            }
        }
    }
    return $owners;
}

/** A page stays open if any module that owns it is on. */
function licence_page_on($script)
{
    $owners = licence_owners();
    $codes = array_keys($owners['pages'][$script] ?? $owners['dirs'][dirname($script)] ?? []);
    foreach ($codes as $code) {
        if (licence_module_on($code)) {
            return true;
        }
    }
    return !$codes;
}

/** From the running script back up to the project folder, so the lock screen finds its files at any depth. */
function licence_root_path()
{
    $root = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
    $file = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    return str_repeat('../', str_starts_with($file, $root) ? substr_count(substr($file, strlen($root)), '/') : 0);
}

/** The lock screen: one picture for every lock (expired, suspended, cancelled, wrong server), with the true reason in words below. */
function licence_stop($message, $links = '')
{
    http_response_code(403);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Cache-Control: no-store');
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '') {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => $message]));
    }
    $root = licence_root_path();
    exit('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service Expired</title><link rel="stylesheet" href="' . $root . 'addon/licence/locked.css"></head><body><main class="licence-lock"><h1>Service Expired</h1><img src="' . $root . 'addon/licence/expired.png" alt="Service Expired"><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' . $links . '</main></body></html>');
}

/**
 * Sign-in pages call this: a locked install shows the lock screen and not a form. The page rechecks with the panel, so a renewal
 * opens it again by itself. With $adminLink, index.php?admin=1 still offers the form, so the SuperAdmin can reach Service Licence.
 */
function licence_splash($adminLink = false)
{
    if (licence_status()['state'] === 'none') {
        return;
    }
    licence_tick();
    $status = licence_status();
    if ($status['locked'] && $_SERVER['REQUEST_METHOD'] === 'GET' && !($adminLink && isset($_GET['admin']))) {
        licence_stop($status['message'], $adminLink ? '<p class="licence-links"><a href="index.php?admin=1">Administrator sign in</a></p>' : '');
    }
}

/** Called by perm_guard on every admin page. A SuperAdmin can still reach the licence page, to check again or reconnect. */
function licence_guard($script)
{
    if (licence_status()['state'] === 'none') {
        return;
    }
    licence_tick();
    $status = licence_status();
    $up = str_repeat('../', substr_count($script, '/'));
    if ($status['locked']) {
        if ($script === 'logout.php' || ($script === 'user/licence.php' && perm_is_super())) {
            return;
        }
        licence_stop($status['message'], '<p class="licence-links">' . (perm_is_super() ? '<a href="' . $up . 'user/licence.php" target="_top">Service Licence</a> · ' : '') . '<a href="' . $up . 'logout.php" target="_top">Sign out</a></p>');
    }
    if (!licence_page_on($script)) {
        perm_deny('this module is not part of your service.');
    }
}

/** Called when a portal page opens. */
function licence_portal_guard($module)
{
    if (licence_status()['state'] === 'none') {
        return;
    }
    licence_tick();
    $status = licence_status();
    if ($status['locked']) {
        licence_stop($status['message']);
    }
    if (!licence_module_on($module)) {
        licence_stop('This portal is not part of your service.');
    }
}
