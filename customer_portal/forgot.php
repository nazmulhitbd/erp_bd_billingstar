<?php
/**
 * Forgot password: sends a password request to staff (Customer Management > Portal Requests), who reset the password
 * and tell the customer. Nothing is revealed about whether the mobile number has an account.
 */
require_once __DIR__ . '/portal_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && cp_ready()) {
    cp_check_csrf('forgot.php');
    $mobile = cp_post('mobile', 40);
    $recent = (int) cp_row("SELECT COUNT(*) AS n FROM portal_requests WHERE request_type='password' AND contact=? AND created_at > NOW() - INTERVAL 1 DAY", 's', [$mobile])['n'];
    if (strlen(cp_phone_key($mobile)) === 10 && $recent < 3) {
        $acc = cp_row("SELECT a.account_id, a.customer_id FROM portal_accounts a JOIN sales_customers c ON c.customer_id=a.customer_id WHERE RIGHT(REGEXP_REPLACE(c.mobile, '[^0-9]', ''), 10)=? LIMIT 1", 's', [cp_phone_key($mobile)]);
        $id = cp_insert("INSERT INTO portal_requests (request_no, customer_id, account_id, request_type, subject, contact) VALUES (?, ?, ?, 'password', 'Password reset request', ?)", 'siis',
            [cp_request_no(), $acc['customer_id'] ?? null, $acc['account_id'] ?? null, $mobile]);
        notify_admin('customer/portal_requests.php', 'Password reset requested', 'Mobile ' . $mobile);
        cp_insert("INSERT INTO portal_request_messages (request_id, sender, body) VALUES (?, 'customer', ?)", 'is', [$id, cp_post('note', 1000) ?: 'Please reset my portal password.']);
    }
    cp_redirect('login.php', 'ok', 'Thank you. If this number has an account, our staff will reset the password and contact you.');
}
cp_page_start('Forgot password', '', true);
$c = cp_company();
echo '<form method="post" class="card grid">' . cp_csrf_field()
    . '<div><label for="mobile">Your mobile number</label><input id="mobile" name="mobile" required inputmode="tel"></div>'
    . '<div><label for="note">Anything we should know (optional)</label><textarea id="note" name="note" maxlength="1000"></textarea></div>'
    . '<button class="btn">Send request</button></form>'
    . ($c['phone'] ? '<p class="muted">You can also call us on ' . cp_h($c['phone']) . '.</p>' : '') . '<p><a href="login.php">Back to sign in</a></p>';
cp_page_end();
