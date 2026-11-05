<?php
/** Transaction Category: names for the kinds of transactions in each module (Cash Sales, Credit Purchase, ...); one default per module. */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Transaction Category', 'active' => 'transaction', 'table' => 'transaction_categories', 'pk' => 'tc_id', 'order' => "FIELD(module,'sales','purchase','inventory','accounts','rma'), category_name",
    'fields' => ['module' => ['Module', 'select', ['options' => ['sales' => 'Sales', 'purchase' => 'Purchase', 'inventory' => 'Inventory', 'accounts' => 'Accounts', 'rma' => 'RMA'], 'required' => true]],
        'category_name' => ['Category Name', 'text', ['max' => 120, 'required' => true]], 'description' => ['Description', 'textarea', ['max' => 255]],
        'is_default' => ['Default', 'check', ['default' => 0]], 'is_active' => ['Active', 'check']],
    'unique' => ['module', 'category_name'],
    'after_save' => function (mysqli $db, ?array $old, array $new) {
        if ((int) $new['is_default']) {
            sales_exec($db, 'UPDATE transaction_categories SET is_default=0 WHERE module=? AND tc_id<>?', 'si', [$new['module'], (int) $new['tc_id']])->close();
        }
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
