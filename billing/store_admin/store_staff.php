<?php
/**
 * Sales Persons by Store (system admin): every SR / staff member with the store that owns them
 * (sales_representatives.branch_id, NULL = shared / Head Office), territory, commission, assigned customers, this
 * month's sales and SR portal login, in the reseller-list layout. Selected people can be activated, deactivated or
 * moved to a store. Details open in Sales Person Management.
 */
require_once __DIR__ . '/sa_list.php';

$self = 'store_staff.php';
$ready = sales_tables_ready($conn, ['store_profiles', 'sales_representatives', 'sales_invoices']);
$hasPortal = sales_tables_ready($conn, ['sr_portal_accounts']);
$hasTerritory = sales_tables_ready($conn, ['sr_territories']);
$hasAssign = sales_tables_ready($conn, ['trader_sr_assignments']);
$stores = $ready ? sa_stores($conn) : [];
$filters = ['type' => (string) ($_GET['type'] ?? ''), 'store' => (int) ($_GET['store'] ?? 0), 'status' => (string) ($_GET['status'] ?? ''), 'q' => sales_cut(trim((string) ($_GET['q'] ?? '')), 60)];
$filters['type'] = array_key_exists($filters['type'], SA_STORE_TYPES) || $filters['type'] === 'none' ? $filters['type'] : '';
$filters['status'] = in_array($filters['status'], ['active', 'inactive', 'portal', 'no_portal'], true) ? $filters['status'] : '';
[$limit, $page] = sa_paging();
$url = sa_url_builder($self, $filters, $limit);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    $back = $url(['page' => $page]);
    sales_require_csrf($back);
    try {
        $ids = array_map('intval', (array) ($_POST['selected'] ?? []));
        $action = (string) ($_POST['action'] ?? '');
        if (!$ids) {
            throw new RuntimeException('Tick at least one person.');
        }
        $in = implode(',', $ids);
        if ($action === 'activate' || $action === 'deactivate') {
            $stmt = sales_exec($conn, "UPDATE sales_representatives SET is_active=? WHERE sr_id IN ($in)", 'i', [$action === 'activate' ? 1 : 0]);
        $n = $stmt->affected_rows;
        $stmt->close();
        sales_flash_redirect($back, 'success', $n . ' person(s) ' . ($action === 'activate' ? 'activated.' : 'deactivated. Their SR portal sign-in stops too.'));
    }
    if ($action === 'move') {
        $to = (int) ($_POST['to_store'] ?? 0);
        $store = null;
        foreach ($stores as $s) {
            $store = (int) $s['store_id'] === $to ? $s : $store;
        }
        if ($to !== -1 && !$store) {
            throw new RuntimeException('Choose the store to move them to.');
        }
        $stmt = sales_exec($conn, "UPDATE sales_representatives SET branch_id=? WHERE sr_id IN ($in)", 'i', [$store ? (int) $store['branch_id'] : null]);
    $n = $stmt->affected_rows;
    $stmt->close();
    sales_flash_redirect($back, 'success', $n . ' person(s) moved to ' . ($store ? $store['store_name'] : 'shared / Head Office') . '.');
}
throw new RuntimeException('Unknown action.');
} catch (RuntimeException $e) {
    sales_flash_redirect($back, 'error', $e->getMessage());
}
}

sa_page_head('Sales Persons by Store', sales_take_flash());
if (!$ready) {
    echo '<div class="alert alert-danger">Sales Person Management and the store back office must be installed (billing/sales_person/schema.sql; the Stores tables come with the installer).</div>';
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
    $add(['s.branch_id IS NULL', '', []]);
} elseif ($filters['type'] !== '') {
    $add(['p.store_type = ?', 's', [$filters['type']]]);
}
if ($filters['store']) {
    $add(sa_store_filter($stores, $filters['store'], 's.branch_id'));
}
if ($filters['q'] !== '') {
    $add(['(s.sr_name LIKE ? OR s.sr_code LIKE ? OR s.mobile LIKE ? OR s.designation LIKE ?)', 'ssss', array_fill(0, 4, '%' . $filters['q'] . '%')]);
}
$add([match ($filters['status']) {
    'active' => 's.is_active = 1',
    'inactive' => 's.is_active = 0',
    'portal' => $hasPortal ? 'pa.account_id IS NOT NULL' : '0=1',
    'no_portal' => $hasPortal ? 'pa.account_id IS NULL' : '1=1',
    default => '1=1',
}, '', []]);
$joins = 'LEFT JOIN store_profiles p ON p.branch_id=s.branch_id LEFT JOIN branches b ON b.branch_id=s.branch_id'
    . ($hasTerritory ? ' LEFT JOIN sr_territories t ON t.territory_id=s.territory_id' : '')
    . ($hasPortal ? ' LEFT JOIN sr_portal_accounts pa ON pa.sr_id=s.sr_id' : '');
$from = "FROM sales_representatives s $joins WHERE " . implode(' AND ', $where);
$total = (int) sales_row($conn, "SELECT COUNT(*) AS n $from", $types, $params)['n'];
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$month = date('Y-m-01');
$rows = sales_rows($conn, 'SELECT s.*, p.store_name, p.store_code, p.store_type, b.branch_name'
        . ($hasTerritory ? ', t.territory_name' : ", '' AS territory_name")
        . ($hasPortal ? ', pa.login AS portal_login, pa.status AS portal_status' : ', NULL AS portal_login, NULL AS portal_status')
        . ($hasAssign ? ', (SELECT COUNT(*) FROM trader_sr_assignments a WHERE a.sr_id=s.sr_id AND a.effective_to IS NULL) AS customers' : ', 0 AS customers')
        . ", (SELECT COALESCE(SUM(v.net_amount), 0) FROM sales_invoices v WHERE v.status='posted' AND v.sales_date >= ? AND (v.sr_id=s.sr_id OR (v.sr_id IS NULL AND v.sales_person<>'' AND v.sales_person=s.sr_name))) AS month_sales "
        . "$from ORDER BY s.is_active DESC, p.store_type='head_office' DESC, p.store_name, s.sr_name LIMIT " . (($page - 1) * $limit) . ", $limit", 's' . $types, array_merge([$month], $params));

sa_tabs($url, 'type', $filters['type'], ['' => 'All Sales Persons', 'head_office' => 'Head Office', 'warehouse' => 'Warehouse', 'retail_shop' => 'Retail Shop', 'none' => 'Not in a store']);
?>
<div class="panel panel-default"><div class="panel-body">
<form method="get" class="search-bar"><strong>Search:</strong>
<?php if ($filters['type'] !== ''): ?><input type="hidden" name="type" value="<?= sales_h($filters['type']) ?>"><?php endif; ?>
<input class="form-control" name="q" placeholder="Name, code, mobile or designation" value="<?= sales_h($filters['q']) ?>">
<select class="form-control" name="store" aria-label="Store"><?= sa_store_options($stores, $filters['store']) ?></select>
<select class="form-control" name="status" aria-label="Status"><option value="">- any status -</option><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'portal' => 'Has SR portal login', 'no_portal' => 'No SR portal login'] as $k => $l): ?><option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<input type="hidden" name="limit" value="<?= $limit ?>"><button class="btn btn-primary">&#187; Apply</button>
</form></div></div>
<form method="post"><?= sales_csrf_field() ?>
<?php
sa_pager($url, $page, $pages, $total, $limit);
sa_table_head(['Nr', 'Code', 'Name', 'Designation', 'Mobile', 'Store', 'Store Type', 'Branch', 'Territory', 'Commission', 'Customers', 'Sales ' . date('M Y'), 'SR Portal', 'Joined', 'Status', 'Profile']);
if (!$rows) {
    echo '<tr><td colspan="17" class="WUIfield" style="text-align:center">No sales persons match.</td></tr>';
}
$basis = ['none' => 'None', 'net_sales' => 'net sales', 'collection' => 'collection', 'gross_profit' => 'gross profit'];
foreach ($rows as $i => $r) {
    $portal = $r['portal_login'] === null ? '<span class="muted">No login</span>' : sales_h($r['portal_login']) . ' ' . ($r['portal_status'] === 'active' ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Disabled</span>');
    echo '<tr valign="top" class="' . ($i % 2 ? 'WUIrsrecordrowodd' : 'WUIrsrecordroweven') . '">'
        . '<td class="WUIfield" style="text-align:center"><input type="checkbox" name="selected[]" value="' . (int) $r['sr_id'] . '" aria-label="Select ' . sales_h($r['sr_name']) . '"></td>'
        . '<td class="WUIfield">' . (($page - 1) * $limit + $i + 1) . '.</td>'
        . '<td class="WUIfield">' . sales_h($r['sr_code']) . '</td>'
        . '<td class="WUIfield"><a href="../sales_person/sr_list.php?edit=' . (int) $r['sr_id'] . '">' . sales_h($r['sr_name']) . '</a></td>'
        . '<td class="WUIfield">' . sales_h($r['designation']) . '</td><td class="WUIfield">' . sales_h($r['mobile']) . '</td>'
        . '<td class="WUIfield">' . ($r['store_name'] !== null ? sales_h($r['store_name']) . ' <span class="muted">' . sales_h($r['store_code']) . '</span>' : '<span class="muted">Shared</span>') . '</td>'
        . '<td class="WUIfield">' . ($r['store_type'] ? sales_h(SA_STORE_TYPES[$r['store_type']]) : '') . '</td><td class="WUIfield">' . sales_h($r['branch_name']) . '</td>'
        . '<td class="WUIfield">' . sales_h($r['territory_name']) . '</td>'
        . '<td class="WUIfield nowrap">' . ($r['commission_basis'] === 'none' ? '<span class="muted">None</span>' : sales_h((float) $r['commission_rate'] . '% of ' . $basis[$r['commission_basis']])) . '</td>'
        . '<td class="WUIfield" style="text-align:right">' . (int) $r['customers'] . '</td>'
        . '<td class="WUIfield" style="text-align:right">' . sales_money($r['month_sales']) . '</td>'
        . '<td class="WUIfield nowrap">' . $portal . '</td>'
        . '<td class="WUIfield nowrap">' . ($r['joining_date'] ? sales_h(date('d/m/Y', strtotime($r['joining_date']))) : '') . '</td>'
        . '<td class="WUIfield">' . ((int) $r['is_active'] ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>') . '</td>'
        . '<td class="WUIfield" style="text-align:center"><a href="../sales_person/sr_list.php?edit=' . (int) $r['sr_id'] . '"><span class="label label-primary">Edit</span></a></td></tr>';
}
echo '</tbody></table></div>';
?>
<div class="bulk">
<button class="btn btn-primary" name="action" value="deactivate" onclick="return confirm('Deactivate the selected people? Their SR portal sign-in stops too.')">&#187; Deactivate selected</button>
<button class="btn btn-default" name="action" value="activate">&#187; Activate selected</button>
<select class="form-control" name="to_store" aria-label="Move to store"><?= sa_store_options($stores, 0, '- move to store -') ?></select>
<button class="btn btn-default" name="action" value="move" onclick="return confirm('Move the selected people to this store?')">&#187; Move selected</button>
</div>
</form>
<?php
sa_page_foot('Each store sees only its own SR / staff in the store panel; "Shared" people belong to Head Office. Targets, commission payments and SR portal logins are in Sales Person Management.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
