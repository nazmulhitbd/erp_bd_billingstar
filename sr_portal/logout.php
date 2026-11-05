<?php
/** Sign out. */
require_once __DIR__ . '/sr_common.php';
cp_sign_out();
cp_redirect('login.php', 'ok', 'You have signed out.');
