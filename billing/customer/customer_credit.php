<?php
/**
 * Credit Limit: credit rules of all customers with a profile in one grid, next to what each owes, what is still
 * available and how old the oldest unpaid invoice is. Changed rows are saved together.
 */
require_once __DIR__ . '/customer_common.php';

$self = 'customer_credit.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && cm_ready($conn)) {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['q', 'status'])));
    sales_require_csrf($back);
    mgmt_require(cm_can($conn, 'credit'), 'changing credit limits');
    $rows = is_array($_POST['credit'] ?? null) ? $_POST['credit'] : [];
    $n = 0;
    $conn->begin_transaction();
    foreach ($rows as $traderId => $r) {
        if (!is_array($r) || empty($r['changed']) || !sales_row($conn, 'SELECT 1 FROM traders WHERE trader_id=?', 'i', [(int) $traderId])) {
            continue;
        }
        sales_exec($conn, 'INSERT INTO trader_flags (trader_id, allow_credit, credit_limit, credit_days, block_sales, updated_by) VALUES (?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE allow_credit=VALUES(allow_credit), credit_limit=VALUES(credit_limit), credit_days=VALUES(credit_days), block_sales=VALUES(block_sales), updated_by=VALUES(updated_by)',
            'iidiii', [(int) $traderId, !empty($r['allow']) ? 1 : 0, sales_amount($r['limit'] ?? 0), max(0, min(3650, (int) ($r['days'] ?? 0))), !empty($r['block']) ? 1 : 0, (int) ($GLOBALS['user_id'] ?? 0)])->close();
        $n++;
    }
    $conn->commit();
    sales_flash_redirect($back, 'success', $n ? $n . ' customer(s) updated.' : 'Nothing was changed.');
}

$flash = sales_take_flash();
cm_page_start($conn, 'Credit Limit', 'credit');
echo sales_flash_html($flash);
$q = sales_cut(trim((string) ($_GET['q'] ?? '')), 120);
$status = in_array($_GET['status'] ?? '', ['credit', 'over_limit', 'overdue', 'blocked'], true) ? (string) $_GET['status'] : '';
$where = ['trader_id IS NOT NULL'];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR mobile LIKE ? OR trader_code LIKE ?)';
    $types = 'sss';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$where[] = match ($status) {
    'credit' => 'allow_credit=1',
    'over_limit' => 'allow_credit=1 AND credit_limit>0 AND balance>credit_limit+0.004',
    'overdue' => 'credit_days>0 AND oldest_due_date IS NOT NULL AND DATEDIFF(CURDATE(), oldest_due_date)>credit_days',
    'blocked' => 'block_sales=1',
    default => '1=1',
};
$rows = sales_list_rows($conn, 'SELECT * FROM customer_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY customer_name', $types, $params);
$can = cm_can($conn, 'credit');
?>
<form method="get" class="list-search"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, mobile or code" style="max-width:240px">
<select class="input" name="status" style="max-width:190px"><?php foreach (['' => 'All with a profile', 'credit' => 'Credit allowed', 'over_limit' => 'Over credit limit', 'overdue' => 'Overdue', 'blocked' => 'Blocked'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<button class="btn primary">Show</button></form>
<form method="post"><?= sales_csrf_field() ?><input type="hidden" name="q" value="<?= sales_h($q) ?>"><input type="hidden" name="status" value="<?= sales_h($status) ?>">
<div class="table-scroll" style="max-height:620px"><table id="credit-grid"><thead><tr><th>SL</th><th>Code</th><th>Customer</th><th>Mobile</th><th>Allow Credit</th><th class="num">Credit Limit</th><th class="num">Credit Days</th><th>Block Sales</th>
<th class="num">Due</th><th class="num">Available</th><th>Oldest Due</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="12" class="empty">No customers with a profile match. Customers made on sales screens get a profile in Customer Profile.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): $tid = (int) $r['trader_id']; [$label, $badge] = cm_status($r); $dis = $can ? '' : ' disabled'; ?>
<tr data-row><td><?= $i + 1 ?></td><td><?= sales_h($r['trader_code']) ?></td><td><a href="customer_profile.php?id=<?= (int) $r['customer_id'] ?>"><?= sales_h($r['customer_name']) ?></a></td><td><?= sales_h($r['mobile']) ?></td>
<td><input type="hidden" name="credit[<?= $tid ?>][changed]" value="" data-changed><input class="check" type="checkbox" name="credit[<?= $tid ?>][allow]" value="1"<?= (int) $r['allow_credit'] ? ' checked' : '' ?><?= $dis ?>></td>
<td><input type="number" min="0" step="0.01" name="credit[<?= $tid ?>][limit]" value="<?= sales_h(number_format((float) $r['credit_limit'], 2, '.', '')) ?>" style="width:110px;text-align:right"<?= $dis ?>></td>
<td><input type="number" min="0" max="3650" name="credit[<?= $tid ?>][days]" value="<?= (int) $r['credit_days'] ?>" style="width:70px;text-align:right"<?= $dis ?>></td>
<td><input class="check" type="checkbox" name="credit[<?= $tid ?>][block]" value="1"<?= (int) $r['block_sales'] ? ' checked' : '' ?><?= $dis ?>></td>
<td class="num"><?= sales_money($r['balance']) ?></td><td class="num"><?= (int) $r['allow_credit'] && (float) $r['credit_limit'] > 0 ? sales_money(max(0, (float) $r['credit_limit'] - (float) $r['balance'])) : '' ?></td>
<td><?= $r['oldest_due_date'] ? inv_fmt_date($r['oldest_due_date']) . ' <span class="muted">(' . cm_overdue_days($r) . 'd)</span>' : '' ?></td><td><span class="badge <?= $badge ?>"><?= sales_h($label) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($rows && $can): ?><div class="toolbar" style="margin-top:8px"><button class="btn primary">Save Changes</button></div><?php endif; ?>
</form>
<?php
// Mark a row changed when any of its inputs changes, so only those rows are saved.
mgmt_page_end('document.querySelectorAll("#credit-grid [data-row]").forEach(tr=>tr.querySelectorAll("input:not([data-changed])").forEach(i=>i.addEventListener("change",()=>{tr.querySelector("[data-changed]").value="1"})));');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
