<?php
/** Trader Search: find traders across every category by name, code, phone, BIN/NID, group, SR, status or flags. */
require_once __DIR__ . '/trader_common.php';

$self = 'trader_search.php';
$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0);
$groupId = (int) ($_GET['group'] ?? 0);
$srId = (int) ($_GET['sr'] ?? 0);
$status = in_array(($_GET['status'] ?? ''), ['active', 'inactive'], true) ? (string) $_GET['status'] : '';
$flag = in_array(($_GET['flag'] ?? ''), ['credit', 'blocked', 'vat_exempt', 'no_sr'], true) ? (string) $_GET['flag'] : '';
$rows = $groups = $srs = [];
$total = 0;

if ($ready) {
    if ($groupId > 0 && $cat === 0) {
        $g = sales_row($conn, 'SELECT category_id FROM trader_groups WHERE group_id=?', 'i', [$groupId]);
        $cat = (int) ($g['category_id'] ?? 0);
    }
    $groups = trader_groups($conn, $cat);
    $srs = trader_srs($conn, false);
    $where = ['1=1'];
    $types = '';
    $params = [];
    if ($q !== '') {
        $where[] = '(t.trader_name LIKE ? OR t.trader_code LIKE ? OR t.mobile LIKE ? OR t.secondary_mobile LIKE ? OR t.phone LIKE ? OR t.contact_person LIKE ? OR t.owner_name LIKE ? OR t.bin LIKE ? OR t.nid LIKE ? OR t.email LIKE ? OR t.address LIKE ?)';
        $types .= str_repeat('s', 11);
        $params = array_merge($params, array_fill(0, 11, '%' . $q . '%'));
    }
    if ($cat > 0) { $where[] = 't.category_id=?'; $types .= 'i'; $params[] = $cat; }
    if ($groupId > 0) { $where[] = 't.group_id=?'; $types .= 'i'; $params[] = $groupId; }
    if ($srId > 0) { $where[] = 'a.sr_id=?'; $types .= 'i'; $params[] = $srId; }
    if ($status === 'active') { $where[] = 't.is_active=1'; }
    if ($status === 'inactive') { $where[] = 't.is_active=0'; }
    if ($flag === 'credit') { $where[] = 'f.allow_credit=1'; }
    if ($flag === 'blocked') { $where[] = '(f.block_sales=1 OR f.block_purchase=1)'; }
    if ($flag === 'vat_exempt') { $where[] = 'f.vat_exempt=1'; }
    if ($flag === 'no_sr') { $where[] = 'a.assignment_id IS NULL'; }
    $from = 'FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id '
        . 'LEFT JOIN trader_flags f ON f.trader_id=t.trader_id LEFT JOIN trader_sr_assignments a ON a.trader_id=t.trader_id AND a.effective_to IS NULL '
        . 'LEFT JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE ' . implode(' AND ', $where);
    $total = (int) (sales_row($conn, 'SELECT COUNT(*) AS n ' . $from, $types, $params)['n'] ?? 0);
    $limit = isset($_GET['export']) ? 20000 : 500;
    $rows = sales_rows(
        $conn,
        'SELECT t.trader_id, t.category_id, t.trader_code, t.trader_name, t.mobile, t.phone, t.email, t.contact_person, t.address, t.bin, t.nid, t.opening_balance, t.is_active, '
            . 'c.category_name, COALESCE(g.group_name, \'\') AS group_name, COALESCE(s.sr_name, \'\') AS sr_name, COALESCE(f.allow_credit, 0) AS allow_credit, '
            . 'COALESCE(f.credit_limit, 0) AS credit_limit, COALESCE(f.block_sales, 0) AS block_sales, COALESCE(f.block_purchase, 0) AS block_purchase ' . $from
            . ' ORDER BY c.sort_order, t.trader_name LIMIT ' . $limit,
        $types,
        $params
    );

    if (isset($_GET['export'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="traders_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $columns = ['trader_code' => 'Code', 'trader_name' => 'Name', 'category_name' => 'Category', 'group_name' => 'Group', 'mobile' => 'Mobile', 'phone' => 'Phone', 'email' => 'Email', 'contact_person' => 'Contact Person', 'address' => 'Address', 'bin' => 'BIN', 'nid' => 'NID', 'sr_name' => 'SR', 'opening_balance' => 'Opening Balance', 'credit_limit' => 'Credit Limit', 'is_active' => 'Active'];
        fputcsv($out, array_values($columns), ',', '"', '');
        foreach ($rows as $r) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $value = (string) $r[$key];
                // Keep spreadsheet apps from running cell text as a formula.
                $line[] = preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
            }
            fputcsv($out, $line, ',', '"', '');
        }
        fclose($out);
        exit;
    }
}

trader_page_start('Trader Search', 'search');
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
$query = $_GET;
unset($query['export']);
?>
<fieldset class="box"><legend>Trader Search</legend>
<form method="get" class="grid g4" style="margin-top:0">
<div class="field" style="grid-column:span 2"><label for="q">Search</label><input id="q" name="q" value="<?= sales_h($q) ?>" placeholder="Name, code, mobile, phone, contact, owner, BIN, NID, email or address" autofocus></div>
<div class="field"><label for="cat">Category</label><select id="cat" name="cat" onchange="this.form.group.value='0'"><?= trader_category_options($categories, $cat, true) ?></select></div>
<div class="field"><label for="group">Group</label><select id="group" name="group"><option value="0">All groups</option><?php foreach ($groups as $g): ?><option value="<?= (int) $g['group_id'] ?>" <?= (int) $g['group_id'] === $groupId ? 'selected' : '' ?>><?= sales_h($g['group_name'] . ($cat ? '' : ' (' . $g['category_name'] . ')')) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sr">SR</label><select id="sr" name="sr"><option value="0">Any SR</option><?php foreach ($srs as $s): ?><option value="<?= (int) $s['sr_id'] ?>" <?= (int) $s['sr_id'] === $srId ? 'selected' : '' ?>><?= sales_h($s['sr_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Active and inactive</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option></select></div>
<div class="field"><label for="flag">Flag</label><select id="flag" name="flag"><option value="">Any</option><option value="credit" <?= $flag === 'credit' ? 'selected' : '' ?>>Credit allowed</option><option value="blocked" <?= $flag === 'blocked' ? 'selected' : '' ?>>Blocked</option><option value="vat_exempt" <?= $flag === 'vat_exempt' ? 'selected' : '' ?>>VAT exempt</option><option value="no_sr" <?= $flag === 'no_sr' ? 'selected' : '' ?>>No SR assigned</option></select></div>
<div class="field"><label>&nbsp;</label><div class="toolbar"><button class="btn primary">Search</button><a class="btn" href="<?= $self ?>">Clear</a><a class="btn" href="<?= $self ?>?<?= sales_h(http_build_query($query + ['export' => 'csv'])) ?>">Export CSV</a></div></div>
</form>
<p class="muted"><?= $total ?> trader(s) found<?= $total > count($rows) ? ', showing the first ' . count($rows) . '. Narrow the search or export to CSV for all' : '' ?>.</p>
<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Category</th><th>Group</th><th>Mobile</th><th>Phone</th><th>Contact</th><th>SR</th><th class="num">Credit Limit</th><th>Status</th><th>Select</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="12" class="empty">No traders found.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?><tr>
<td><?= $i + 1 ?></td><td><?= sales_h($r['trader_code']) ?></td><td><?= sales_h($r['trader_name']) ?></td><td><?= sales_h($r['category_name']) ?></td><td><?= sales_h($r['group_name']) ?></td><td><?= sales_h($r['mobile']) ?></td><td><?= sales_h($r['phone']) ?></td><td><?= sales_h($r['contact_person']) ?></td><td><?= sales_h($r['sr_name']) ?></td>
<td class="num"><?= (int) $r['allow_credit'] ? sales_money($r['credit_limit']) : '<span class="muted">No credit</span>' ?></td>
<td><?php if (!(int) $r['is_active']): ?><span class="badge cancelled">Inactive</span><?php elseif ((int) $r['block_sales'] || (int) $r['block_purchase']): ?><span class="badge cancelled">Blocked</span><?php else: ?><span class="badge converted">Active</span><?php endif; ?></td>
<td class="nowrap"><a class="btn small" href="trader_info.php?cat=<?= (int) $r['category_id'] ?>&id=<?= (int) $r['trader_id'] ?>">Open</a> <a class="btn small" href="trader_convert.php?id=<?= (int) $r['trader_id'] ?>">Convert</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>
<?php
trader_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
