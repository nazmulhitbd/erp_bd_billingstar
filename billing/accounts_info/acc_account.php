<?php
/** General Accounts: ledger accounts in the chart of accounts. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'General Accounts', 'account');

$groups = [];
foreach (acc_group_tree($conn) as $gid => $g) {
    $groups[$gid] = $g['path'];
}
$move = acc_account_movements($conn, '1900-01-01', '2100-12-31');

acc_crud($conn, [
    'self' => 'acc_account.php', 'title' => 'General Accounts', 'menu' => 'account',
    'table' => 'acc_accounts', 'pk' => 'account_id',
    'fields' => [
        'group_id' => ['label' => 'Accounts Group', 'type' => 'select', 'options' => $groups, 'blank' => 'Pick group', 'required' => true, 'span' => 2],
        'account_code' => ['label' => 'Account Code', 'required' => true, 'max' => 20],
        'account_name' => ['label' => 'Account Name', 'required' => true, 'max' => 150],
        'account_type' => ['label' => 'Account Type', 'type' => 'select', 'options' => ACC_TYPES, 'string' => true, 'required' => true, 'help' => 'Cash and Bank feed the cash/bank reports. Receivable and Payable feed the due lists.'],
        'has_sub_ledger' => ['label' => 'Has sub ledgers (party accounts)', 'type' => 'check', 'default' => 0],
        'bank_name' => ['label' => 'Bank Name', 'max' => 120],
        'bank_account_no' => ['label' => 'Bank Account No', 'max' => 60],
        'opening_balance' => ['label' => 'Opening Balance', 'type' => 'decimal', 'default' => '0', 'min' => 0],
        'opening_side' => ['label' => 'Opening Dr/Cr', 'type' => 'select', 'options' => ['Dr' => 'Dr', 'Cr' => 'Cr'], 'string' => true],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'validate' => function (&$v, $id) use ($conn) {
        $v['opening_balance'] = round($v['opening_balance'], 2);
        if ($id > 0 && !$v['has_sub_ledger'] && sales_row($conn, 'SELECT 1 FROM acc_sub_ledger_groups WHERE account_id=? LIMIT 1', 'i', [$id])) {
            throw new RuntimeException('This account already has sub ledger groups, so "Has sub ledgers" must stay ticked.');
        }
    },
    'delete_check' => function ($id) use ($conn) {
        if (sales_row($conn, 'SELECT 1 FROM acc_voucher_lines WHERE account_id=? LIMIT 1', 'i', [$id])) {
            return 'This account is used in vouchers. Untick Active instead.';
        }
        if (sales_row($conn, 'SELECT 1 FROM acc_sub_ledger_groups WHERE account_id=? LIMIT 1', 'i', [$id])) {
            return 'This account has sub ledger groups. Delete them first.';
        }
        if (sales_row($conn, "SELECT setting_key FROM acc_settings WHERE setting_key LIKE '%account' AND setting_value=?", 's', [(string) $id])) {
            return 'This account is used in Accounts Configuration. Pick another account there first.';
        }
        return '';
    },
    'list_sql' => 'SELECT a.*, g.group_name, g.nature FROM acc_accounts a JOIN acc_groups g ON g.group_id=a.group_id {where} ORDER BY a.account_code',
    'search' => ['a.account_code', 'a.account_name', 'g.group_name', 'a.bank_account_no'],
    'columns' => [
        'account_code' => 'Code',
        'account_name' => 'Account Name',
        'group_name' => 'Group',
        'account_type' => ['Type', fn($r) => sales_h(ACC_TYPES[$r['account_type']]) . ((int) $r['has_sub_ledger'] ? ' <span class="badge">sub ledgers</span>' : '')],
        'opening_balance' => ['Opening', fn($r) => sales_money($r['opening_balance']) . ' ' . $r['opening_side'], true],
        'balance' => ['Current Balance', function ($r) use ($move) {
            $m = $move[(int) $r['account_id']] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
            return acc_drcr($m['open'] + $m['dr'] - $m['cr']);
        }, true],
        'is_active' => ['Active', fn($r) => (int) $r['is_active'] ? 'Yes' : 'No'],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
