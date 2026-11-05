<?php
/** SR / Staff sign-in with the portal login or mobile number. Accounts are created by the office (Sales Person Management › SR Portal Accounts). */
require_once __DIR__ . '/sr_common.php';
require_once __DIR__ . '/../addon/inc/two_step.php';

licence_splash();
if (sr_ready() && sr_account()) {
    cp_redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sr_ready()) {
    cp_check_csrf('login.php');
    [$ok, $message] = cp_auth_sign_in(SR_AUTH, cp_post('login', 60), (string) ($_POST['password'] ?? ''));
    if ($ok && ($tfa = tfa_portal_hold('employee', 'sr_account', cp_post('login', 60))) !== null) { // two-step verification: Settings > User Config
        $tfa === '' ? cp_redirect('verify.php') : cp_redirect('login.php', 'err', $tfa);
    }
    if ($ok && perm_portal_open_shell('employee', $_SESSION['sr_account'])) {
        cp_redirect('../billing/topmain.php');
    }
    $ok ? cp_redirect('index.php') : cp_redirect('login.php', 'err', $message);
}
sr_page_start('SR / Staff sign in', '', true);
if (!sr_ready()) {
    echo '<div class="alert err">The SR portal is not installed yet.</div>';
} else {
    echo '<form method="post" class="card grid">' . cp_csrf_field()
        . '<div><label for="login">Login or mobile number</label><input id="login" name="login" required autocomplete="username" autofocus></div>'
        . '<div><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>'
        . '<button class="btn">Sign in</button></form><p class="muted">Forgot your password? Ask the office to reset it.</p>';
}
sr_page_end();
