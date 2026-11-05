<?php
/**
 * Create an account: a customer proves who they are with the mobile number on their invoices and one of their
 * invoice numbers, then chooses a password. Allowed only while portal setting allow_signup is 1. The answer is the
 * same whatever does not match, so the form cannot be used to find customers.
 */
require_once __DIR__ . '/portal_common.php';

$open = cp_ready() && cp_setting('allow_signup', '1') === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    cp_check_csrf('register.php');
    $mobile = cp_phone_key(cp_post('mobile', 40));
    $invoiceNo = cp_post('invoice_no', 40);
    $pass = (string) ($_POST['password'] ?? '');
    $ipFails = (int) cp_row("SELECT COUNT(*) AS n FROM portal_login_log WHERE ip_address=? AND success=0 AND created_at > NOW() - INTERVAL ? MINUTE", 'si', [cp_ip(), CP_LOCK_MINUTES])['n'];
    if ($ipFails >= CP_IP_MAX_FAILS) {
        cp_redirect('register.php', 'err', 'Too many attempts from this connection. Please wait ' . CP_LOCK_MINUTES . ' minutes.');
    }
    if (strlen($pass) < CP_MIN_PASSWORD || $pass !== (string) ($_POST['password2'] ?? '')) {
        cp_redirect('register.php', 'err', 'Choose a password of at least ' . CP_MIN_PASSWORD . ' characters and type it twice.');
    }
    $match = strlen($mobile) === 10 ? cp_row("SELECT c.customer_id, c.mobile FROM sales_invoices v JOIN sales_customers c ON c.customer_id=v.customer_id
        WHERE v.invoice_no=? AND v.status='posted' AND RIGHT(REGEXP_REPLACE(c.mobile, '[^0-9]', ''), 10)=? LIMIT 1", 'ss', [$invoiceNo, $mobile]) : null;
    $taken = $match ? cp_row('SELECT 1 AS x FROM portal_accounts WHERE customer_id=?', 'i', [(int) $match['customer_id']]) : null;
    if (!$match || $taken) {
        cp_log_attempt(null, $mobile, false, 'signup_mismatch');
        cp_redirect('register.php', 'err', 'We could not create an account with these details. Check the mobile number and invoice number, or contact us if you already have an account.');
    }
    $login = $mobile;
    if (cp_row('SELECT 1 AS x FROM portal_accounts WHERE login=?', 's', [$login])) {
        $login = 'C' . (int) $match['customer_id'];
    }
    cp_insert("INSERT INTO portal_accounts (customer_id, login, password_hash, must_change_password, created_via) VALUES (?, ?, ?, 0, 'signup')", 'iss', [(int) $match['customer_id'], $login, password_hash($pass, PASSWORD_DEFAULT)]);
    cp_log_attempt(null, $login, true, 'signup');
    notify_admin('customer/portal_accounts.php', 'New portal signup', 'Login ' . $login . ' registered.');
    cp_redirect('login.php', 'ok', 'Your account is ready. Sign in with your mobile number' . ($login !== $mobile ? ' or login ' . $login : '') . '.');
}
cp_page_start('Create an account', '', true);
if (!$open) {
    echo '<div class="alert info">Accounts are created by our staff. Please contact us to get access.</div><p><a href="login.php">Back to sign in</a></p>';
} else {
    echo '<form method="post" class="card grid">' . cp_csrf_field()
        . '<div><label for="mobile">Mobile number on your invoices</label><input id="mobile" name="mobile" required inputmode="tel" autocomplete="tel"></div>'
        . '<div><label for="invoice_no">Any one of your invoice numbers</label><input id="invoice_no" name="invoice_no" required placeholder="e.g. SI2026000123"></div>'
        . '<div><label for="password">Choose a password (at least ' . CP_MIN_PASSWORD . ' characters)</label><input id="password" name="password" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div>'
        . '<div><label for="password2">Type it again</label><input id="password2" name="password2" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div>'
        . '<button class="btn">Create account</button></form><p><a href="login.php">Back to sign in</a></p>';
}
cp_page_end();
