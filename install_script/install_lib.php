<?php
/**
 * Installer rules: what the server needs, the order the database is built in, and writing the configuration.
 * The installer only runs while addon/display/dbconfig.php does not exist; index.php checks that on every request.
 */
const INST_ROOT = __DIR__ . '/..';
const INST_CONFIG = INST_ROOT . '/addon/display/dbconfig.php';
const INST_MIN_PHP = '8.0.0';
const INST_MIN_MYSQL = 80000;
const INST_MIN_MARIADB = 100300;
/** Names only MySQL 8 knows, and what MariaDB calls them. cPanel servers usually run MariaDB. */
const INST_MARIADB_NAMES = ['utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci', 'utf8mb3_unicode_ci' => 'utf8_unicode_ci', 'utf8mb3_general_ci' => 'utf8_general_ci', 'utf8mb3' => 'utf8'];
/** The one SQL file: its sections run in order, are safe to run again, and add their own default rows. build_sql.php makes it. */
const INST_SQL = 'install_script/sql/install.sql';
/** Folders the application writes to. */
const INST_WRITABLE = ['addon/display', 'addon/uploads', 'billing/hr/uploads', 'billing/sales/uploads', 'billing/accounts_info/uploads'];

/** Everything the server must have: [label, passed, what to do, stops the install]. */
function inst_requirements()
{
    $checks = [
        ['PHP ' . INST_MIN_PHP . ' or newer (this server: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, INST_MIN_PHP, '>='), 'Upgrade PHP.', true],
    ];
    foreach (['mysqli' => 'MySQL', 'mbstring' => 'Multibyte text', 'fileinfo' => 'File type checks', 'json' => 'JSON', 'session' => 'Sessions'] as $ext => $label) {
        $checks[] = ['PHP ' . $ext . ' extension (' . $label . ')', extension_loaded($ext), 'Enable the ' . $ext . ' extension in PHP.', true];
    }
    $checks[] = ['PHP openssl extension (Billing CRM activation)', extension_loaded('openssl'), 'Enable the openssl extension in PHP.', true];
    $checks[] = ['PHP can call Billing CRM', function_exists('curl_init') || (bool) ini_get('allow_url_fopen'), 'Enable curl or allow_url_fopen in PHP.', true];
    $checks[] = ['PHP gd extension (images)', extension_loaded('gd'), 'Optional: enable gd in PHP.', false];
    if (!inst_sections()) {
        $checks[] = ['Schema file ' . INST_SQL, false, 'The project files are incomplete. Upload them again.', true];
    }
    foreach (INST_WRITABLE as $dir) {
        $path = INST_ROOT . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $checks[] = ['Writable folder ' . $dir, is_dir($path) && is_writable($path), 'You can still install. Run the command below to fix this.', false];
    }
    return $checks;
}

/** The folders the web server cannot write to yet. */
function inst_unwritable()
{
    return array_values(array_filter(INST_WRITABLE, fn($dir) => !is_writable(INST_ROOT . '/' . $dir)));
}

/** The Terminal commands that give the web server write access on this machine. */
function inst_permission_commands(array $dirs)
{
    $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : 'www-data';
    $group = function_exists('posix_getegid') ? (posix_getgrgid(posix_getegid())['name'] ?? $user) : $user;
    $list = implode(' ', $dirs);
    return 'cd ' . escapeshellarg((string) realpath(INST_ROOT)) . "\nsudo chgrp " . escapeshellarg($group) . ' ' . $list . "\nsudo chmod 775 " . $list;
}

/** Connect to a database server. Returns [mysqli or null, error for the person installing]. */
function inst_connect(array $db, $useDatabase = true)
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    if (!$conn || !@$conn->real_connect($db['host'], $db['user'], $db['pass'], $useDatabase ? $db['name'] : null, (int) $db['port'])) {
        $error = (string) mysqli_connect_error();
        if (str_contains($error, 'authentication method')) {
            $error = 'This PHP cannot use that user\'s password method. Create the user with mysql_native_password, or upgrade the PHP mysqli driver.';
        } elseif (str_contains($error, 'Access denied') && $db['host'] === '127.0.0.1') {
            $error .= ' On cPanel, set Database Host to localhost.';
        }
        return [null, $error !== '' ? $error : 'Could not connect.'];
    }
    $conn->set_charset('utf8mb4');
    return [$conn, ''];
}

/** POST to Billing CRM, with curl where the host has it. Returns the body or false. */
function inst_crm_post($url, array $fields)
{
    $body = http_build_query($fields);
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 10]);
        return curl_exec($curl);
    }
    return @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => 10, 'ignore_errors' => true, 'content' => $body]]));
}

/**
 * Activate this install from a Billing CRM Connection Code. Returns [licence, error]. The licence is kept in the session and
 * saved by inst_save_licence once the tables exist. A service the CRM has stopped cannot be installed.
 */
function inst_activate($text)
{
    $c = json_decode((string) base64_decode(preg_replace('/\s+/', '', (string) $text), true), true);
    if (!is_array($c) || empty($c['url']) || empty($c['code']) || empty($c['key']) || empty($c['public_key']) || !preg_match('~^https?://~', (string) $c['url'])) {
        return [null, 'That is not a Connection Code. Copy it from the service page in Billing CRM.'];
    }
    $nonce = bin2hex(random_bytes(16));
    $body = inst_crm_post(rtrim($c['url'], '/') . '/api/provision.php', ['service_code' => (int) $c['code'], 'service_key' => $c['key'], 'nonce' => $nonce, 'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''), 'version' => 'installer']);
    if ($body === false) {
        return [null, 'Billing CRM could not be reached at ' . $c['url'] . '.'];
    }
    $answer = json_decode($body, true);
    if (empty($answer['ok'])) {
        return [null, 'Billing CRM refused this code: ' . ($answer['error'] ?? 'unexpected answer') . ' Copy the current code from the service page.'];
    }
    $payload = (string) ($answer['payload'] ?? '');
    $data = @openssl_verify($payload, (string) base64_decode((string) ($answer['signature'] ?? '')), (string) $c['public_key'], OPENSSL_ALGO_SHA256) === 1 ? json_decode($payload, true) : null;
    if (!$data || ($data['nonce'] ?? '') !== $nonce || (int) ($data['service_code'] ?? 0) !== (int) $c['code']) {
        return [null, 'The answer from Billing CRM failed its signature check.'];
    }
    if ($data['status'] !== 'Active') {
        return [null, 'Service ' . $data['service_code'] . ' is ' . strtolower($data['status']) . ' in Billing CRM. Resume it first.'];
    }
    $today = max(date('Y-m-d'), (string) ($data['today'] ?? ''));
    if (date('Y-m-d', strtotime($data['expiry_date'] . ' +' . (int) $data['grace_days'] . ' days')) < $today) {
        return [null, 'Service ' . $data['service_code'] . ' expired on ' . $data['expiry_date'] . '. Extend it in Billing CRM first.'];
    }
    $server = (string) ($_SERVER['SERVER_ADDR'] ?? (PHP_SAPI === 'cli-server' ? '127.0.0.1' : ''));
    if (!empty($data['ips']) && !in_array($server, $data['ips'], true)) {
        return [null, 'Service ' . $data['service_code'] . ' is not licensed for this server (' . $server . '). Correct Server IP in Billing CRM.'];
    }
    return [['url' => rtrim($c['url'], '/'), 'code' => (int) $c['code'], 'key' => (string) $c['key'], 'public_key' => (string) $c['public_key'], 'payload' => $payload, 'signature' => (string) $answer['signature'], 'data' => $data], ''];
}

/** Store the activation in the new database. From here the site is under Billing CRM, and cannot run without a licence. */
function inst_save_licence(mysqli $conn, array $licence)
{
    inst_exec($conn, 'REPLACE INTO licence_state (id, crm_url, service_code, service_key, public_key, payload, signature, checked_at, attempted_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)', 'sissssii', [$licence['url'], $licence['code'], $licence['key'], $licence['public_key'], $licence['payload'], $licence['signature'], time(), time()]);
    inst_exec($conn, 'UPDATE client_theme SET service_code=? WHERE client_theme_id=1', 'i', [$licence['code']]);
    inst_exec($conn, "INSERT INTO acc_settings (setting_key, setting_value) VALUES ('licence_required', '1') ON DUPLICATE KEY UPDATE setting_value='1'");
    // The installer's own address is the site's: saved so the licence check never reports a Host header a visitor made up.
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (preg_match('/^[a-z0-9.\-]{1,190}(:\d{1,5})?$|^\[[0-9a-f:.]{2,60}\](:\d{1,5})?$/', $host)) {
        inst_exec($conn, "INSERT INTO acc_settings (setting_key, setting_value) VALUES ('licence_host', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", 's', [$host]);
    }
}

function inst_is_mariadb(mysqli $conn)
{
    return stripos(inst_server_version($conn), 'mariadb') !== false;
}

function inst_server_version(mysqli $conn)
{
    $result = $conn->query('SELECT VERSION()');
    return $result ? (string) $result->fetch_row()[0] : '';
}

/** The server's version as a number, read from its name: MariaDB can report 5.5.5 to clients. Returns an error or ''. */
function inst_check_version(mysqli $conn)
{
    $version = inst_server_version($conn);
    $maria = inst_is_mariadb($conn);
    $number = preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m) ? $m[1] * 10000 + $m[2] * 100 + $m[3] : 0;
    if ($number >= ($maria ? INST_MIN_MARIADB : INST_MIN_MYSQL)) {
        return '';
    }
    return ($maria ? 'MariaDB 10.3' : 'MySQL 8.0') . ' or newer is needed. This server is ' . $version . '.';
}

/** Check the server and database for a new install, creating the database when asked. Returns an error or ''. */
function inst_check_database(array $db)
{
    [$conn, $error] = inst_connect($db, false);
    if (!$conn) {
        return 'Could not connect: ' . $error;
    }
    if (($error = inst_check_version($conn)) !== '') {
        return $error;
    }
    $name = $conn->real_escape_string($db['name']);
    if (!$conn->select_db($db['name'])) {
        if (empty($db['create'])) {
            return 'Database "' . $db['name'] . '" does not exist. Tick Create it, or create it first.';
        }
        if (!$conn->query('CREATE DATABASE `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') || !$conn->select_db($db['name'])) {
            return 'Could not create the database: ' . $conn->error;
        }
    }
    $tables = $conn->query('SHOW TABLES');
    if ($tables && $tables->num_rows > 0) {
        return 'Database "' . $db['name'] . '" already has ' . $tables->num_rows . ' tables. Use an empty database, so nothing is overwritten.';
    }
    return '';
}

/** A password that passes MySQL's password policy: upper and lower case, a digit and a symbol. */
function inst_suggest_password()
{
    return 'Bs-' . bin2hex(random_bytes(8)) . '-X' . random_int(10, 99);
}

/** Hosts a MySQL user needs, for the host the application connects to. */
function inst_user_hosts($dbHost)
{
    return in_array($dbHost, ['127.0.0.1', 'localhost', '::1'], true) ? ['127.0.0.1', 'localhost'] : ['%'];
}

/**
 * What a MySQL administrator runs to make the database and the billing user. Shown on screen, and run as written when the
 * installer is given an administrator login. A user that already exists keeps its password.
 */
function inst_setup_statements(array $db, $plugin = true)
{
    $esc = fn($v) => str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $v);
    $with = $plugin ? 'IDENTIFIED WITH mysql_native_password BY ' : 'IDENTIFIED BY ';
    $sql = ['CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'];
    foreach (inst_user_hosts($db['host']) as $host) {
        $sql[] = "CREATE USER IF NOT EXISTS '" . $esc($db['user']) . "'@'" . $host . "' " . $with . "'" . $esc($db['pass']) . "'";
        $sql[] = 'GRANT ALL PRIVILEGES ON `' . $db['name'] . "`.* TO '" . $esc($db['user']) . "'@'" . $host . "'";
    }
    return $sql;
}

/** Make the database and the billing user with an administrator login, which is used here and not kept. Returns an error or ''. */
function inst_create_user(array $db, $adminUser, $adminPass)
{
    [$conn, $error] = inst_connect(['user' => $adminUser, 'pass' => $adminPass] + $db, false);
    if (!$conn) {
        if (str_contains($error, 'password method')) {
            return 'This PHP cannot sign in as ' . $adminUser . ' because of its password method. Open See the SQL below, run it in Terminal as a MySQL administrator, then choose Use a user I already made.';
        }
        return 'Could not sign in as the MySQL administrator: ' . $error;
    }
    if ($conn->select_db($db['name']) && ($tables = $conn->query('SHOW TABLES')) && $tables->num_rows > 0) {
        return 'Database "' . $db['name'] . '" already has ' . $tables->num_rows . ' tables. Use a new name, so nothing is overwritten.';
    }
    $run = function ($statements) use ($conn) {
        foreach ($statements as $sql) {
            if (!$conn->query($sql)) {
                return $conn->error;
            }
        }
        return '';
    };
    $error = inst_is_mariadb($conn) ? $run(inst_setup_statements($db, false)) : $run(inst_setup_statements($db));
    if ($error !== '' && (stripos($error, 'mysql_native_password') !== false || $conn->errno === 1064)) {
        $error = $run(inst_setup_statements($db, false)); // that plugin is off on this server, or it does not know the words
    }
    if ($error !== '') {
        return stripos($error, 'password does not satisfy') !== false ? 'MySQL refused that password. Use upper and lower case letters, a digit and a symbol.' : 'MySQL refused: ' . $error;
    }
    $conn->query('FLUSH PRIVILEGES');
    return '';
}

/** The sections of the SQL file: label => statements. Each runs as one step, so a failure says where it stopped. */
function inst_sections()
{
    static $sections = null;
    if ($sections === null) {
        $sections = [];
        $parts = preg_split('/^-- SECTION: (.+)$/m', (string) @file_get_contents(INST_ROOT . '/' . INST_SQL), -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $sections[trim($parts[$i])] = $parts[$i + 1];
        }
    }
    return $sections;
}

/** Run one section of the SQL file. Returns an error or ''. */
function inst_run_sql(mysqli $conn, $label)
{
    $sql = inst_sections()[$label] ?? '';
    if ($sql === '') {
        return 'Could not read ' . $label . ' from ' . INST_SQL . '.';
    }
    if (inst_is_mariadb($conn)) {
        $sql = strtr($sql, INST_MARIADB_NAMES);
    }
    if (!$conn->multi_query($sql)) {
        return $label . ': ' . $conn->error;
    }
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno) {
            return $label . ': ' . $conn->error;
        }
    } while ($conn->more_results() && $conn->next_result());
    return $conn->errno ? $label . ': ' . $conn->error : '';
}

function inst_exec(mysqli $conn, $sql, $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $stmt->close();
}

/** Company details, the first branch and store, and the administrator. */
function inst_save_setup(mysqli $conn, array $company, array $admin)
{
    foreach (['name', 'address', 'phone', 'mobile', 'email', 'website', 'bin'] as $key) { // bin: printed on POS receipts
        inst_exec($conn, 'INSERT INTO acc_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', ['company_' . $key, (string) ($company[$key] ?? '')]);
    }
    inst_exec($conn, 'UPDATE client_theme SET brand_name=?, slogan=?, index_title=?, index_heading=?, main_title=? WHERE client_theme_id=1', 'sssss', [$company['brand'], $company['slogan'], $company['brand'], $company['brand'], $company['brand']]);
    inst_exec($conn, 'UPDATE branches SET branch_name=? WHERE branch_id=1', 's', [$company['branch']]);
    inst_exec($conn, 'INSERT IGNORE INTO sales_stores (store_name) VALUES (?)', 's', [$company['store']]);
    inst_exec($conn, 'INSERT INTO inv_settings (setting_key, setting_value) VALUES (\'default_store\', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 's', [$company['store']]);
    $role = (int) ($conn->query("SELECT user_role_id FROM admin_role WHERE role_name='Administrator' ORDER BY user_role_id LIMIT 1")->fetch_row()[0] ?? 1);
    inst_exec($conn, "INSERT INTO admin_user (user_name, full_name, user_password, email, mobile, user_role_id, access_level, user_type, full_time, is_active, all_branches, default_branch_id, current_branch_id, show_user)
        VALUES (?, ?, ?, ?, ?, ?, 'SuperAdmin', 'staff', 1, 1, 1, 1, 1, 1)", 'sssssi', [$admin['user'], $admin['name'], $admin['hash'], $admin['email'], $admin['mobile'], $role]);
}

/** The application's database configuration file. */
function inst_config_source(array $db)
{
    $values = '';
    foreach (['dbhost' => (string) $db['host'], 'dbport' => (int) $db['port'], 'dbname' => (string) $db['name'], 'dbuser' => (string) $db['user'], 'dbpass' => (string) $db['pass'], 'dbcharset' => 'latin1', 'processtime' => 600] as $name => $value) {
        $values .= '$' . $name . ' = ' . var_export($value, true) . ";\n";
    }
    return "<?php\n" . $values . <<<'PHP'
$host = $servername = $dbhost;
$username = $dbuser;
$password = $dbpass;
$database = $dbname;
$ptime = $processtime;
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if (!$conn || !@$conn->real_connect($dbhost, $dbuser, $dbpass, $dbname, $dbport)) {
    throw new RuntimeException('Database connection failed: ' . (mysqli_connect_error() ?: 'unable to initialize MySQL client'));
}
if (!$conn->set_charset($dbcharset)) {
    throw new RuntimeException('Could not set database connection charset.');
}
$link = $conn;
$db = true;

PHP;
}

/** The configuration with its password hidden, for showing on screen before the install. */
function inst_config_preview(array $db)
{
    return inst_config_source(['pass' => '********'] + $db);
}

/** Writing this file is what marks the application installed. */
function inst_write_config(array $db)
{
    $temp = INST_CONFIG . '.tmp';
    if (@file_put_contents($temp, inst_config_source($db), LOCK_EX) === false) {
        return 'Could not write addon/display/dbconfig.php. Give the web server write access to addon/display.';
    }
    @chmod($temp, 0640);
    return rename($temp, INST_CONFIG) ? '' : 'Could not save addon/display/dbconfig.php.';
}

/**
 * Delete this folder. Only the session that finished an install calls this: a system already running, or a developer's
 * copy, keeps its installer, which stays locked. Nothing is touched unless the whole folder can go. Returns true if it went.
 */
function inst_remove_self()
{
    $dir = (string) realpath(__DIR__);
    if (basename($dir) !== 'install_script' || !is_file(INST_CONFIG) || !is_writable($dir) || !is_writable(dirname($dir))) {
        return false;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    return @rmdir($dir);
}
