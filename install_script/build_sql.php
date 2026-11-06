<?php
/**
 * Rebuilds sql/install.sql, the one file a new install runs, after a module's schema.sql changes:  php install_script/build_sql.php
 * The database structure is kept from the current file; every other section is read again from its module. The list order
 * is the order they run in. Each "-- SECTION:" line starts a part the installer runs on its own connection.
 * Names only MySQL 8 knows are replaced, so the file also imports on MariaDB, where a single unknown name stops the import.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
const BUILD_MODULES = [
    'Sales' => 'billing/sales/schema.sql',
    'Sales orders' => 'billing/sales/sales_order/schema.sql',
    'Accounts' => 'billing/accounts_info/schema.sql',
    'Inventory' => 'billing/inventory/schema.sql',
    'Purchase' => 'billing/purchase/schema.sql',
    'Sales documents' => 'billing/sales/schema_v2.sql',
    'Sales returns' => 'billing/sales/sales_retrun/schema.sql',
    'Bills from orders' => 'billing/sales/sal_bill_from_sal_order/schema.sql',
    'Order adjustments' => 'billing/sales/sales_order_adjustment/schema.sql',
    'Challan' => 'billing/challan/schema.sql',
    'Price history' => 'billing/price_history/schema.sql',
    'Products' => 'billing/product/schema.sql',
    'Traders' => 'billing/trader/schema.sql',
    'Customers' => 'billing/customer/schema.sql',
    'Sales team' => 'billing/sales_person/schema.sql',
    'Customer portal' => 'customer_portal/schema.sql',
    'SR portal' => 'sr_portal/schema.sql',
    'Service (RMA)' => 'billing/rma/schema.sql',
    'Reports' => 'billing/report/schema.sql',
    'Users and permissions' => 'billing/user/schema.sql',
    'HR' => 'billing/hr/schema.sql',
    'Audit' => 'billing/audit/schema.sql',
];
const BUILD_NAMES = ['utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci', 'utf8mb3_unicode_ci' => 'utf8_unicode_ci', 'utf8mb3_general_ci' => 'utf8_general_ci', 'utf8mb3' => 'utf8'];
$root = dirname(__DIR__);
$target = __DIR__ . '/sql/install.sql';
$base = $argv[1] ?? '';
if ($base !== '') { // first build only: php build_sql.php path/to/base_schema.sql
    $structure = file_get_contents($base);
} else {
    $parts = preg_split('/^-- SECTION: .*$/m', (string) file_get_contents($target), 3);
    $structure = ltrim($parts[1] ?? '');
}
if ($structure === false || $structure === '') {
    exit("The database structure section was not found.\n");
}
$out = "-- PetaBill: everything a new installation runs, in order. Made by build_sql.php: change the module schema, then rebuild.\n-- SECTION: Database structure\n" . rtrim($structure) . "\n";
foreach (BUILD_MODULES as $label => $file) {
    $sql = file_get_contents("$root/$file");
    if ($sql === false || preg_match('/^-- SECTION: /m', $sql)) {
        exit("Could not use $file.\n");
    }
    $out .= "-- SECTION: $label\n-- From $file\n" . rtrim($sql) . "\n";
}
$out = strtr($out, BUILD_NAMES);
file_put_contents($target, $out, LOCK_EX);
echo 'Wrote sql/install.sql: ' . (count(BUILD_MODULES) + 1) . ' sections, ' . number_format(strlen($out)) . " bytes.\n";
