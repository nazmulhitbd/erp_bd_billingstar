<?php
/** Accounts Sub Ledger: party accounts (customers, suppliers ...) under a sub ledger group. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Accounts Sub Ledger', 'sl');

$groups = [];
$groupAccount = [];
foreach (sales_rows($conn, 'SELECT s.slg_id, s.account_id, s.group_name, a.account_name FROM acc_sub_ledger_groups s JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.is_active=1 ORDER BY a.account_code, s.group_name') as $g) {
    $groups[(int) $g['slg_id']] = $g['account_name'] . ' / ' . $g['group_name'];
    $groupAccount[(int) $g['slg_id']] = (int) $g['account_id'];
}
$tradersReady = sales_tables_ready($conn, ['traders', 'trader_categories']);
$move = acc_sub_ledger_movements($conn, '1900-01-01', '2100-12-31');

/** Create sub ledgers for traders that do not have one yet. */
function acc_import_traders(mysqli $db)
{
    $receivable = (int) acc_setting($db, 'receivable_account');
    $payable = (int) acc_setting($db, 'payable_account');
    if (!$receivable || !$payable) {
        throw new RuntimeException('Set the receivable and payable accounts in Accounts Configuration first.');
    }
    $traders = sales_rows($db, "SELECT t.trader_id, t.trader_code, t.trader_name, t.mobile, t.address, t.opening_balance, c.kind, COALESCE(g.group_name, '') AS group_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE NOT EXISTS (SELECT 1 FROM acc_sub_ledgers s WHERE s.trader_id=t.trader_id) ORDER BY t.trader_id");
    $db->begin_transaction();
    $made = 0;
    foreach ($traders as $t) {
        $account = $t['kind'] === 'supplier' ? $payable : $receivable;
        $groupName = $t['group_name'] !== '' ? $t['group_name'] : ($t['kind'] === 'supplier' ? 'General Suppliers' : 'General Customers');
        $slg = sales_row($db, 'SELECT slg_id FROM acc_sub_ledger_groups WHERE account_id=? AND group_name=?', 'is', [$account, $groupName]);
        $slgId = $slg ? (int) $slg['slg_id'] : sales_insert($db, 'INSERT INTO acc_sub_ledger_groups (account_id, group_name) VALUES (?, ?)', 'is', [$account, $groupName]);
        $code = (string) $t['trader_code'];
        if ($code === '' || sales_row($db, 'SELECT 1 FROM acc_sub_ledgers WHERE sub_ledger_code=?', 's', [$code])) {
            $code = 'TR' . str_pad((string) $t['trader_id'], 6, '0', STR_PAD_LEFT);
        }
        // Trader opening balance is what the party owes us (customers) or we owe them (suppliers).
        $opening = (float) $t['opening_balance'];
        $side = ($t['kind'] === 'supplier') === ($opening >= 0) ? 'Cr' : 'Dr';
        sales_insert(
            $db,
            'INSERT INTO acc_sub_ledgers (slg_id, account_id, sub_ledger_code, sub_ledger_name, address, mobile, opening_balance, opening_side, trader_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iissssdsi',
            [$slgId, $account, $code, sales_cut($t['trader_name'], 190), sales_cut((string) $t['address'], 255), sales_cut($t['mobile'], 40), round(abs($opening), 2), $side, (int) $t['trader_id']]
        );
        $made++;
    }
    $db->commit();
    return $made ? $made . ' trader(s) imported as sub ledgers.' : 'Every trader already has a sub ledger.';
}

acc_crud($conn, [
    'self' => 'acc_sub_ledger.php', 'title' => 'Accounts Sub Ledger', 'menu' => 'sl',
    'table' => 'acc_sub_ledgers', 'pk' => 'sub_ledger_id',
    'before_form' => $groups ? '' : '<div class="alert error">Add an Accounts Sub Ledger Group first.</div>',
    'fields' => [
        'slg_id' => ['label' => 'Group Name', 'type' => 'select', 'options' => $groups, 'blank' => 'Pick group', 'required' => true, 'span' => 2],
        'sub_ledger_code' => ['label' => 'Code', 'max' => 30, 'help' => 'Leave blank to number it automatically.'],
        'sub_ledger_name' => ['label' => 'Name', 'required' => true, 'max' => 190],
        'address' => ['label' => 'Address', 'max' => 255, 'span' => 2],
        'mobile' => ['label' => 'Mobile', 'max' => 40],
        'opening_balance' => ['label' => 'Opening Balance', 'type' => 'decimal', 'default' => '0', 'min' => 0],
        'opening_side' => ['label' => 'Opening Dr/Cr', 'type' => 'select', 'options' => ['Dr' => 'Dr (party owes us)', 'Cr' => 'Cr (we owe party)'], 'string' => true],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'validate' => function (&$v, $id) use ($conn, $groupAccount) {
        $v['account_id'] = $groupAccount[$v['slg_id']] ?? 0;
        if (!$v['account_id']) {
            throw new RuntimeException('Pick an active group.');
        }
        if ($id > 0) {
            $old = sales_row($conn, 'SELECT account_id FROM acc_sub_ledgers WHERE sub_ledger_id=?', 'i', [$id]);
            if ($old && (int) $old['account_id'] !== $v['account_id'] && sales_row($conn, 'SELECT 1 FROM acc_voucher_lines WHERE sub_ledger_id=? LIMIT 1', 'i', [$id])) {
                throw new RuntimeException('This sub ledger has vouchers, so it can only move to a group under the same control account.');
            }
        }
        $v['opening_balance'] = round($v['opening_balance'], 2);
        if ($v['sub_ledger_code'] === '') {
            $v['sub_ledger_code'] = 'TMP' . bin2hex(random_bytes(6));
        }
    },
    'after_save' => function ($id, $v) use ($conn) {
        if (str_starts_with($v['sub_ledger_code'], 'TMP')) {
            sales_exec($conn, 'UPDATE acc_sub_ledgers SET sub_ledger_code=? WHERE sub_ledger_id=?', 'si', ['SL' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id])->close();
        }
    },
    'delete_check' => fn($id) => sales_row($conn, 'SELECT 1 FROM acc_voucher_lines WHERE sub_ledger_id=? LIMIT 1', 'i', [$id]) ? 'This party is used in vouchers. Untick Active instead.' : '',
    'extra_post' => function ($action) use ($conn, $tradersReady) {
        if ($action !== 'import_traders' || !$tradersReady) {
            throw new RuntimeException('Unknown action.');
        }
        return acc_import_traders($conn);
    },
    'after_form' => $tradersReady ? '<form method="post" style="margin-top:8px">' . sales_csrf_field() . '<input type="hidden" name="action" value="import_traders"><button class="btn" onclick="return confirm(\'Create sub ledgers for every trader that does not have one?\')">Import from Trader Info</button> <span class="muted">Customers go under the receivable account and suppliers under the payable account set in Accounts Configuration.</span></form>' : '',
    'list_sql' => 'SELECT s.*, g.group_name, a.account_name, g2.nature FROM acc_sub_ledgers s JOIN acc_sub_ledger_groups g ON g.slg_id=s.slg_id JOIN acc_accounts a ON a.account_id=s.account_id JOIN acc_groups g2 ON g2.group_id=a.group_id {where} ORDER BY s.sub_ledger_name',
    'search' => ['s.sub_ledger_name', 's.sub_ledger_code', 's.mobile', 'g.group_name'],
    'columns' => [
        'sub_ledger_code' => 'Code',
        'sub_ledger_name' => 'Name',
        'group_name' => 'Group',
        'account_name' => 'Control Account',
        'mobile' => 'Mobile',
        'opening' => ['Opening', fn($r) => sales_money($r['opening_balance']) . ' ' . $r['opening_side'], true],
        'balance' => ['Balance', function ($r) use ($move) {
            $m = $move[(int) $r['sub_ledger_id']] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
            return acc_drcr($m['open'] + $m['dr'] - $m['cr']);
        }, true],
        'is_active' => ['Active', fn($r) => (int) $r['is_active'] ? 'Yes' : 'No'],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
