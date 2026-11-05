<?php
/** Edition switches. Hosted copy: replace module.php with this file on a shared server. */
$issetvalue = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$mobileVersion = isMobile($issetvalue);
include_once(dirname(__FILE__) . '/../globalization/global.php');
$lang = "Arobi";

$finalversion = 1;  // 0 = demo (no session check on sign-in, no sign out), 1 = full
$multilog_version = 1;  // 1 = the same user may be signed in from two places at once
