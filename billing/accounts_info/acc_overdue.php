<?php
/** Over Due Permission: allow a party to stay over its due or credit limit up to an amount until a date. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Over Due Permission', 'odp');

$parties = [];
foreach (sales_rows($conn, "SELECT s.sub_ledger_id, s.sub_ledger_code, s.sub_ledger_name, a.account_name FROM acc_sub_ledgers s JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.is_active=1 ORDER BY a.account_type='receivable' DESC, s.sub_ledger_name") as $p) {
    $parties[(int) $p['sub_ledger_id']] = $p['sub_ledger_name'] . ' (' . $p['sub_ledger_code'] . ', ' . $p['account_name'] . ')';
}

acc_crud($conn, [
    'self' => 'acc_overdue.php', 'title' => 'Over Due Permission', 'menu' => 'odp',
    'table' => 'acc_overdue_permissions', 'pk' => 'permission_id',
    'fields' => [
        'sub_ledger_id' => ['label' => 'Party (Sub Ledger)', 'type' => 'select', 'options' => $parties, 'blank' => 'Pick party', 'required' => true, 'span' => 2],
        'permitted_amount' => ['label' => 'Permitted Amount', 'type' => 'decimal', 'required' => true, 'min' => 0],
        'valid_from' => ['label' => 'Valid From', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')],
        'valid_to' => ['label' => 'Valid To', 'type' => 'date', 'required' => true],
        'reason' => ['label' => 'Reason', 'max' => 255, 'span' => 2],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'validate' => function (&$v, $id) {
        if ($v['valid_to'] < $v['valid_from']) {
            throw new RuntimeException('Valid To cannot be before Valid From.');
        }
        $v['permitted_amount'] = round($v['permitted_amount'], 2);
        $v['approved_by'] = (int) ($GLOBALS['user_id'] ?? 0);
    },
    'list_sql' => 'SELECT p.*, s.sub_ledger_name, s.sub_ledger_code FROM acc_overdue_permissions p JOIN acc_sub_ledgers s ON s.sub_ledger_id=p.sub_ledger_id {where} ORDER BY p.valid_to DESC, p.permission_id DESC',
    'search' => ['s.sub_ledger_name', 's.sub_ledger_code', 'p.reason'],
    'columns' => [
        'sub_ledger_name' => ['Party', fn($r) => sales_h($r['sub_ledger_name'] . ' (' . $r['sub_ledger_code'] . ')')],
        'permitted_amount' => ['Permitted Amount', fn($r) => sales_money($r['permitted_amount']), true],
        'valid_from' => ['From', fn($r) => acc_fmt_date($r['valid_from'])],
        'valid_to' => ['To', fn($r) => acc_fmt_date($r['valid_to'])],
        'reason' => 'Reason',
        'status' => ['Status', fn($r) => !(int) $r['is_active'] ? '<span class="badge cancelled">Off</span>' : ($r['valid_to'] < date('Y-m-d') ? '<span class="badge">Expired</span>' : ($r['valid_from'] > date('Y-m-d') ? '<span class="badge open">Upcoming</span>' : '<span class="badge converted">Active</span>'))],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
