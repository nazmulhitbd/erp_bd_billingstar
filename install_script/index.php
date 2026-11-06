<?php
/**
 * PetaBill installer. Steps: server check, database, company (optionally with sample data), administrator, install, finish.
 * It only works while addon/display/dbconfig.php does not exist, and only on an empty database, so it can never touch a
 * running system. The billing database password stays in this PHP session until the install ends; a MySQL administrator
 * login is used once on the database step and never kept; the application administrator's password is hashed when entered.
 * Nothing is hidden from the person installing: the SQL file and the configuration can be read on screen, and if the
 * server cannot write the configuration, the finish screen shows it to save by hand. The finish screen removes this folder.
 */
require_once __DIR__ . '/install_lib.php';

session_name('BSINSTALL');
session_start();
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$finished = $_SESSION['inst_finished'] ?? null;

/** Stop every request once installed; the session that is finishing the install is the one exception. */
if (is_file(INST_CONFIG) && !$finished && empty($_SESSION['inst']['built'])) {
    http_response_code(403);
    inst_shell('Already installed', 0, '<h1>PetaBill is already installed</h1><p>The installer is switched off, so it cannot change this system.</p><p><a class="btn" href="../index.php">Go to sign in</a></p><p class="note">To install again, remove <code>addon/display/dbconfig.php</code> from the server. That disconnects this system from its database.</p>');
    exit;
}
if (empty($_SESSION['inst_token'])) {
    $_SESSION['inst_token'] = bin2hex(random_bytes(32));
    $_SESSION['inst'] = ['db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'billing_star', 'user' => 'billinguser', 'pass' => inst_suggest_password(), 'mode' => 'create'], 'company' => [], 'admin' => [], 'reached' => 1];
}
$inst = &$_SESSION['inst'];
$token = $_SESSION['inst_token'];
$field = fn($key, $max = 190) => mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);

/** Read the SQL file, or the configuration with its password hidden, in a tab of its own. */
if (isset($_GET['view']) && !empty($inst) && (int) ($inst['reached'] ?? 0) >= 3) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    exit($_GET['view'] === 'config' ? inst_config_preview($inst['db']) : (string) @file_get_contents(INST_ROOT . '/' . INST_SQL));
}

/** One step of the build, called by install.js. */
if (($_POST['action'] ?? '') === 'run') {
    header('Content-Type: application/json; charset=utf-8');
    $fail = function ($message) {
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    };
    if (!hash_equals($token, (string) ($_POST['token'] ?? '')) || (int) ($inst['reached'] ?? 0) < 5 || $finished) {
        $fail('This session has expired. Reload the page.');
    }
    set_time_limit(0);
    $labels = array_keys(inst_sections());
    $step = (int) ($_POST['step'] ?? -1);
    [$conn, $error] = inst_connect($inst['db']);
    if (!$conn) {
        $fail('Could not connect: ' . $error);
    }
    if ($step >= 0 && $step < count($labels)) {
        $error = inst_run_sql($conn, $labels[$step]);
        $error === '' ? print(json_encode(['ok' => true, 'label' => $labels[$step]])) : $fail($error);
        exit;
    }
    $tail = array_merge(['setup'], !empty($inst['sample']) && inst_has_sample() ? ['sample'] : [], ['config']);
    $what = $step >= count($labels) ? ($tail[$step - count($labels)] ?? '') : '';
    if ($what === 'setup') {
        try {
            inst_exec($conn, 'DELETE FROM admin_user WHERE user_name=?', 's', [$inst['admin']['user']]); // a retry starts clean
            inst_save_setup($conn, $inst['company'], $inst['admin']);
            inst_save_licence($conn, $inst['licence']);
        } catch (Throwable $e) {
            $fail('Saving your company and administrator failed: ' . $e->getMessage());
        }
        echo json_encode(['ok' => true, 'label' => 'Company and administrator']);
        exit;
    }
    if ($what === 'sample') {
        $error = inst_load_sample($conn, $inst['admin']['user']);
        $error === '' ? print(json_encode(['ok' => true, 'label' => 'Sample data'])) : $fail($error);
        exit;
    }
    if ($what === 'config') {
        $inst['built'] = 1;
        if (inst_write_config($inst['db']) !== '') {
            echo json_encode(['ok' => true, 'label' => 'Configuration', 'done' => true]); // the finish step shows it to save by hand
            exit;
        }
        $_SESSION['inst_finished'] = ['user' => $inst['admin']['user'], 'brand' => $inst['company']['brand'], 'sample' => !empty($inst['sample'])];
        unset($_SESSION['inst']); // the database password leaves the session here
        echo json_encode(['ok' => true, 'label' => 'Configuration', 'done' => true]);
        exit;
    }
    $fail('Unknown step.');
}

$errors = [];
$reached = (int) ($inst['reached'] ?? 1);
$step = max(1, min((int) ($_GET['step'] ?? $reached), $reached));
if ($finished || !empty($inst['built'])) {
    $step = 6;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$finished) {
    $step = (int) ($_POST['step'] ?? 1);
    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        $errors[] = 'This form has expired. Please try again.';
    } elseif ($step < 1 || ($step > $reached && !($step === 6 && !empty($inst['built'])))) { // no step can be skipped: activation with PetaBill Cloud always comes first
        $errors[] = 'Finish the earlier steps first.';
        $step = $reached;
    } elseif ($step === 6 && !empty($inst['built'])) {
        $clean = fn($text) => rtrim(str_replace("\r", '', (string) $text)); // editors change line endings and the last line
        if ($clean(@file_get_contents(INST_CONFIG)) === $clean(inst_config_source($inst['db']))) {
            $_SESSION['inst_finished'] = ['user' => $inst['admin']['user'], 'brand' => $inst['company']['brand'], 'sample' => !empty($inst['sample'])];
            unset($_SESSION['inst']);
            header('Location: index.php?step=6');
            exit;
        }
        $errors[] = 'The file is not there yet, or its contents differ. Save exactly the text below as addon/display/dbconfig.php.';
    } elseif ($step === 1) {
        $code = trim((string) ($_POST['connection'] ?? ''));
        if (array_filter(inst_requirements(), fn($c) => !$c[1] && $c[3])) {
            $errors[] = 'Fix the items marked below first.';
        } elseif ($code === '' && empty($inst['licence'])) {
            $errors[] = 'Paste the Connection Code from PetaBill Cloud.';
        } else {
            $error = '';
            if ($code !== '') {
                [$licence, $error] = inst_activate($code);
                if ($licence) {
                    $inst['licence'] = $licence;
                    $inst['company'] += ['name' => $licence['data']['company'] ?? '', 'brand' => mb_substr((string) ($licence['data']['company'] ?? ''), 0, 50)];
                }
            }
            if ($error === '') {
                $inst['reached'] = max($reached, 2);
                header('Location: index.php?step=2');
                exit;
            }
            $errors[] = $error;
        }
    } elseif ($step === 2) {
        $mode = ($_POST['mode'] ?? '') === 'existing' ? 'existing' : 'create';
        $db = ['host' => $field('host', 120), 'port' => (int) ($_POST['port'] ?? 3306), 'name' => $field('name', 64), 'user' => $field('user', 80), 'pass' => (string) ($_POST['pass'] ?? ''), 'mode' => $mode, 'create' => $mode === 'existing' && isset($_POST['create']) ? 1 : 0];
        $inst['db'] = $db;
        if ($db['host'] === '' || !preg_match('/^[A-Za-z0-9_]{1,32}$/', $db['user']) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $db['name']) || $db['port'] < 1 || $db['port'] > 65535) {
            $errors[] = 'Enter the host, port, and a database name and user of letters, numbers and _ only.';
        } elseif ($db['pass'] === '' || ($mode === 'create' && strlen($db['pass']) < 10)) {
            $errors[] = 'Enter the billing user\'s password' . ($mode === 'create' ? ', at least 10 characters.' : '.');
        } else {
            $error = $mode === 'create' ? inst_create_user($db, $field('admin_user', 80), (string) ($_POST['admin_pass'] ?? '')) : '';
            $error = $error !== '' ? $error : inst_check_database($db);
            if ($error !== '') {
                $errors[] = $error;
            } else {
                $inst['reached'] = max($reached, 3);
                header('Location: index.php?step=3');
                exit;
            }
        }
    } elseif ($step === 3) {
        $inst['company'] = ['name' => $field('name'), 'brand' => $field('brand', 50), 'slogan' => $field('slogan', 120), 'address' => $field('address', 255), 'phone' => $field('phone', 40), 'mobile' => $field('mobile', 40),
            'email' => $field('email'), 'website' => $field('website'), 'bin' => $field('bin', 60), 'branch' => $field('branch', 120), 'store' => $field('store', 140)];
        $inst['sample'] = isset($_POST['sample']) && inst_has_sample() ? 1 : 0;
        $c = $inst['company'];
        if ($c['name'] === '' || $c['brand'] === '' || $c['branch'] === '' || $c['store'] === '') {
            $errors[] = 'Enter the company name, the short name, the first branch and the first store.';
        } elseif ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address or leave it blank.';
        } else {
            $inst['reached'] = max($reached, 4);
            header('Location: index.php?step=4');
            exit;
        }
    } elseif ($step === 4) {
        $password = (string) ($_POST['password'] ?? '');
        $inst['admin'] = ['name' => $field('full_name', 120), 'user' => $field('user_name', 100), 'email' => $field('email', 150), 'mobile' => $field('mobile', 30), 'hash' => $inst['admin']['hash'] ?? ''];
        $a = $inst['admin'];
        if ($a['name'] === '' || !preg_match('/^[A-Za-z0-9._@-]{3,100}$/', $a['user'])) {
            $errors[] = 'Enter a name, and a user name of 3 or more letters, numbers or . _ @ -';
        } elseif ($a['email'] !== '' && !filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address or leave it blank.';
        } elseif ($password === '' && $a['hash'] !== '') {
            $inst['reached'] = max($reached, 5);
            header('Location: index.php?step=5');
            exit;
        } elseif (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Use a password of at least 10 characters with letters and numbers.';
        } elseif ($password !== (string) ($_POST['confirm'] ?? '')) {
            $errors[] = 'The two passwords do not match.';
        } else {
            $inst['admin']['hash'] = password_hash($password, PASSWORD_DEFAULT);
            $inst['reached'] = max($reached, 5);
            header('Location: index.php?step=5');
            exit;
        }
    }
}

$hidden = '<input type="hidden" name="token" value="' . $h($token) . '"><input type="hidden" name="step" value="' . $step . '">';
$input = function ($name, $label, $value, $type = 'text', $attrs = '') use ($h) {
    return '<div class="field"><label for="' . $name . '">' . $label . '</label><input id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $h($value) . '" ' . $attrs . '></div>';
};
$actions = fn($back, $next) => '<div class="actions">' . ($back ? '<a class="btn light" href="index.php?step=' . $back . '">Back</a>' : '<span></span>') . '<button class="btn">' . $next . '</button></div>';
$body = '';
foreach ($errors as $e) {
    $body .= '<div class="alert">' . $h($e) . '</div>';
}

$commandBox = fn($text, $id) => '<div class="code-box"><button type="button" class="copy" data-copy="' . $id . '">Copy</button><pre id="' . $id . '">' . $h($text) . '</pre></div>';
if ($step === 1) {
    $checks = inst_requirements();
    $blocked = (bool) array_filter($checks, fn($c) => !$c[1] && $c[3]);
    $body .= '<h1>Check this server</h1><p class="lead">We check this server, then activate it with PetaBill Cloud.</p><ul class="checks">';
    foreach ($checks as [$label, $good, $fix, $required]) {
        $body .= '<li class="' . ($good ? 'pass' : ($required ? 'fail' : 'warn')) . '"><b>' . ($good ? 'OK' : ($required ? 'Fix' : 'Note')) . '</b><span>' . $h($label) . ($good ? '' : '<small>' . $h($fix) . '</small>') . '</span></li>';
    }
    $body .= '</ul>';
    if ($closed = inst_unwritable()) {
        $body .= '<p class="note"><b>Run this in Terminal</b>, then press Check Again. It gives the web server write access. Uploads need it, and without it you save the configuration file by hand at the end.</p>' . $commandBox(inst_permission_commands($closed), 'fix-folders');
    }
    $done = $inst['licence']['data'] ?? null;
    $body .= '<form method="post">' . $hidden . '<h2>Activate with PetaBill Cloud</h2>'
        . ($done ? '<p class="note"><b>Activated:</b> service ' . (int) $done['service_code'] . ', ' . $h($done['company']) . ', ' . $h($done['plan']) . ' plan, expires ' . $h($done['expiry_date']) . '. Paste another code only to change it.</p>' : '<p class="note">In PetaBill Cloud, create the service for this install and copy its Connection Code. The install cannot continue without an active service.</p>')
        . '<div class="field"><label for="connection">Connection Code</label><textarea id="connection" name="connection" rows="4"' . ($done ? '' : ' required') . ' autocomplete="off"></textarea></div>'
        . '<div class="actions"><a class="btn light" href="index.php?step=1">Check Again</a><button class="btn"' . ($blocked ? ' disabled' : '') . '>Activate And Continue</button></div></form>';
}
if ($step === 2) {
    $d = $inst['db'];
    $create = ($d['mode'] ?? 'create') === 'create';
    $body .= '<h1>Connect the database</h1><p class="lead">The application signs in with its own MySQL user, not root. MySQL 8.0+ or MariaDB 10.3+ is needed.</p><p class="note">On cPanel: first make the database and user in MySQL Databases, add the user to the database with All Privileges, choose Use a user I already made, and set Database Host to localhost. Names include your cPanel prefix, such as account_billing.</p><form method="post" autocomplete="off">' . $hidden
        . '<div class="choice"><label><input type="radio" name="mode" value="create"' . ($create ? ' checked' : '') . '> <span><b>Create the database and user for me</b><small>You give a MySQL administrator login once. It is not saved.</small></span></label>'
        . '<label><input type="radio" name="mode" value="existing"' . ($create ? '' : ' checked') . '> <span><b>Use a user I already made</b><small>The database must be empty.</small></span></label></div>'
        . '<div class="grid" data-mode="create">' . $input('admin_user', 'MySQL Administrator', 'root', 'text', 'autocomplete="off"') . $input('admin_pass', 'Administrator Password', '', 'password', 'autocomplete="new-password"') . '</div>'
        . '<div class="grid">' . $input('host', 'Database Host', $d['host'], 'text', 'required') . $input('port', 'Port', $d['port'], 'number', 'required min="1" max="65535"') . $input('name', 'Database Name', $d['name'], 'text', 'required pattern="[A-Za-z0-9_]+"')
        . $input('user', 'Billing User', $d['user'], 'text', 'required pattern="[A-Za-z0-9_]+" autocomplete="off"') . $input('pass', 'Billing User Password', $d['pass'], 'text', 'required autocomplete="off"') . '</div>'
        . '<label class="check" data-mode="existing"><input type="checkbox" name="create"' . (!empty($d['create']) ? ' checked' : '') . '> Create this database if it does not exist</label>'
        . '<details class="sql"><summary>See the SQL this runs, or run it yourself</summary><p class="note">To do it by hand, run this as a MySQL administrator, then choose Use a user I already made. On MariaDB, remove WITH mysql_native_password.</p>' . $commandBox(implode(";\n", inst_setup_statements($d)) . ';', 'setup-sql') . '</details>'
        . $actions(1, 'Test And Continue') . '</form>';
}
if ($step === 3) {
    $c = $inst['company'] + ['name' => '', 'brand' => '', 'slogan' => '', 'address' => '', 'phone' => '', 'mobile' => '', 'email' => '', 'website' => '', 'bin' => '', 'branch' => 'Head Office', 'store' => 'Main Store'];
    $body .= '<h1>Your company</h1><p class="lead">These appear on invoices and the sign-in page. You can change them later in Company &amp; Login Settings.</p><form method="post">' . $hidden . '<div class="grid">'
        . $input('name', 'Company Name', $c['name'], 'text', 'required maxlength="190"') . $input('brand', 'Short Name', $c['brand'], 'text', 'required maxlength="50"') . $input('slogan', 'Slogan', $c['slogan']) . $input('address', 'Address', $c['address'])
        . $input('phone', 'Phone', $c['phone']) . $input('mobile', 'Mobile', $c['mobile']) . $input('email', 'Email', $c['email'], 'email') . $input('website', 'Website', $c['website']) . $input('bin', 'BIN (VAT Registration)', $c['bin'], 'text', 'maxlength="60"')
        . $input('branch', 'First Branch', $c['branch'], 'text', 'required') . $input('store', 'First Store', $c['store'], 'text', 'required') . '</div>'
        . (inst_has_sample() ? '<label class="check"><input type="checkbox" name="sample"' . (!empty($inst['sample']) ? ' checked' : '') . '> <span><b>Start with sample data</b><small>A demo electronics shop to try every module: 14 products, suppliers, customers, purchases, sales and POS, orders, returns, vouchers, staff, attendance, sales targets and a service complaint. All names and numbers are invented. Leave it off for a system you will use for real: sample records stay until you delete them.</small></span></label>' : '')
        . $actions(2, 'Continue') . '</form>';
}
if ($step === 4) {
    $a = $inst['admin'] + ['name' => '', 'user' => '', 'email' => '', 'mobile' => '', 'hash' => ''];
    $body .= '<h1>Create the administrator</h1><p class="lead">This is the SuperAdmin login, with full access to everything.</p><form method="post" autocomplete="off">' . $hidden . '<div class="grid">'
        . $input('full_name', 'Full Name', $a['name'], 'text', 'required') . $input('user_name', 'User Name', $a['user'], 'text', 'required minlength="3" pattern="[A-Za-z0-9._@\-]+" autocomplete="off"') . $input('email', 'Email', $a['email'], 'email') . $input('mobile', 'Mobile', $a['mobile'])
        . $input('password', 'Password', '', 'password', 'autocomplete="new-password" minlength="10"' . ($a['hash'] !== '' ? ' placeholder="Saved: leave blank to keep it"' : ' required')) . $input('confirm', 'Confirm Password', '', 'password', 'autocomplete="new-password"' . ($a['hash'] !== '' ? '' : ' required')) . '</div>'
        . '<p class="note">At least 10 characters with letters and numbers. Keep it safe: it cannot be recovered, only reset by another SuperAdmin.</p>' . $actions(3, 'Continue') . '</form>';
}
if ($step === 5) {
    $d = $inst['db'];
    $c = $inst['company'];
    $a = $inst['admin'];
    $rows = ['Service Code' => $inst['licence']['code'] ?? '', 'Database' => $d['name'] . ' on ' . $d['host'] . ':' . $d['port'], 'Billing User' => $d['user'], 'Company' => $c['name'], 'First Branch' => $c['branch'], 'First Store' => $c['store'], 'Administrator' => $a['name'] . ' (' . $a['user'] . ')', 'Sample Data' => !empty($inst['sample']) && inst_has_sample() ? 'Yes, load the demo shop' : 'No'];
    $body .= '<div id="review"><h1>Ready to install</h1><p class="lead">Check these details, then start. It takes about a minute.</p><dl class="review">';
    foreach ($rows as $k => $v) {
        $body .= '<dt>' . $k . '</dt><dd>' . $h($v) . '</dd>';
    }
    $sections = array_keys(inst_sections());
    $withSample = !empty($inst['sample']) && inst_has_sample();
    $body .= '</dl><details class="sql"><summary>See the files that will be used (one SQL file and the configuration)</summary><ul class="files"><li><a target="_blank" rel="noopener" href="index.php?view=config">addon/display/dbconfig.php</a><small>Written last. Password hidden here.</small></li>'
        . '<li><a target="_blank" rel="noopener" href="index.php?view=sql">' . $h(INST_SQL) . '</a><small>' . count($sections) . ' parts, ' . number_format((int) @filesize(INST_ROOT . '/' . INST_SQL) / 1024) . ' KB</small></li>';
    $body .= '</ul></details><div class="actions"><a class="btn light" href="index.php?step=4">Back</a><button class="btn" id="start" type="button">Install Now</button></div></div>'
        . '<div id="progress" hidden><h1>Installing</h1><p class="lead" id="status">Starting...</p><div class="bar" role="progressbar" aria-label="Install progress"><span id="fill"></span></div><ol class="log" id="log"></ol><div class="alert" id="failed" role="alert" hidden></div><div class="actions" id="retry-row" hidden><span></span><button class="btn" id="retry" type="button">Try Again</button></div></div>'
        . '<script id="install-data" type="application/json">' . json_encode(['token' => $token, 'steps' => array_merge($sections, ['Company and administrator'], $withSample ? ['Sample data'] : [], ['Configuration']), 'files' => array_merge($sections, ['Your company and administrator'], $withSample ? [INST_SAMPLE_SQL] : [], ['addon/display/dbconfig.php'])]) . '</script>';
}
if ($step === 6 && !$finished) {
    $body .= '<h1>One last step: save the configuration</h1><p class="lead">The database is ready, but the server cannot write this file. Save it yourself and the install is done.</p>'
        . '<p class="note">Create <code>addon/display/dbconfig.php</code> with exactly this text. It holds the password, so keep it private.</p>' . $commandBox(inst_config_source($inst['db']), 'config-file')
        . '<form method="post">' . $hidden . '<div class="actions"><span></span><button class="btn">I Saved It</button></div></form>';
}
if ($step === 6 && $finished) {
    $body .= '<h1>PetaBill is installed</h1><p class="lead">Your system is ready, and the installer has switched itself off.</p><dl class="review"><dt>Company</dt><dd>' . $h($finished['brand'] ?? '') . '</dd><dt>Administrator</dt><dd>' . $h($finished['user'] ?? '') . '</dd></dl>';
    if ($closed = inst_unwritable()) {
        $body .= '<p class="note"><b>Uploads need one more step.</b> Run this in Terminal so photos and documents can be saved:</p>' . $commandBox(inst_permission_commands($closed), 'fix-folders');
    }
    // The folder goes now, so this last page brings its own styles and script. Reloading it is a plain 404, and the session ends here.
    $inline = ['style' => (string) @file_get_contents(__DIR__ . '/install.css'), 'script' => (string) @file_get_contents(__DIR__ . '/install.js')];
    $removed = inst_remove_self();
    $body .= (!empty($finished['sample']) ? '<p class="note"><b>Sample data is loaded:</b> a demo shop with invented products, customers and documents, so you can try every module. For your real business, install again on a new, empty database.</p>' : '')
        . '<p class="note"><b>Next:</b> sign in, then set up Users, Roles &amp; Permissions and the Salary Accounts.</p>'
        . ($removed ? '<p class="note">The <code>install_script</code> folder has been removed from this server.</p>' : '<div class="alert">The <code>install_script</code> folder could not be removed: the web server cannot delete it. It stays locked, but delete it from the server yourself.</div>')
        . '<div class="actions"><span></span><a class="btn" href="../index.php">Go To Sign In</a></div>';
    $_SESSION = [];
    session_destroy();
}
inst_shell('Install PetaBill', $step, $body, $inline ?? null);

/** The page around each step. The last one is given its files as text, as they are gone by the time a browser would ask. */
function inst_shell($title, $step, $body, $inline = null)
{
    $steps = ['Activate', 'Database', 'Company', 'Administrator', 'Install', 'Finish'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . ($inline ? '<style>' . $inline['style'] . '</style>' : '<link rel="stylesheet" href="install.css">') . '</head><body><main class="card"><aside class="side"><div class="logo">PetaBill</div><p>Setup</p>';
    if ($step) {
        echo '<ol>';
        foreach ($steps as $i => $label) {
            $n = $i + 1;
            echo '<li class="' . ($n < $step ? 'done' : ($n === $step ? 'now' : '')) . '"' . ($n === $step ? ' aria-current="step"' : '') . '><span>' . ($n < $step ? '&#10003;' : $n) . '</span>' . $label . '</li>';
        }
        echo '</ol>';
    }
    echo '</aside><section class="main">' . $body . '</section></main>' . ($inline ? '<script>' . $inline['script'] . '</script>' : '<script src="install.js"></script>') . '</body></html>';
}
