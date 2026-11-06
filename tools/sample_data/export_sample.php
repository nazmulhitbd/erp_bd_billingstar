<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Writes billing_star/install_script/sql/sample_data.sql: every row the seeding added to a fresh install, as plain INSERTs.
// Install-specific and security rows are left out; user references become @sample_user (set by the installer).
require __DIR__ . '/diff_db.php';

const SKIP_TABLES = ['admin_user', 'audit_log', 'bus_admin_audit', 'login_log', 'audit_settings', 'bs_tax_rates', 'security_throttle', 'licence_state', 'licence_locale'];
const SKIP_ROWS = ['acc_settings' => ['setting_key' => ['licence_host']]];
const USER_COLUMNS = ['created_by', 'posted_by', 'approved_by', 'received_by', 'sent_by', 'updated_by', 'uploaded_by', 'printed_by', 'verified_by', 'decided_by', 'assigned_by', 'user_id'];
define('OUT', dirname(__DIR__, 2) . '/install_script/sql/sample_data.sql');

$db = dd_db();
$q = fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'";
$sql = [];
$count = 0;
foreach (dd_diff() as $table => $d) {
    if (in_array($table, SKIP_TABLES, true)) {
        continue;
    }
    if ($d['changed'] || $d['removed']) {
        throw new RuntimeException("$table: the sample changes rows a fresh install already has; decide how to ship that.");
    }
    $rows = array_values(array_filter($d['new'], function ($r) use ($table) {
        foreach (SKIP_ROWS[$table] ?? [] as $col => $values) {
            if (in_array((string) $r[$col], $values, true)) {
                return false;
            }
        }
        return true;
    }));
    if (!$rows) {
        continue;
    }
    $generated = array_column($db->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='bs_sample' AND table_name='$table' AND EXTRA LIKE '%GENERATED%'")->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
    if ($table === 'sales_stores') { // matched by name: the install's own first store may have another name
        $sql[] = "INSERT IGNORE INTO `sales_stores` (`store_name`) VALUES ('Main Store'), " . implode(', ', array_map(fn($r) => '(' . $q($r['store_name']) . ')', $rows)) . ';';
        $count += count($rows);
        continue;
    }
    $cols = array_values(array_diff(array_keys($rows[0]), $generated));
    $values = [];
    foreach ($rows as $r) {
        $values[] = '(' . implode(', ', array_map(fn($c) => in_array($c, USER_COLUMNS, true) && (string) $r[$c] === '1' ? '@sample_user' : $q($r[$c]), $cols)) . ')';
    }
    $head = "INSERT INTO `$table` (`" . implode('`, `', $cols) . '`) VALUES';
    $tail = $table === 'doc_counters' ? "\nON DUPLICATE KEY UPDATE `" . $cols[2] . '`=GREATEST(`' . $cols[2] . '`, VALUES(`' . $cols[2] . '`))' : '';
    foreach (array_chunk($values, 100) as $chunk) {
        $sql[] = $head . "\n" . implode(",\n", $chunk) . $tail . ';';
    }
    $count += count($rows);
    echo str_pad($table, 30), count($rows), "\n";
}

$header = <<<'SQL'
-- PetaBill ERP: sample data
-- Copyright (c) 2015-2026 Petacore Technology. All rights reserved. PetaBill is a trademark of Petacore Technology.
--
-- A demo electronics shop in Bangladesh (September to November 2026): products, suppliers, customers, purchases,
-- sales invoices, POS, orders, returns, quotation, challan, stock transfer and amendments, approvals, vouchers,
-- employees, attendance, leave, sales representatives and targets, a service complaint and an announcement.
-- Every company, person, brand, phone number and address here is invented. Phone numbers use 0100000xxxx (no operator uses 010) and
-- e-mail addresses use example.com, so nothing reaches a real person.
--
-- Built through the application's own forms on a fresh install, then exported (install_script loads it when
-- "Start with sample data" is ticked). It expects a freshly installed, empty database, and @sample_user set to
-- the administrator's user_id. Do not edit by hand: the numbers, stock and ledgers agree with each other.
SET @sample_user = COALESCE(@sample_user, 1);
SET FOREIGN_KEY_CHECKS = 0;

SQL;
file_put_contents(OUT, $header . implode("\n\n", $sql) . "\n\nSET FOREIGN_KEY_CHECKS = 1;\n");
printf("%d rows, %s KB -> %s\n", $count, number_format(filesize(OUT) / 1024, 1), OUT);
