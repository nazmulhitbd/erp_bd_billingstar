<?php
/** Sign in with the portal login or mobile number and password. */
require_once __DIR__ . '/portal_common.php';
require_once __DIR__ . '/../addon/inc/two_step.php';

licence_splash();
if (cp_ready() && cp_account()) {
    cp_redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && cp_ready()) {
    cp_check_csrf('login.php');
    [$ok, $message] = cp_sign_in(cp_post('login', 60), (string) ($_POST['password'] ?? ''));
    if ($ok && ($tfa = tfa_portal_hold('customer', 'cp_account', cp_post('login', 60))) !== null) { // two-step verification: Settings > User Config
        $tfa === '' ? cp_redirect('verify.php') : cp_redirect('login.php', 'err', $tfa);
    }
    if ($ok && perm_portal_open_shell('customer', $_SESSION['cp_account'])) {
        cp_redirect('../billing/topmain.php');
    }
    $ok ? cp_redirect('index.php') : cp_redirect('login.php', 'err', $message);
}
cp_page_start('Sign in', '', true);
if (!cp_ready()) {
    echo '<div class="alert err">The customer portal is not installed yet.</div>';
} else {
    echo '<p class="muted">' . cp_h(cp_setting('welcome_text')) . '</p>';
    echo '<form method="post" class="card grid">' . cp_csrf_field()
        . '<div><label for="login">Mobile number or login</label><input id="login" name="login" required autocomplete="username" autofocus></div>'
        . '<div><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>'
        . '<button class="btn">Sign in</button></form>';
    echo '<p><a href="forgot.php">Forgot your password?</a>' . (cp_setting('allow_signup', '1') === '1' ? ' · <a href="register.php">Create an account</a>' : '') . '</p>';
}
cp_page_end();
