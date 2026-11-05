<?php
/**
 * The audit trail. Pages need no audit code of their own: the database helpers (sales_exec, cp_exec, perm_q, hr_exec, rma_exec,
 * mysql_query) report every write here, and security.php and the sign-in code report everything else, so a future page or
 * portal built on those helpers is audited by itself. Nothing here may ever stop a page: every fault is swallowed.
 *
 * A write is recorded BEFORE it runs, on the same connection. Recording after would overwrite $db->insert_id, which pages read
 * straight after a save. Being in the same transaction also means a rolled back save leaves no record of a change that never
 * happened. Screens: billing/audit. Tables: billing/audit/schema.sql.
 */
const AUDIT_CATEGORIES = ['login' => 'Logins', 'change' => 'Data Changes', 'activity' => 'Activity', 'security' => 'Security'];
const AUDIT_ACTORS = ['staff' => 'Staff', 'customer' => 'Customer', 'employee' => 'Employee / SR', 'store' => 'Store', 'anonymous' => 'Not signed in', 'system' => 'System'];
const AUDIT_DEFAULTS = ['geo_on' => '1', 'geo_url' => 'https://api.country.is/{ip}', 'retention_days' => '730', 'page_views' => '0', 'trust_proxy' => '0', 'autoblock' => '1', 'autoblock_hits' => '10'];
/** Columns whose values are never recorded, only that they changed. */
const AUDIT_SECRET = '/pass|pwd|secret|token|hash|sesid|otp|api_?key|private|csrf|signature/i';
/** Tables that are logs or counters themselves. licence_state is rewritten by the automatic check every minute; licence.php records a change of state instead. */
const AUDIT_SKIP_TABLES = ['audit_log', 'audit_ip_country', 'audit_blocked_ip', 'audit_settings', 'security_throttle', 'login_log', 'portal_login_log', 'sr_portal_login_log', 'app_notifications', 'app_notification_reads', 'admin_doc_prints', 'licence_state'];
/** Columns that change on their own every session. A write touching only these is not a data change. */
const AUDIT_NOISE = ['lastaccess', 'sesid', 'current_branch_id', 'last_login_at', 'last_login_ip', 'failed_attempts', 'locked_until', 'module', 'customize3', 'customize4', 'customize5', 'customize6'];
const AUDIT_MAX_WRITES = 300;     // writes recorded in one request, so one big import cannot flood the log
const AUDIT_MAX_DETAIL = 20000;
const AUDIT_ANON_LIMIT = 300;     // events an hour from one address with nobody signed in, so an attacker cannot fill the table
const AUDIT_WATCHED = ['admin_user', 'admin_role', 'admin_user_permissions', 'admin_user_branches', 'audit_settings'];
const AUDIT_SIGNATURES = [
    'SQL injection' => '/\bunion\b[\s\/*]+(all[\s\/*]+)?select\b|\b(sleep|benchmark|pg_sleep|extractvalue|updatexml|load_file)\s*\(|\binformation_schema\b|[\'"]\s*(or|and)\s+[\'"\d(][^=]{0,20}=|\bdrop\s+table\b|\binto\s+(out|dump)file\b|;\s*(delete|update|insert|drop|shutdown)\b/i',
    'Script injection' => '/<\s*(script|iframe|object|embed|svg)\b|\bon(error|load|click|mouseover|focus)\s*=|javascript\s*:|document\.cookie/i',
    'Path traversal' => '~\.\./\.\.|\.\.\\\\|/etc/(passwd|shadow)|/proc/self|c:\\\\windows|php://|expect://|file://~i',
    'Command injection' => '/[;|`]\s*(cat|ls|id|whoami|wget|curl|nc|bash|sh|python|perl)\b|\$\(\s*(cat|id|whoami|wget|curl)\b/i',
    'Scanner tool' => '/\b(sqlmap|nikto|nessus|acunetix|nmap|masscan|wpscan|dirbuster|gobuster|ffuf|nuclei|havij|burpsuite|zgrab)\b/i',
];

function audit_db()
{
    foreach (['conn', 'link', 'cp_db'] as $name) {
        if (($GLOBALS[$name] ?? null) instanceof mysqli) {
            return $GLOBALS[$name];
        }
    }
    return null;
}

/** Run a query that must never disturb the page, whichever error mode the connection is in. */
function audit_query(mysqli $db, $sql, $types = '', array $params = [])
{
    $busy = $GLOBALS['audit_busy'] ?? false;
    $GLOBALS['audit_busy'] = true;
    $rows = null;
    try {
        if ($stmt = $db->prepare($sql)) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $rows = null;
    }
    $GLOBALS['audit_busy'] = $busy;
    return $rows;
}

/** Start auditing this request, making the tables the first time so an existing system needs no manual update. */
function audit_boot()
{
    if (isset($GLOBALS['audit_on'])) {
        return;
    }
    $GLOBALS['audit_on'] = false;
    $GLOBALS['audit_pending'] = [];
    $GLOBALS['audit_writes'] = 0;
    $db = audit_db();
    if (!$db) {
        return;
    }
    if (audit_query($db, 'SELECT 1 FROM audit_settings, security_throttle LIMIT 0') === null) { // here, never during a save: creating a table ends any open transaction
        foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', (string) preg_replace('/^--.*$/m', '', (string) file_get_contents(dirname(__DIR__, 2) . '/billing/audit/schema.sql'))))) as $sql) {
            audit_query($db, $sql);
        }
        if (audit_query($db, 'SELECT 1 FROM audit_settings, security_throttle LIMIT 0') === null) {
            return;
        }
    }
    $GLOBALS['audit_on'] = true;
    $GLOBALS['audit_request'] = bin2hex(random_bytes(8));
    register_shutdown_function('audit_finish');
}

function audit_setting($key)
{
    static $saved = null;
    if ($saved === null) {
        $saved = [];
        $db = audit_db();
        foreach (($db ? audit_query($db, 'SELECT setting_key, setting_value FROM audit_settings') : null) ?? [] as $row) {
            $saved[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $saved[$key] ?? AUDIT_DEFAULTS[$key] ?? '';
}

/** The visitor's address. Only behind a known proxy may a forwarded header be believed, as anyone can send one. */
function audit_ip()
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (audit_setting('trust_proxy') === '1') {
        $forwarded = trim(explode(',', (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
            $ip = $forwarded;
        }
    }
    return substr($ip, 0, 45);
}

function audit_public_ip($ip)
{
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/** The country already known for an address, 'LAN' for an office network, or '' until the end-of-request lookup fills it in. */
function audit_country($ip)
{
    static $known = [];
    if (!isset($known[$ip])) {
        if (!audit_public_ip($ip)) {
            $known[$ip] = filter_var($ip, FILTER_VALIDATE_IP) ? 'LAN' : '';
        } elseif (audit_setting('trust_proxy') === '1' && preg_match('/^[A-Z]{2}$/', (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''))) {
            $known[$ip] = $_SERVER['HTTP_CF_IPCOUNTRY'];
        } else {
            $db = audit_db();
            $known[$ip] = (string) ($db ? (audit_query($db, 'SELECT country FROM audit_ip_country WHERE ip=?', 's', [$ip])[0]['country'] ?? '') : '');
        }
    }
    return $known[$ip];
}

/** [browser, os, device] from a browser's description. */
function audit_agent($agent)
{
    $browser = 'Other';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'curl' => 'curl', 'python' => 'Python', 'Wget' => 'Wget'] as $mark => $name) {
        if (stripos($agent, $mark) !== false) {
            $browser = $name;
            break;
        }
    }
    $os = 'Other';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $mark => $name) {
        if (stripos($agent, $mark) !== false) {
            $os = $name;
            break;
        }
    }
    $device = preg_match('/iPad|Tablet/i', $agent) ? 'Tablet' : (preg_match('/Mobile|Android|iPhone/i', $agent) ? 'Mobile' : 'Desktop');
    return [$browser, $os, $device];
}

/** Who is acting: [type, id, name, branch]. A sign-in names the person itself, as no session exists yet. */
function audit_actor()
{
    if (isset($GLOBALS['audit_actor'])) {
        return $GLOBALS['audit_actor'];
    }
    if (PHP_SAPI === 'cli') {
        return ['system', 0, 'Scheduled job', 0];
    }
    // Read here, not through perm_user(): attacks are caught before the permission code has loaded. A cookie alone proves nothing.
    $sesid = bs_session_id($_COOKIE['admin_sesid'] ?? '');
    $u = $sesid !== '' && ($db = audit_db()) ? (audit_query($db, 'SELECT user_id, user_name, user_type, current_branch_id FROM admin_user WHERE sesid=? LIMIT 1', 's', [$sesid])[0] ?? null) : null;
    if ($u) {
        return [isset(AUDIT_ACTORS[$u['user_type']]) ? $u['user_type'] : 'staff', (int) $u['user_id'], (string) $u['user_name'], (int) $u['current_branch_id']];
    }
    foreach (['cp_account' => ['customer', 'portal_accounts'], 'sr_account' => ['employee', 'sr_portal_accounts']] as $key => [$type, $table]) {
        if (!empty($_SESSION[$key]) && ($db = audit_db())) {
            $id = (int) $_SESSION[$key];
            return [$type, $id, (string) (audit_query($db, "SELECT login FROM $table WHERE account_id=?", 'i', [$id])[0]['login'] ?? ''), 0];
        }
    }
    return ['anonymous', 0, '', 0];
}

/** Record one event. $f: summary, detail (array), status, target_table, target_id, actor. Returns the row id, or 0. */
function audit_event($category, $action, $severity = 'info', array $f = [])
{
    if (empty($GLOBALS['audit_on']) || !($db = audit_db())) {
        return 0;
    }
    try {
        [$type, $id, $name, $branch] = $f['actor'] ?? audit_actor();
        $ip = audit_ip();
        if ($type === 'anonymous') { // limited, or an attacker could fill the table simply by failing
            $bucket = "audit-ip:$ip";
            if (bs_limit_reached($bucket, AUDIT_ANON_LIMIT)) {
                return 0;
            }
            bs_limit_hit($bucket, 3600);
        }
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        [$browser, $os, $device] = audit_agent($agent);
        $sid = (string) ($_COOKIE['admin_sesid'] ?? (session_status() === PHP_SESSION_ACTIVE ? session_id() : ''));
        $detail = isset($f['detail']) ? substr((string) json_encode($f['detail'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), 0, AUDIT_MAX_DETAIL) : null;
        $page = substr((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), 0, 190);
        $country = audit_country($ip);
        audit_query($db, 'INSERT INTO audit_log (category, action, severity, status, actor_type, actor_id, actor_name, branch_id, ip, country, browser, os, device, user_agent, method, page, target_table, target_id, summary, detail, session_ref, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssssisissssssssssssss',
            [$category, substr($action, 0, 40), $severity, $f['status'] ?? 'ok', $type, $id, substr($name, 0, 120), $branch, $ip, $country, $browser, $os, $device, $agent, substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 8), $page, substr((string) ($f['target_table'] ?? ''), 0, 64), substr((string) ($f['target_id'] ?? ''), 0, 40), mb_substr((string) ($f['summary'] ?? ''), 0, 255), $detail, $sid === '' ? '' : substr(hash('sha256', $sid), 0, 12), $GLOBALS['audit_request'] ?? '']);
        $GLOBALS['audit_events'] = true;
        return (int) $db->insert_id;
    } catch (Throwable $e) {
        error_log('audit: ' . $e->getMessage());
        return 0;
    }
}

/** One sign-in, staff or portal. A success from a country this person has not used before raises an alert. */
function audit_login($type, $id, $name, $ok, $reason = '')
{
    $actor = [$type, (int) $id, (string) $name, 0];
    if ($ok) {
        $GLOBALS['audit_actor'] = $actor;
    }
    $country = audit_country(audit_ip());
    $db = audit_db();
    $earlier = $ok && $db && $country !== '' && $country !== 'LAN' ? audit_query($db, "SELECT DISTINCT country FROM audit_log WHERE category='login' AND action='login_ok' AND actor_type=? AND actor_id=? AND country NOT IN ('', 'LAN') LIMIT 20", 'si', [$type, (int) $id]) : null;
    audit_event('login', $ok ? 'login_ok' : 'login_failed', $ok ? 'info' : 'warn', ['actor' => $actor, 'status' => $ok ? 'ok' : 'failed', 'summary' => ($ok ? 'Signed in: ' : 'Sign-in failed: ') . $name . ($reason !== '' ? " ($reason)" : '')]);
    if ($earlier && !in_array($country, array_column($earlier, 'country'), true)) {
        audit_event('security', 'new_country', 'warn', ['actor' => $actor, 'summary' => "$name signed in from a new country: $country"]);
        audit_alert("New country sign-in: $name", "From $country at " . audit_ip());
    }
}

function audit_logout()
{
    audit_event('login', 'logout', 'info', ['summary' => 'Signed out']);
}

/** Tell whoever holds Security Events, at most once in 15 minutes for each kind of alert. Sent at the end of the request. */
function audit_alert($title, $body = '')
{
    $bucket = 'audit-alert:' . preg_replace('/:.*/', '', $title);
    if (!bs_limit_reached($bucket, 1)) {
        bs_limit_hit($bucket, 900);
        $GLOBALS['audit_alerts'][] = [$title, $body];
    }
}

/** A hostile or refused request. */
function audit_security($action, $summary, array $detail = [], $severity = 'warn')
{
    $GLOBALS['audit_refused'] = $action !== 'attack_signature'; // the form it came with did nothing, so it is not activity too
    audit_event('security', $action, $severity, ['status' => 'blocked', 'summary' => $summary, 'detail' => $detail]);
    if ($severity === 'bad') {
        audit_alert('Security: ' . $summary, 'From ' . audit_ip());
    }
}

/** Look through a request for known attacks. Recording does not refuse it: a real product name can look odd. Repeat offenders are blocked. */
function audit_scan_request()
{
    $page = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (empty($GLOBALS['audit_on']) || str_contains($page, '/billing/audit/')) {
        return;
    }
    $fields = ['address' => rawurldecode((string) ($_SERVER['REQUEST_URI'] ?? '')), 'browser' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')];
    array_walk_recursive($_GET, function ($value, $key) use (&$fields) { $fields["get:$key"] = $value; });
    array_walk_recursive($_POST, function ($value, $key) use (&$fields) {
        if (!preg_match(AUDIT_SECRET, (string) $key)) {
            $fields["post:$key"] = $value;
        }
    });
    foreach ($fields as $field => $value) {
        foreach (AUDIT_SIGNATURES as $name => $pattern) {
            if (is_string($value) && $value !== '' && preg_match($pattern, $value)) {
                $signedIn = audit_actor()[0] !== 'anonymous'; // checked against the database, or a made-up cookie would dodge the block
                audit_security('attack_signature', "$name in $field", ['field' => $field, 'value' => mb_substr($value, 0, 300)], 'bad');
                if (!$signedIn && audit_setting('autoblock') === '1') {
                    $ip = audit_ip();
                    bs_limit_hit("attack:$ip", 3600);
                    if (bs_limit_reached("attack:$ip", (int) audit_setting('autoblock_hits'))) {
                        audit_block_ip($ip, 'Automatic: repeated attacks', date('Y-m-d H:i:s', time() + 86400), 'System');
                        audit_security('ip_blocked', "$ip blocked for 24 hours", [], 'bad');
                    }
                }
                return;
            }
        }
    }
}

function audit_block_ip($ip, $reason, $until, $by)
{
    if ($db = audit_db()) {
        audit_query($db, 'INSERT INTO audit_blocked_ip (ip, reason, blocked_until, created_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), blocked_until=VALUES(blocked_until), created_by=VALUES(created_by)', 'ssss', [$ip, mb_substr($reason, 0, 190), $until, mb_substr($by, 0, 120)]);
    }
}

function audit_ip_blocked($ip)
{
    $db = audit_db();
    return $db && !empty($GLOBALS['audit_on']) && audit_query($db, 'SELECT 1 AS x FROM audit_blocked_ip WHERE ip=? AND (blocked_until IS NULL OR blocked_until > NOW())', 's', [$ip]);
}

/** Split a list on its top-level commas, leaving those inside quotes and brackets alone. */
function audit_split($text)
{
    $parts = [];
    $part = '';
    $depth = 0;
    $quote = '';
    foreach (str_split($text) as $i => $c) {
        if ($quote !== '') {
            $quote = $c === $quote && ($text[$i - 1] ?? '') !== '\\' ? '' : $quote;
        } elseif ($c === "'" || $c === '"') {
            $quote = $c;
        } elseif ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            $parts[] = $part;
            $part = '';
            continue;
        }
        $part .= $c;
    }
    return [...$parts, $part];
}

/** What a SET or VALUES item comes to, taking parameters from $params in order. */
function audit_value($text, array &$params)
{
    $text = trim($text);
    if ($text === '?') {
        return array_shift($params);
    }
    array_splice($params, 0, substr_count($text, '?'));
    if (preg_match("/^'(.*)'$/s", $text, $m)) {
        return stripslashes($m[1]);
    }
    return is_numeric($text) ? $text : ['expression' => mb_substr($text, 0, 120)];
}

function audit_clean(array $row)
{
    foreach ($row as $column => $value) {
        if (preg_match(AUDIT_SECRET, (string) $column)) {
            $row[$column] = '***';
        } elseif (is_string($value) && strlen($value) > 500) {
            $row[$column] = mb_substr($value, 0, 500) . '...';
        }
    }
    return $row;
}

function audit_same($old, $new)
{
    if (is_array($new)) {
        return false;
    }
    return is_numeric($old) && is_numeric($new) ? (float) $old === (float) $new : (string) $old === (string) $new;
}

/**
 * Called by a database helper just before it runs a statement. Records what is about to change and returns a handle for
 * audit_sql_end(), or null when it is not a write worth recording. $params are the bound values, $types their letters.
 */
function audit_sql($db, $sql, $types = '', array $params = [])
{
    if (empty($GLOBALS['audit_on']) || !empty($GLOBALS['audit_busy']) || !($db instanceof mysqli)
        || !preg_match('/^\s*(INSERT(?:\s+IGNORE)?(?:\s+INTO)?|REPLACE(?:\s+INTO)?|UPDATE|DELETE\s+FROM)\s+`?(\w+)`?/i', (string) $sql, $m)) {
        return null;
    }
    try {
        $table = strtolower($m[2]);
        if (in_array($table, AUDIT_SKIP_TABLES, true)) {
            return null;
        }
        if (++$GLOBALS['audit_writes'] > AUDIT_MAX_WRITES) {
            return null;
        }
        $verb = strtoupper($m[1][0]);
        $detail = [];
        $target = '';
        $summary = '';
        $action = $verb === 'D' ? 'delete' : ($verb === 'U' ? 'update' : 'insert');
        if ($verb === 'U' && preg_match('/^\s*UPDATE\s+`?\w+`?\s+SET\s+(.+?)\s+WHERE\s+(.+)$/is', $sql, $u)) {
            $rest = $params;
            $new = [];
            foreach (audit_split($u[1]) as $item) {
                $pair = explode('=', $item, 2);
                $new[trim($pair[0], " `\n\t")] = audit_value($pair[1] ?? '', $rest);
            }
            $old = audit_before($db, $table, $u[2], $types, $rest, array_slice($params, 0, count($params) - count($rest)));
            if ($old === []) {
                return null; // no row will change
            }
            $changes = [];
            foreach ($old ?? [[]] as $row) {
                foreach ($new as $column => $value) {
                    if ($old !== null && !array_key_exists($column, $row)) {
                        continue;
                    }
                    if (!in_array($column, AUDIT_NOISE, true) && ($old === null || !audit_same($row[$column], $value))) {
                        $changes[$column] = preg_match(AUDIT_SECRET, $column) ? ['***', '***'] : [$old === null ? null : $row[$column], $value];
                    }
                }
            }
            if (!$changes) {
                return null; // saved with nothing different, or only session bookkeeping
            }
            $detail = ['changes' => $changes, 'rows' => $old === null ? null : count($old)];
            $target = $old ? (string) reset($old[0]) : '';
            $summary = "Updated $table" . ($target !== '' ? " #$target" : '') . ': ' . implode(', ', array_keys($changes));
        } elseif ($verb === 'D' && preg_match('/^\s*DELETE\s+FROM\s+`?\w+`?\s+WHERE\s+(.+)$/is', $sql, $d)) {
            $old = audit_before($db, $table, $d[1], $types, $params, []);
            if ($old === []) {
                return null;
            }
            $detail = ['deleted' => array_map('audit_clean', array_slice($old ?? [], 0, 5)), 'rows' => $old === null ? null : count($old)];
            $target = $old ? (string) reset($old[0]) : '';
            $summary = "Deleted from $table" . ($target !== '' ? " #$target" : '');
        } elseif ($verb !== 'U' && $verb !== 'D' && preg_match('/\(([^()]+)\)\s*VALUES\s*\((.*?)\)\s*(ON\s+DUPLICATE.*)?$/is', $sql, $i) && count($cols = audit_split($i[1])) === count($vals = audit_split($i[2]))) {
            $rest = $params;
            $row = [];
            foreach ($cols as $n => $column) {
                $row[trim($column, " `\n\t")] = audit_value($vals[$n], $rest);
            }
            $detail = ['added' => audit_clean($row)];
            $summary = "Added to $table";
        } else {
            $detail = ['statement' => mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 300)]; // joins and bulk writes: what ran, not row by row
            $summary = ucfirst($action) . " on $table";
        }
        $severity = in_array($table, AUDIT_WATCHED, true) || $action === 'delete' ? 'warn' : 'info';
        $id = audit_event('change', $action, $severity, ['target_table' => $table, 'target_id' => $target, 'summary' => $summary, 'detail' => $detail]);
        return $id ? [$id, $target === ''] : null;
    } catch (Throwable $e) {
        error_log('audit: ' . $e->getMessage());
        return null;
    }
}

/** The rows a WHERE matches now, at most 20. [] means none, null means they could not be read. */
function audit_before(mysqli $db, $table, $where, $types, array $whereParams, array $setParams)
{
    $where = preg_replace('/\s+LIMIT\s+\d+\s*$/i', '', $where);
    return audit_query($db, "SELECT * FROM `$table` WHERE $where LIMIT 20", substr((string) $types, count($setParams)), $whereParams);
}

/** Called by the helper once the statement has run. Touches nothing on the connection until the end of the request. */
function audit_sql_end($handle, $ok, $insertId = 0)
{
    if ($handle) {
        $GLOBALS['audit_pending'][$handle[0]] = [$ok, $handle[1] ? (int) $insertId : 0];
    }
}

/** Finish the record of this request, once. Called by connection_close.php, which shuts the database, or at shutdown. */
function audit_finish()
{
    if (!empty($GLOBALS['audit_finished']) || empty($GLOBALS['audit_on'])) {
        return;
    }
    $GLOBALS['audit_finished'] = true;
    try {
        $db = audit_db();
        if (!$db) {
            return;
        }
        foreach ($GLOBALS['audit_pending'] as $id => [$ok, $insertId]) {
            if (!$ok) {
                audit_query($db, "UPDATE audit_log SET status='failed' WHERE audit_id=?", 'i', [$id]);
            } elseif ($insertId > 0) {
                audit_query($db, "UPDATE audit_log SET target_id=?, summary=CONCAT(summary, ' #', ?) WHERE audit_id=?", 'iii', [$insertId, $insertId, $id]);
            }
        }
        audit_record_request();
        foreach ($GLOBALS['audit_alerts'] ?? [] as [$title, $body]) {
            if (!function_exists('notify_admin')) {
                require_once __DIR__ . '/perm.php';
            }
            notify_admin('audit/audit_log.php?tab=security', $title, $body);
        }
        if (!empty($GLOBALS['audit_events'])) {
            audit_fill_country($db);
        }
        if (audit_setting('purged_licence_state') !== '1') { // once: the minute-by-minute licence checks recorded before licence_state was skipped
            if (audit_query($db, "DELETE FROM audit_log WHERE category='change' AND target_table='licence_state'") !== null) {
                audit_query($db, "REPLACE INTO audit_settings (setting_key, setting_value) VALUES ('purged_licence_state', '1')");
            }
        }
        if (random_int(1, 500) === 1) {
            $days = (int) audit_setting('retention_days');
            if ($days > 0) {
                audit_query($db, 'DELETE FROM audit_log WHERE created_at < NOW() - INTERVAL ? DAY LIMIT 5000', 'i', [$days]);
            }
            audit_query($db, 'DELETE FROM audit_blocked_ip WHERE blocked_until IS NOT NULL AND blocked_until < NOW()');
        }
    } catch (Throwable $e) {
        error_log('audit: ' . $e->getMessage());
    }
}

/** A form that was sent, with every password taken out, and page views if Audit Settings asks for them. */
function audit_record_request()
{
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (PHP_SAPI === 'cli' || $page === 'notifications_feed.php' || !empty($GLOBALS['audit_refused'])) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isset($_POST['okButton'])) {
        $fields = [];
        foreach ($_POST as $key => $value) {
            $fields[$key] = preg_match(AUDIT_SECRET, (string) $key) ? '***' : (is_array($value) ? '(' . count($value) . ' items)' : mb_substr((string) $value, 0, 200));
        }
        audit_event('activity', 'form_post', 'info', ['summary' => "Form sent: $page" . (isset($_POST['action']) && is_string($_POST['action']) ? ' (' . mb_substr($_POST['action'], 0, 40) . ')' : ''), 'detail' => ['fields' => $fields, 'files' => array_column($_FILES, 'name')]]);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && audit_setting('page_views') === '1' && audit_actor()[0] !== 'anonymous') {
        audit_event('activity', 'page_view', 'info', ['summary' => "Opened $page"]);
    }
}

/** Find the country of an address not seen before, after the page is done, and fill it into the rows waiting for it. */
function audit_fill_country(mysqli $db)
{
    $ip = audit_ip();
    if (audit_setting('geo_on') !== '1' || !audit_public_ip($ip) || audit_country($ip) !== '' || !function_exists('curl_init')) {
        return;
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    $curl = curl_init(str_replace('{ip}', rawurlencode($ip), audit_setting('geo_url')));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
    $reply = json_decode((string) curl_exec($curl), true);
    $country = strtoupper((string) ($reply['country'] ?? $reply['country_code'] ?? $reply['countryCode'] ?? ''));
    if (preg_match('/^[A-Z]{2}$/', $country)) {
        audit_query($db, 'INSERT INTO audit_ip_country (ip, country) VALUES (?, ?) ON DUPLICATE KEY UPDATE country=VALUES(country), checked_at=NOW()', 'ss', [$ip, $country]);
        audit_query($db, "UPDATE audit_log SET country=? WHERE ip=? AND country=''", 'ss', [$country, $ip]);
    }
}
