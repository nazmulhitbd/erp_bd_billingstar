<?php
/** Sign out (POST would be stricter, but signing out carries no risk, so a plain link is accepted). */
require_once __DIR__ . '/portal_common.php';
cp_sign_out();
cp_redirect('login.php', 'ok', 'You have signed out.');
