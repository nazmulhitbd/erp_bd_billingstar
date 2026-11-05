<?php
/**
 * Customer Management: every customer with category, SR, credit limit, what they owe now and their status
 * (Blocked, Over limit, Overdue, Cash only, OK). Opens the profile and the ledger.
 */
require_once __DIR__ . '/customer_common.php';

cm_page_start($conn, 'Customer Management', 'list');
$q = sales_cut(trim((string) ($_GET['q'] ?? '')), 120);
$cat = (int) ($_GET['cat'] ?? 0);
$sr = sales_cut(trim((string) ($_GET['sr'] ?? '')), 140);
$status = in_array($_GET['status'] ?? '', ['due', 'over_limit', 'overdue', 'blocked', 'no_profile', 'inactive'], true) ? (string) $_GET['status'] : '';
$cats = cm_categories($conn);

$where = ['1=1'];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR mobile LIKE ? OR customer_code LIKE ? OR trader_code LIKE ?)';
    $types .= 'ssss';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($cat > 0) {
    $where[] = 'category_id=?';
    $types .= 'i';
    $params[] = $cat;
}
if ($sr !== '') {
    $where[] = 'sr_name=?';
    $types .= 's';
    $params[] = $sr;
}
$where[] = match ($status) {
    'due' => 'balance>0.004',
    'over_limit' => 'allow_credit=1 AND credit_limit>0 AND balance>credit_limit+0.004',
    'overdue' => 'credit_days>0 AND oldest_due_date IS NOT NULL AND DATEDIFF(CURDATE(), oldest_due_date)>credit_days',
    'blocked' => 'block_sales=1',
    'no_profile' => 'trader_id IS NULL',
    'inactive' => 'is_active=0',
    default => '1=1',
};
$rows = sales_rows($conn, 'SELECT * FROM customer_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY customer_name', $types, $params); // every row: the totals below need them
$tot = ['balance' => array_sum(array_column($rows, 'balance')), 'limit' => array_sum(array_map(fn($r) => (int) $r['allow_credit'] ? (float) $r['credit_limit'] : 0, $rows))];
$srs = array_column(trader_srs($conn, false), 'sr_name');
$opt = fn(array $list, $cur) => implode('', array_map(fn($k, $v) => '<option value="' . sales_h($k) . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . sales_h($v) . '</option>', array_keys($list), $list));
?>
<form method="get" class="list-search" style="align-items:center">
<input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, mobile or code" style="max-width:240px">
<select class="input" name="cat" style="max-width:170px"><option value="0">All categories</option><?= $opt($cats, $cat) ?></select>
<select class="input" name="sr" style="max-width:170px"><option value="">All SRs</option><?= $opt(array_combine($srs, $srs) ?: [], $sr) ?></select>
<select class="input" name="status" style="max-width:170px"><?= $opt(['' => 'Any status', 'due' => 'Has due', 'over_limit' => 'Over credit limit', 'overdue' => 'Overdue', 'blocked' => 'Blocked', 'no_profile' => 'No profile yet', 'inactive' => 'Inactive'], $status) ?></select>
<button class="btn primary">Search</button>
<?php if (cm_can($conn, 'add')): ?><a class="btn" href="customer_profile.php">New Customer</a><?php endif; ?>
</form>
<div class="summary"><div>Customers<b><?= count($rows) ?></b></div><div>Total Due<b class="qty-info"><?= sales_money($tot['balance']) ?></b></div><div>Credit Limits<b><?= sales_money($tot['limit']) ?></b></div></div>
<div class="table-scroll" style="max-height:640px;margin-top:8px"><table><thead><tr><th>SL</th><th>Code</th><th>Customer</th><th>Category</th><th>Group</th><th>Mobile</th><th>SR</th>
<th class="num">Credit Limit</th><th class="num">Due</th><th class="num">Available</th><th>Oldest Due</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="13" class="empty">No customers match.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): [$label, $badge] = cm_status($r); $credit = (int) $r['allow_credit'] && (float) $r['credit_limit'] > 0; ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($r['trader_code'] ?: $r['customer_code']) ?></td><td><?= sales_h($r['customer_name']) ?></td><td><?= sales_h($r['category_name'] ?: '—') ?></td><td><?= sales_h($r['customer_group']) ?></td>
<td><?= sales_h($r['mobile']) ?></td><td><?= sales_h($r['sr_name'] ?? '') ?></td>
<td class="num"><?= $credit ? sales_money($r['credit_limit']) : '<span class="muted">' . ((int) $r['has_credit_rules'] ? 'No credit' : '—') . '</span>' ?></td>
<td class="num"><?= sales_money($r['balance']) ?></td><td class="num"><?= $credit ? sales_money(max(0, (float) $r['credit_limit'] - (float) $r['balance'])) : '' ?></td>
<td><?= $r['oldest_due_date'] ? inv_fmt_date($r['oldest_due_date']) . ' <span class="muted">(' . cm_overdue_days($r) . 'd)</span>' : '' ?></td>
<td><span class="badge <?= $badge ?>"><?= sales_h($label) ?></span><?= $r['trader_id'] ? '' : ' <span class="muted">no profile</span>' ?></td>
<td class="nowrap"><a class="btn small" href="customer_profile.php?id=<?= (int) $r['customer_id'] ?>">Profile</a><?php if (cm_can($conn, 'ledger')): ?> <a class="btn small" href="customer_ledger.php?id=<?= (int) $r['customer_id'] ?>">Ledger</a><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
