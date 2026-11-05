<?php
/** Second step of the SR / staff sign-in: the code sent by SMS or email (addon/inc/two_step.php). login.php sends people here. */
require_once __DIR__ . '/sr_common.php';
require_once __DIR__ . '/../addon/inc/two_step.php';

$t = sr_ready() ? tfa_pending() : null;
if (!$t) {
    cp_redirect('login.php', 'err', 'This sign-in has expired. Sign in again.');
}
// Each message goes back to this page while the sign-in is still parked, else to the password page.
$back = fn($kind, $message) => cp_redirect(tfa_pending() ? 'verify.php' : 'login.php', $kind, $message);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('verify.php');
    if (($_POST['action'] ?? '') === 'resend') {
        $error = tfa_resend();
        $error === '' ? $back('ok', 'A new code is on its way.') : $back('err', $error);
    }
    $result = tfa_verify($_POST['code'] ?? '');
    if (!is_array($result)) {
        $back('err', $result);
    }
    $_SESSION['sr_account'] = (int) $result['account_id'];
    $_SESSION['cp_seen'] = time();
    cp_redirect(perm_portal_open_shell('employee', $_SESSION['sr_account']) ? '../billing/topmain.php' : 'index.php');
}
sr_page_start('Verify sign-in', '', true);
echo '<p class="muted">' . cp_h(tfa_sent_text($t)) . '</p>' . tfa_portal_form(cp_csrf_field());
sr_page_end();
