<?php
/** MoneyReceiptCategory: receipt types picked on credit (receipt) vouchers. The receipt print is titled with it. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Money Receipt Category', 'mrc');

$accounts = [];
foreach (acc_accounts($conn) as $a) {
    $accounts[(int) $a['account_id']] = $a['account_code'] . ' - ' . $a['account_name'];
}

acc_crud($conn, [
    'self' => 'acc_receipt_category.php', 'title' => 'Money Receipt Category', 'menu' => 'mrc',
    'table' => 'acc_money_receipt_categories', 'pk' => 'category_id',
    'fields' => [
        'category_name' => ['label' => 'Category Name', 'required' => true, 'max' => 120],
        'account_id' => ['label' => 'Default Credit Account', 'type' => 'select', 'options' => $accounts, 'blank' => 'None', 'nullable' => true],
        'note' => ['label' => 'Note', 'max' => 255],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'delete_check' => fn($id) => sales_row($conn, 'SELECT 1 FROM acc_vouchers WHERE receipt_category_id=? LIMIT 1', 'i', [$id]) ? 'Vouchers use this category. Untick Active instead.' : '',
    'list_sql' => 'SELECT c.*, COALESCE(a.account_name, \'\') AS account_name FROM acc_money_receipt_categories c LEFT JOIN acc_accounts a ON a.account_id=c.account_id {where} ORDER BY c.category_name',
    'search' => ['c.category_name'],
    'columns' => [
        'category_id' => 'ID',
        'category_name' => 'Category Name',
        'account_name' => 'Default Credit Account',
        'note' => 'Note',
        'is_active' => ['Active', fn($r) => (int) $r['is_active'] ? 'Yes' : 'No'],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
