<?php
/** Profile: the SR's details as the office has them, and password change. */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('profile.php');
    $err = cp_auth_change_password(SR_AUTH, (int) $a['account_id'], $_POST['current_password'] ?? '', (string) ($_POST['new_password'] ?? ''), (string) ($_POST['new_password2'] ?? ''));
    $err === '' ? cp_redirect('index.php', 'ok', 'Your password has been changed.') : cp_redirect('profile.php', 'err', $err);
}
sr_page_start('Profile', 'profile');
$rights = array_filter([(int) $a['can_book_orders'] ? 'book orders' : '', (int) $a['can_collect'] ? 'record collections' : '', (int) $a['see_all_customers'] ? 'see all customers' : 'see your own customers']);
echo '<div class="card meta"><div><b>Name:</b> ' . cp_h($a['sr_name']) . '</div><div><b>SR code:</b> ' . cp_h($a['sr_code']) . '</div><div><b>Designation:</b> ' . cp_h($a['designation']) . '</div>'
    . '<div><b>Territory:</b> ' . cp_h($a['territory_name']) . '</div><div><b>Mobile:</b> ' . cp_h($a['mobile']) . '</div><div><b>Login:</b> ' . cp_h($a['login']) . '</div>'
    . '<div><b>Commission:</b> ' . ($a['commission_basis'] === 'none' ? 'None' : cp_h((float) $a['commission_rate'] . '% of ' . str_replace('_', ' ', $a['commission_basis'])) . ((float) $a['target_bonus_rate'] ? ' + ' . (float) $a['target_bonus_rate'] . '% bonus at target' : '')) . '</div>'
    . '<div><b>You can:</b> ' . cp_h(implode(', ', $rights)) . '</div></div>';
echo '<form method="post" class="card grid">' . cp_csrf_field() . '<h2>Change password</h2>'
    . ((int) $a['must_change_password'] ? '<div class="alert info">Please replace the password the office gave you with your own.</div>' : '')
    . '<div><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>'
    . '<div class="row"><div><label for="new_password">New password (at least ' . CP_MIN_PASSWORD . ' characters)</label><input id="new_password" name="new_password" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div>'
    . '<div><label for="new_password2">Type it again</label><input id="new_password2" name="new_password2" type="password" required minlength="' . CP_MIN_PASSWORD . '" autocomplete="new-password"></div></div><div><button class="btn">Change password</button></div></form>';
sr_page_end();
