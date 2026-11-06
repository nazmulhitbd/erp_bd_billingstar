<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Runs the installer of the sample-build copy against the private MySQL (port 3399), the way index.php's steps do.
require dirname(__DIR__, 3) . '/bs_sample_build/install_script/install_lib.php';
$db = ['host' => '127.0.0.1', 'port' => 3399, 'name' => 'bs_sample', 'user' => 'bsapp', 'pass' => 'SampleBuild2026x'];
[$conn, $err] = inst_connect($db);
if (!$conn) { exit("connect: $err\n"); }
$t = microtime(true);
foreach (array_keys(inst_sections()) as $label) {
    $e = inst_run_sql($conn, $label);
    echo str_pad($label, 26), $e === '' ? 'ok' : "FAILED: $e", "\n";
    if ($e !== '') { exit(1); }
}
inst_save_setup($conn, ['name' => 'Sample Trading Ltd.', 'brand' => 'Sample Trading', 'slogan' => 'Demo company', 'address' => '12 Sample Road, Dhaka', 'phone' => '02-0000000', 'mobile' => '01700000000',
    'email' => 'info@example.com', 'website' => 'example.com', 'bin' => '000000000-0000', 'branch' => 'Head Office', 'store' => 'Main Store'],
    ['name' => 'Demo Administrator', 'user' => 'admin', 'email' => 'admin@example.com', 'mobile' => '01700000001', 'hash' => password_hash('SampleAdmin2026', PASSWORD_DEFAULT)]);
echo "setup ok\n", inst_write_config($db) ?: "config written\n";
printf("%.1fs\n", microtime(true) - $t);
