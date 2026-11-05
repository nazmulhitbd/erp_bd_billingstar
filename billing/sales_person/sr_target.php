<?php
/**
 * Target: each SR's sales and collection target for a month, next to what was achieved (net sales after returns,
 * and money collected on the SR's invoices). Copy brings last month's targets in.
 */
require_once __DIR__ . '/sales_person_common.php';

$self = 'sr_target.php';
$month = sp_month($_REQUEST['month'] ?? '');
$ym = substr($month, 0, 7);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_ready($conn)) {
    sales_require_csrf($self . '?month=' . $ym);
    mgmt_require(sp_can($conn, 'target'), 'setting targets');
    $user = (int) ($GLOBALS['user_id'] ?? 0);
    $conn->begin_transaction();
    if (($_POST['action'] ?? '') === 'copy') {
        $prev = date('Y-m-01', strtotime($month . ' -1 month'));
        sales_exec($conn, 'INSERT IGNORE INTO sr_targets (sr_id, target_month, sales_target, collection_target, note, updated_by) SELECT sr_id, ?, sales_target, collection_target, note, ? FROM sr_targets WHERE target_month=?', 'sis', [$month, $user, $prev])->close();
        $conn->commit();
        sales_flash_redirect($self . '?month=' . $ym, 'success', 'Copied ' . date('M Y', strtotime($prev)) . ' targets where this month had none.');
    }
    $n = 0;
    foreach ((array) ($_POST['t'] ?? []) as $srId => $r) {
        if (!is_array($r) || !sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_id=?', 'i', [(int) $srId])) {
            continue;
        }
        $sales = sales_amount($r['sales'] ?? 0);
        $coll = sales_amount($r['collection'] ?? 0);
        if ($sales <= 0 && $coll <= 0) {
            sales_exec($conn, 'DELETE FROM sr_targets WHERE sr_id=? AND target_month=?', 'is', [(int) $srId, $month])->close();
            continue;
        }
        sales_exec($conn, 'INSERT INTO sr_targets (sr_id, target_month, sales_target, collection_target, note, updated_by) VALUES (?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE sales_target=VALUES(sales_target), collection_target=VALUES(collection_target), note=VALUES(note), updated_by=VALUES(updated_by)',
            'isddsi', [(int) $srId, $month, $sales, $coll, sales_str($r['note'] ?? '', 190), $user])->close();
        $n++;
    }
    $conn->commit();
    sales_flash_redirect($self . '?month=' . $ym, 'success', $n . ' target(s) saved for ' . date('M Y', strtotime($month)) . '.');
}

$flash = sales_take_flash();
sp_page_start($conn, 'Target', 'target');
echo sales_flash_html($flash);
$stats = sp_month_stats($conn, $month);
$targets = array_column(sales_rows($conn, 'SELECT * FROM sr_targets WHERE target_month=?', 's', [$month]), null, 'sr_id');
$srs = sales_rows($conn, 'SELECT s.*, COALESCE(t.territory_name, \'\') AS territory_name FROM sales_representatives s LEFT JOIN sr_territories t ON t.territory_id=s.territory_id ORDER BY s.sr_name');
$srs = array_values(array_filter($srs, fn($s) => empty($s['staff_only']) && ((int) $s['is_active'] || isset($targets[(int) $s['sr_id']]) || isset($stats[(int) $s['sr_id']]))));
$can = sp_can($conn, 'target');
$tot = ['st' => 0, 'ct' => 0, 'net' => 0, 'coll' => 0];
?>
<form method="get" class="list-search" style="align-items:center"><label class="nowrap">Month <input class="input" type="month" name="month" value="<?= $ym ?>" style="max-width:170px"></label><button class="btn primary">Show</button></form>
<form method="post"><?= sales_csrf_field() ?><input type="hidden" name="month" value="<?= $ym ?>">
<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>SL</th><th>SR</th><th>Territory</th><th class="num">Sales Target</th><th class="num">Net Sales</th><th class="num">Achieved</th><th class="num">Collection Target</th><th class="num">Collection</th><th class="num">Achieved</th><th>Note</th></tr></thead><tbody>
<?php if (!$srs): ?><tr><td colspan="10" class="empty">No active sales persons. Add them in Sales Person (SR).</td></tr><?php endif; ?>
<?php foreach ($srs as $i => $s): $id = (int) $s['sr_id']; $t = $targets[$id] ?? []; $st = $stats[$id] ?? ['net_sales' => 0, 'collection' => 0];
$tot['st'] += (float) ($t['sales_target'] ?? 0); $tot['ct'] += (float) ($t['collection_target'] ?? 0); $tot['net'] += $st['net_sales']; $tot['coll'] += $st['collection'];
$ps = sp_pct($st['net_sales'], $t['sales_target'] ?? 0); $pc = sp_pct($st['collection'], $t['collection_target'] ?? 0); $dis = $can ? '' : ' disabled'; ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($s['sr_name']) ?> <span class="muted"><?= sales_h($s['sr_code']) ?></span></td><td><?= sales_h($s['territory_name']) ?></td>
<td><input type="number" min="0" step="0.01" name="t[<?= $id ?>][sales]" value="<?= sales_h($t['sales_target'] ?? '') ?>" style="width:120px;text-align:right"<?= $dis ?>></td>
<td class="num"><?= sales_money($st['net_sales']) ?></td><td class="num"><?= $ps === null ? '' : '<b class="' . ($ps >= 100 ? '' : 'qty-info') . '">' . $ps . '%</b>' ?></td>
<td><input type="number" min="0" step="0.01" name="t[<?= $id ?>][collection]" value="<?= sales_h($t['collection_target'] ?? '') ?>" style="width:120px;text-align:right"<?= $dis ?>></td>
<td class="num"><?= sales_money($st['collection']) ?></td><td class="num"><?= $pc === null ? '' : '<b class="' . ($pc >= 100 ? '' : 'qty-info') . '">' . $pc . '%</b>' ?></td>
<td><input name="t[<?= $id ?>][note]" maxlength="190" value="<?= sales_h($t['note'] ?? '') ?>"<?= $dis ?>></td></tr>
<?php endforeach; ?>
<?php if ($srs): ?><tr class="subtotal"><td colspan="3"><b>Total</b></td><td class="num"><b><?= sales_money($tot['st']) ?></b></td><td class="num"><b><?= sales_money($tot['net']) ?></b></td><td class="num"><?= sp_pct($tot['net'], $tot['st']) === null ? '' : sp_pct($tot['net'], $tot['st']) . '%' ?></td>
<td class="num"><b><?= sales_money($tot['ct']) ?></b></td><td class="num"><b><?= sales_money($tot['coll']) ?></b></td><td class="num"><?= sp_pct($tot['coll'], $tot['ct']) === null ? '' : sp_pct($tot['coll'], $tot['ct']) . '%' ?></td><td></td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($srs && $can): ?><div class="toolbar" style="margin-top:8px"><button class="btn primary">Save Targets</button><button class="btn" name="action" value="copy" formnovalidate>Copy Last Month</button><span class="muted" style="align-self:center">Clear both targets to remove an SR's target.</span></div><?php endif; ?>
</form>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
