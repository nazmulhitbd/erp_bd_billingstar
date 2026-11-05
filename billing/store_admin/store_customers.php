<?php
/**
 * Customers by Store (system admin): every customer with the store that owns it (sales_customers.branch_id, NULL =
 * shared / Head Office), contact, credit, what it owes and its SR, in the reseller-list layout. Filter by store type,
 * store, owing / blocked or text; move selected customers to a store or back to shared. Profiles open in Customer Management.
 */
require_once __DIR__ . '/sa_list.php';

$self = 'store_customers.php';
$ready = sales_tables_ready($conn, ['store_profiles', 'customer_accounts', 'sales_customers']);
$stores = $ready ? sa_stores($conn) : [];
$filters = ['type' => (string) ($_GET['type'] ?? ''), 'store' => (int) ($_GET['store'] ?? 0), 'status' => (string) ($_GET['status'] ?? ''), 'q' => sales_cut(trim((string) ($_GET['q'] ?? '')), 60)];
$filters['type'] = array_key_exists($filters['type'], SA_STORE_TYPES) || $filters['type'] === 'none' ? $filters['type'] : '';
$filters['status'] = in_array($filters['status'], ['owing', 'blocked', 'credit', 'no_sr'], true) ? $filters['status'] : '';
[$limit, $page] = sa_paging();
$url = sa_url_builder($self, $filters, $limit);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    $back = $url(['page' => $page]);
    sales_require_csrf($back);
    try {
        $ids = array_map('intval', (array) ($_POST['selected'] ?? []));
        if (!$ids || ($_POST['action'] ?? '') !== 'move') {
            throw new RuntimeException('Tick at least one customer.');
        }
        $to = (int) ($_POST['to_store'] ?? 0);
        $store = null;
        foreach ($stores as $s) {
            $store = (int) $s['store_id'] === $to ? $s : $store;
        }
        if ($to !== -1 && !$store) {
            throw new RuntimeException('Choose the store to move them to.');
        }
        $branch = $store ? (int) $store['branch_id'] : null;
        $stmt = sales_exec($conn, 'UPDATE sales_customers SET branch_id=? WHERE customer_id IN (' . implode(',', $ids) . ')', 'i', [$branch]);
        $n = $stmt->affected_rows;
        $stmt->close();
        sales_flash_redirect($back, 'success', $n . ' customer(s) moved to ' . ($store ? $store['store_name'] : 'shared / Head Office') . '.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

sa_page_head('Customers by Store', sales_take_flash());
if (!$ready) {
    echo '<div class="alert alert-danger">Customer Management and the store back office must be installed (billing/customer/schema.sql; the Stores tables come with the installer).</div>';
    sa_page_foot();
    exit;
}

$where = ['1=1'];
$types = '';
$params = [];
$add = function (array $cond) use (&$where, &$types, &$params) {
    $where[] = $cond[0];
    $types .= $cond[1];
    array_push($params, ...$cond[2]);
};
if ($filters['type'] === 'none') {
    $add(['sc.branch_id IS NULL', '', []]);
} elseif ($filters['type'] !== '') {
    $add(['p.store_type = ?', 's', [$filters['type']]]);
}
if ($filters['store']) {
    $add(sa_store_filter($stores, $filters['store'], 'sc.branch_id'));
}
if ($filters['q'] !== '') {
    $add(['(c.customer_name LIKE ? OR c.mobile LIKE ? OR c.customer_code LIKE ? OR c.trader_code LIKE ?)', 'ssss', array_fill(0, 4, '%' . $filters['q'] . '%')]);
}
$add([match ($filters['status']) {
    'owing' => 'c.balance > 0.004',
    'blocked' => 'c.block_sales = 1',
    'credit' => 'c.allow_credit = 1',
    'no_sr' => "COALESCE(c.sr_name, '') = ''",
    default => '1=1',
}, '', []]);
$from = 'FROM customer_accounts c JOIN sales_customers sc ON sc.customer_id=c.customer_id LEFT JOIN store_profiles p ON p.branch_id=sc.branch_id LEFT JOIN branches b ON b.branch_id=sc.branch_id WHERE ' . implode(' AND ', $where);
$sum = sales_row($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(GREATEST(c.balance, 0)), 0) AS owed $from", $types, $params);
$total = (int) $sum['n'];
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$rows = sales_rows($conn, "SELECT c.*, sc.branch_id, p.store_name, p.store_code, p.store_type, b.branch_name $from ORDER BY p.store_type='head_office' DESC, p.store_name, c.customer_name LIMIT " . (($page - 1) * $limit) . ", $limit", $types, $params);

sa_tabs($url, 'type', $filters['type'], ['' => 'All Customers', 'head_office' => 'Head Office', 'warehouse' => 'Warehouse', 'retail_shop' => 'Retail Shop', 'none' => 'Not in a store']);
?>
<div class="panel panel-default"><div class="panel-body">
<form method="get" class="search-bar"><strong>Search:</strong>
<?php if ($filters['type'] !== ''): ?><input type="hidden" name="type" value="<?= sales_h($filters['type']) ?>"><?php endif; ?>
<input class="form-control" name="q" placeholder="Name, mobile or code" value="<?= sales_h($filters['q']) ?>">
<select class="form-control" name="store" aria-label="Store"><?= sa_store_options($stores, $filters['store']) ?></select>
<select class="form-control" name="status" aria-label="Status"><option value="">- any status -</option><?php foreach (['owing' => 'Owing money', 'credit' => 'Credit allowed', 'blocked' => 'Sales blocked', 'no_sr' => 'No SR assigned'] as $k => $l): ?><option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<input type="hidden" name="limit" value="<?= $limit ?>"><button class="btn btn-primary">&#187; Apply</button>
<span class="muted" style="margin-left:auto">Owed by these customers: <b><?= sales_money($sum['owed']) ?></b></span>
</form></div></div>
<form method="post"><?= sales_csrf_field() ?>
<?php
sa_pager($url, $page, $pages, $total, $limit);
sa_table_head(['Nr', 'Code', 'Customer', 'Mobile', 'Group', 'Category', 'Store', 'Store Type', 'Branch', 'SR', 'Credit Limit', 'Owes', 'Last Invoice', 'Status', 'Profile']);
if (!$rows) {
    echo '<tr><td colspan="16" class="WUIfield" style="text-align:center">No customers match.</td></tr>';
}
foreach ($rows as $i => $r) {
    $status = (int) $r['is_active'] ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>';
    $status .= (int) $r['block_sales'] ? ' <span class="label label-danger">Blocked</span>' : '';
    $status .= (int) $r['allow_credit'] ? ' <span class="label label-default">Credit' . ((int) $r['credit_days'] ? ' ' . (int) $r['credit_days'] . 'd' : '') . '</span>' : '';
    echo '<tr valign="top" class="' . ($i % 2 ? 'WUIrsrecordrowodd' : 'WUIrsrecordroweven') . '">'
        . '<td class="WUIfield" style="text-align:center"><input type="checkbox" name="selected[]" value="' . (int) $r['customer_id'] . '" aria-label="Select ' . sales_h($r['customer_name']) . '"></td>'
        . '<td class="WUIfield">' . (($page - 1) * $limit + $i + 1) . '.</td>'
        . '<td class="WUIfield">' . sales_h($r['trader_code'] ?: $r['customer_code']) . '</td>'
        . '<td class="WUIfield"><a href="../customer/customer_profile.php?id=' . (int) $r['customer_id'] . '">' . sales_h($r['customer_name']) . '</a></td>'
        . '<td class="WUIfield">' . sales_h($r['mobile']) . '</td><td class="WUIfield">' . sales_h($r['customer_group']) . '</td><td class="WUIfield">' . sales_h($r['category_name']) . '</td>'
        . '<td class="WUIfield">' . ($r['store_name'] !== null ? sales_h($r['store_name']) . ' <span class="muted">' . sales_h($r['store_code']) . '</span>' : '<span class="muted">Shared</span>') . '</td>'
        . '<td class="WUIfield">' . ($r['store_type'] ? sales_h(SA_STORE_TYPES[$r['store_type']]) : '') . '</td><td class="WUIfield">' . sales_h($r['branch_name']) . '</td>'
        . '<td class="WUIfield">' . sales_h($r['sr_name']) . '</td>'
        . '<td class="WUIfield" style="text-align:right">' . ((float) $r['credit_limit'] > 0 ? sales_money($r['credit_limit']) : '') . '</td>'
        . '<td class="WUIfield" style="text-align:right">' . ((float) $r['balance'] > 0.004 ? '<b>' . sales_money($r['balance']) . '</b>' : sales_money($r['balance'])) . '</td>'
        . '<td class="WUIfield nowrap">' . ($r['last_invoice_date'] ? sales_h(date('d/m/Y', strtotime($r['last_invoice_date']))) : '<span class="muted">Never</span>') . '</td>'
        . '<td class="WUIfield nowrap">' . $status . '</td>'
        . '<td class="WUIfield" style="text-align:center"><a href="../customer/customer_profile.php?id=' . (int) $r['customer_id'] . '"><span class="label label-primary">Edit Profile</span></a></td></tr>';
}
echo '</tbody></table></div>';
?>
<div class="bulk"><select class="form-control" name="to_store" aria-label="Move to store"><?= sa_store_options($stores, 0, '- move to store -') ?></select>
<button class="btn btn-primary" name="action" value="move" onclick="return confirm('Move the selected customers to this store?')">&#187; Move selected</button></div>
</form>
<?php
sa_page_foot('Each store sees only its own customers in the store panel; "Shared" customers belong to Head Office. Credit limit, SR and blocking are set in Customer Management › Customer Profile.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
