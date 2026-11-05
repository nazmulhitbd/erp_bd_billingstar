<?php
/**
 * Profile: the customer's details as the shop has them, and password change. Detail changes go to staff as an
 * account request (the shop keeps the customer record), the password is changed here.
 */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('profile.php');
    if (($_POST['action'] ?? '') === 'password') {
        $err = cp_auth_change_password(CP_AUTH, (int) $a['account_id'], $_POST['current_password'] ?? '', (string) ($_POST['new_password'] ?? ''), (string) ($_POST['new_password2'] ?? ''));
        $err === '' ? cp_redirect('index.php', 'ok', 'Your password has been changed.') : cp_redirect('profile.php#password', 'err', $err);
    }
    $text = cp_post('changes', 2000);
    if ($text === '') {
        cp_redirect('profile.php', 'err', 'Write what should change.');
    }
    $id = cp_insert("INSERT INTO portal_requests (request_no, customer_id, account_id, request_type, subject) VALUES (?, ?, ?, 'account', 'Update my details')", 'sii', [cp_request_no(), (int) $a['customer_id'], (int) $a['account_id']]);
    cp_insert("INSERT INTO portal_request_messages (request_id, sender, body) VALUES (?, 'customer', ?)", 'is', [$id, $text]);
    notify_admin('customer/portal_requests.php', 'Customer asked to update details', mb_substr($text, 0, 160));
    cp_redirect('requests.php?id=' . $id, 'ok', 'Thank you. We will update your details and reply here.');
}

cp_page_start('Profile', 'profile');
echo '<div class="card meta"><div><b>Name:</b> ' . cp_h($a['customer_name']) . '</div><div><b>Customer code:</b> ' . cp_h($a['trader_code'] ?: $a['customer_code']) . '</div><div><b>Mobile:</b> ' . cp_h($a['mobile']) . '</div>'
    . '<div><b>Group:</b> ' . cp_h($a['customer_group']) . '</div><div><b>Address:</b> ' . cp_h($a['address']) . '</div><div><b>Contact person:</b> ' . cp_h($a['attention']) . '</div>'
    . '<div><b>Sales contact:</b> ' . cp_h($a['sr_name'] ?? '') . '</div><div><b>Portal login:</b> ' . cp_h($a['login']) . '</div></div>';
echo '<form method="post" class="card grid">' . cp_csrf_field() . '<h2>Ask us to update your details</h2><div><label for="changes">What should change?</label><textarea id="changes" name="changes" maxlength="2000" placeholder="e.g. new address or mobile number"></textarea></div><div><button class="btn light">Send to staff</button></div></form>';
echo '<form method="post" class="card grid" id="password">' . cp_csrf_field() . '<input type="hidden" name="action" value="password"><h2>Change password</h2>'
    . ((int) $a['must_change_password'] ? '<div class="alert info">Please replace the password you were given with your own.</div>' : '')
    . '<div><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>'
    . '<div class="row"><div><label for="new_password">New password (at least ' . CP_MIN_PASSWORD . ' characters)</label><input id="new_password" name="new_password" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div>'
    . '<div><label for="new_password2">Type it again</label><input id="new_password2" name="new_password2" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div></div><div><button class="btn">Change password</button></div></form>';
cp_page_end();
