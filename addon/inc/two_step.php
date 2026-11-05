<?php
/**
 * Two-step sign-in: after the password, a one-time code by SMS and/or email. Set per group on Settings > User Config
 * (acc_settings tfa_staff, tfa_employee, tfa_customer: off, sms, email or both; tfa_minutes). Sending goes through
 * Settings > Email & SMS (messaging.php). The code is kept hashed in the PHP session of the sign-in, with a time limit,
 * a few tries and a few resends; running out of either means starting again from the password.
 * Used by the main sign-in (addon/inc/user.php, verify.php) and the portals (customer_portal, sr_portal verify.php).
 * If messages stop working and nobody can sign in: UPDATE acc_settings SET setting_value='off' WHERE setting_key='tfa_staff'.
 */
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/brand.php';

const TFA_GROUPS = ['staff' => 'Admin and office users', 'employee' => 'Sales staff (SR portal)', 'customer' => 'Customers (customer portal)'];
const TFA_METHODS = ['off' => 'Off', 'sms' => 'SMS code', 'email' => 'Email code', 'both' => 'SMS and email code'];
const TFA_TRIES = 5;           // wrong codes before the sign-in starts again
const TFA_RESEND_SECONDS = 60;
const TFA_MAX_SENDS = 4;       // first code and resends

function tfa_get($key, $default = '')
{
    $row = perm_q('SELECT setting_value FROM acc_settings WHERE setting_key=?', 's', ['tfa_' . $key])[0] ?? null;
    return $row ? (string) $row['setting_value'] : $default;
}

function tfa_put($key, $value)
{
    perm_q('INSERT INTO acc_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', ['tfa_' . $key, (string) $value]);
}

function tfa_method($group)
{
    $method = tfa_get($group, 'off');
    return isset(TFA_METHODS[$method]) ? $method : 'off';
}

function tfa_minutes()
{
    return min(30, max(2, (int) tfa_get('minutes', '5')));
}

/** The channels a method uses. */
function tfa_channels($method)
{
    return $method === 'both' ? ['sms', 'email'] : ($method === 'off' ? [] : [$method]);
}

/** Where a user's codes go: the admin_user mobile and email, else the customer's or sales person's own mobile. */
function tfa_contacts(array $u)
{
    $mobile = (string) ($u['mobile'] ?? '');
    $type = (string) ($u['user_type'] ?? 'staff');
    if (msg_phone($mobile) === '' && in_array($type, ['customer', 'employee'], true) && (int) ($u['linked_id'] ?? 0) > 0) {
        $sql = $type === 'customer' ? 'SELECT mobile FROM sales_customers WHERE customer_id=?' : 'SELECT mobile FROM sales_representatives WHERE sr_id=?';
        $mobile = (string) (perm_q($sql, 'i', [(int) $u['linked_id']])[0]['mobile'] ?? '');
    }
    $email = trim((string) ($u['email'] ?? ''));
    return ['sms' => msg_phone($mobile), 'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ''];
}

/** '' when a user of this group can receive a code by the given method, else what is missing. */
function tfa_missing($method, array $contacts)
{
    $channels = tfa_channels($method);
    if (!$channels || array_filter($channels, fn($c) => $contacts[$c] !== '')) {
        return '';
    }
    return ['sms' => 'mobile number', 'email' => 'email address', 'both' => 'mobile number or email address'][$method];
}

/** Text that hides most of a number or address: 017*****347, n***@gmail.com. */
function tfa_mask($channel, $to)
{
    if ($channel === 'sms') {
        return substr($to, 0, 3) . str_repeat('*', max(0, strlen($to) - 6)) . substr($to, -3);
    }
    [$name, $domain] = explode('@', $to, 2);
    return substr($name, 0, 1) . str_repeat('*', max(2, strlen($name) - 1)) . '@' . $domain;
}

/**
 * Park a sign-in whose password was right and send its code. $finish is what the verify page needs to finish it.
 * Returns '' when a code went out, else why the sign-in cannot go on.
 */
function tfa_start($group, array $u, $login, array $finish)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['tfa']);
    $method = tfa_method($group);
    $contacts = tfa_contacts($u);
    if (($missing = tfa_missing($method, $contacts)) !== '') {
        audit_security('otp_no_contact', "Two-step code not sent: no $missing for $login");
        return "Two-step verification is on, but this login has no $missing on file. Ask the office to add one.";
    }
    $_SESSION['tfa'] = [
        'group' => $group,
        'login' => (string) $login,
        'to' => array_filter(array_intersect_key($contacts, array_flip(tfa_channels($method)))),
        'finish' => $finish,
        'started' => time(),
        'sends' => 0,
        'csrf' => bin2hex(random_bytes(16)),
    ];
    return tfa_send();
}

/** Send a new code to every channel of the parked sign-in. Returns '' or the reason nothing went out. */
function tfa_send()
{
    $t = &$_SESSION['tfa'];
    $code = (string) random_int(100000, 999999);
    $minutes = tfa_minutes();
    $text = "$code is your " . brand_info()['brand'] . " sign-in code. It expires in $minutes minutes. Never share it.";
    $sent = [];
    $errors = [];
    foreach ($t['to'] as $channel => $to) {
        $error = $channel === 'sms' ? msg_sms($to, $text) : msg_mail($to, 'Your sign-in code', $text . "\n\nIf you did not just try to sign in, change your password.\n");
        $error === '' ? $sent[] = tfa_mask($channel, $to) : $errors[] = $error;
    }
    if (!$sent) {
        error_log('Two-step code not sent to ' . $t['login'] . ': ' . implode(' | ', $errors));
        unset($_SESSION['tfa']);
        return 'The sign-in code could not be sent. ' . ($errors[0] ?? '') . ' Contact your administrator.';
    }
    $t['salt'] = bin2hex(random_bytes(16));
    $t['hash'] = hash('sha256', $t['salt'] . $code);
    $t['expires'] = time() + $minutes * 60;
    $t['sent_at'] = time();
    $t['sent_to'] = $sent;
    $t['sends']++;
    $t['tries'] = 0;
    return '';
}

/** The parked sign-in, or null when there is none or it is over 30 minutes old. */
function tfa_pending()
{
    $t = $_SESSION['tfa'] ?? null;
    if ($t && $t['started'] + 1800 < time()) {
        unset($_SESSION['tfa']);
        return null;
    }
    return $t;
}

/** Check a typed code. Returns the parked $finish when it is right, else the message to show. */
function tfa_verify($code)
{
    $t = &$_SESSION['tfa'];
    if (time() > $t['expires']) {
        return 'This code has expired. Send a new one.';
    }
    $t['tries']++;
    if (!hash_equals($t['hash'], hash('sha256', $t['salt'] . preg_replace('/\D/', '', (string) $code)))) {
        if ($t['tries'] >= TFA_TRIES) {
            audit_security('otp_failed', 'Too many wrong sign-in codes for ' . $t['login'], [], 'bad');
            unset($_SESSION['tfa']);
            return 'Too many wrong codes. Sign in again.';
        }
        $left = TFA_TRIES - $t['tries'];
        return "That code is not correct. $left " . ($left === 1 ? 'try' : 'tries') . ' left.';
    }
    $finish = $t['finish'];
    unset($_SESSION['tfa']);
    session_regenerate_id(true);
    return $finish;
}

/** Send another code when allowed. Returns '' or why not. */
function tfa_resend()
{
    $t = $_SESSION['tfa'];
    if ($t['sends'] >= TFA_MAX_SENDS) {
        unset($_SESSION['tfa']);
        return 'No more codes can be sent. Sign in again.';
    }
    $wait = $t['sent_at'] + TFA_RESEND_SECONDS - time();
    if ($wait > 0) {
        return "Wait $wait seconds before asking for a new code.";
    }
    return tfa_send();
}

/** Text under the heading of a verify page: where the code went and for how long it works. */
function tfa_sent_text(array $t)
{
    return 'We sent a 6-digit code to ' . implode(' and ', $t['sent_to']) . '. It works for ' . tfa_minutes() . ' minutes.';
}

/** The code form of a portal verify page (addon/portal/portal.css). $csrf is the portal's hidden field. */
function tfa_portal_form($csrf)
{
    return '<form method="post" class="card grid" autocomplete="off">' . $csrf
        . '<div><label for="code">Sign-in code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus placeholder="6-digit code"></div>'
        . '<button class="btn" name="action" value="verify">Verify and sign in</button>'
        . '<button class="btn light" name="action" value="resend" formnovalidate>Send a new code</button></form>'
        . '<p><a href="login.php">Back to sign in</a></p>';
}

/* ---------------- Main sign-in page ---------------- */

/**
 * Called by addon/inc/user.php once the password and the sign-in rules have passed. Returns at once when the user's
 * group needs no code; otherwise sends one and goes to verify.php, which shows the code box or why it could not be sent.
 */
function tfa_gate_shell($u, $next)
{
    if (!$u) {
        return; // tables of an older install: no user type or mobile to go by
    }
    $group = isset(TFA_GROUPS[$u['user_type'] ?? '']) ? $u['user_type'] : 'staff';
    if (tfa_method($group) === 'off') {
        return;
    }
    $error = tfa_start($group, $u, (string) $u['user_name'], ['user_id' => (int) $u['user_id'], 'next' => $next]);
    if ($error !== '') {
        $_SESSION['tfa_error'] = $error;
    }
    header('Location: verify.php', true, 303);
    exit;
}

/* ---------------- Portals ---------------- */

/**
 * After a portal's own password check has signed the account in: when its group needs a code, sign it out again, send
 * the code and return '' (go to verify.php) or the error. Returns null when no code is needed.
 */
function tfa_portal_hold($type, $sessionKey, $login)
{
    if (tfa_method($type) === 'off') {
        return null;
    }
    $accountId = (int) $_SESSION[$sessionKey];
    unset($_SESSION[$sessionKey]);
    [$table, $col] = perm_portal_table($type);
    $linked = (int) (perm_q("SELECT $col FROM $table WHERE account_id=?", 'i', [$accountId])[0][$col] ?? 0);
    $u = (perm_q('SELECT * FROM admin_user WHERE user_type=? AND linked_id=? LIMIT 1', 'si', [$type, $linked])[0] ?? []) + ['user_type' => $type, 'linked_id' => $linked];
    return tfa_start($type, $u, $login, ['account_id' => $accountId]);
}
