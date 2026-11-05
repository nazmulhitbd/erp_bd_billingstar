<?php
/** Accounts Sub Ledger Group: area or branch groups of parties under a control account (e.g. Elephant Road under Sundry Creditors). */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Accounts Sub Ledger Group', 'slg');

$controls = [];
foreach (acc_accounts($conn, true, 'a.has_sub_ledger=1') as $a) {
    $controls[(int) $a['account_id']] = $a['account_code'] . ' - ' . $a['account_name'];
}

acc_crud($conn, [
    'self' => 'acc_sub_ledger_group.php', 'title' => 'Accounts Sub Ledger Group', 'menu' => 'slg',
    'table' => 'acc_sub_ledger_groups', 'pk' => 'slg_id',
    'before_form' => $controls ? '' : '<div class="alert error">No control account yet. In General Accounts, tick "Has sub ledgers" on an account such as Sundry Debtors.</div>',
    'fields' => [
        'account_id' => ['label' => 'Control Account', 'type' => 'select', 'options' => $controls, 'blank' => 'Pick account', 'required' => true],
        'group_name' => ['label' => 'Group Name', 'required' => true, 'max' => 120],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'validate' => function (&$v, $id) use ($conn) {
        if ($id > 0) {
            $old = sales_row($conn, 'SELECT account_id FROM acc_sub_ledger_groups WHERE slg_id=?', 'i', [$id]);
            if ($old && (int) $old['account_id'] !== $v['account_id'] && sales_row($conn, 'SELECT 1 FROM acc_sub_ledgers WHERE slg_id=? LIMIT 1', 'i', [$id])) {
                throw new RuntimeException('This group has sub ledgers, so its control account cannot change.');
            }
        }
    },
    'delete_check' => fn($id) => sales_row($conn, 'SELECT 1 FROM acc_sub_ledgers WHERE slg_id=? LIMIT 1', 'i', [$id]) ? 'This group has sub ledgers. Move or delete them first.' : '',
    'list_sql' => 'SELECT s.*, a.account_name, (SELECT COUNT(*) FROM acc_sub_ledgers l WHERE l.slg_id=s.slg_id) AS parties FROM acc_sub_ledger_groups s JOIN acc_accounts a ON a.account_id=s.account_id {where} ORDER BY a.account_code, s.group_name',
    'search' => ['s.group_name', 'a.account_name'],
    'columns' => [
        'slg_id' => 'Group ID',
        'account_name' => 'Control Account',
        'group_name' => 'Group Name',
        'parties' => ['Sub Ledgers', fn($r) => (int) $r['parties'], true],
        'is_active' => ['Active', fn($r) => (int) $r['is_active'] ? 'Yes' : 'No'],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
