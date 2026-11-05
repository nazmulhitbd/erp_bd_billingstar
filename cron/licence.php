<?php
/**
 * Licence check from cron, so visitors' page loads (and bots) do not call the licence panel. Run every minute:
 *   * * * * * php /home/USER/public_html/cron/licence.php >/dev/null 2>&1
 * Without this the pages still check by themselves, only less often. Command line only.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
if (!is_file("$root/addon/display/dbconfig.php")) {
    exit(0); // not installed yet
}
chdir($root);
require_once "$root/addon/inc/security.php";
try {
    require_once "$root/addon/display/dbconfig.php";
} catch (Throwable $e) {
    fwrite(STDERR, 'licence cron: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
require_once "$root/addon/inc/licence.php";
audit_boot(); // the web gate starts the audit trail; here it is started by hand, so a change of licence state is still recorded
licence_tick(true);
$status = licence_status();
$error = (string) (licence_row()['last_error'] ?? '');
echo $status['state'] . ($error !== '' ? " ($error)" : '') . PHP_EOL;
