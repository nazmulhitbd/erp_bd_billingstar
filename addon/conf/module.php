<?php
/** Edition switches. This copy: module.php, the one dbconnect.php loads. */
$issetvalue = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$mobileVersion = isMobile($issetvalue);
include_once(dirname(__FILE__) . '/../globalization/global.php');
$lang = "Arobi";

$finalversion = 1;  // 0 = demo (no session check on sign-in, no sign out), 1 = full
$multilog_version = (int) module(9);  // 1 = the same user may be signed in from two places at once
