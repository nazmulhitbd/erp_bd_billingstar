<?php
/**
 * Sales Person (SR): add or edit an SR's profile, territory and commission settings; the list shows each SR's
 * customers and this month's net sales against target. An SR with customers, targets, commission or invoices
 * cannot be deleted (make it inactive).
 */
require_once __DIR__ . '/sales_person_common.php';

$self = 'sr_list.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_ready($conn)) {
    $id = (int) ($_POST['sr_id'] ?? 0);
    $back = $self . ($id ? '?edit=' . $id : '');
    sales_require_csrf($back);
    try {
        $conn->begin_transaction();
        $old = $id ? sales_row($conn, 'SELECT * FROM sales_representatives WHERE sr_id=? FOR UPDATE', 'i', [$id]) : null;
        if ($id && !$old) {
            throw new RuntimeException('SR not found.');
        }
        if (($_POST['action'] ?? '') === 'delete') {
            mgmt_require(sp_can($conn, 'delete'), 'deleting sales persons');
            foreach (['trader_sr_assignments' => 'customers', 'sr_targets' => 'targets', 'sr_commission_payments' => 'commission payments', 'sales_invoices' => 'invoices'] as $t => $what) {
                if (sales_row($conn, "SELECT 1 FROM $t WHERE sr_id=? LIMIT 1", 'i', [$id])) {
                    throw new RuntimeException($old['sr_name'] . ' has ' . $what . '. Make the SR inactive instead.');
                }
            }
            sales_exec($conn, 'DELETE FROM sales_representatives WHERE sr_id=?', 'i', [$id])->close();
            $conn->commit();
            sales_flash_redirect($self, 'success', $old['sr_name'] . ' deleted.');
        }
        mgmt_require(sp_can($conn, $old ? 'edit' : 'add'), $old ? 'editing sales persons' : 'adding sales persons');
        $name = sales_post_string('sr_name', 140);
        if ($name === '') {
            throw new RuntimeException('Enter the SR name.');
        }
        if (sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_name=? AND sr_id<>?', 'si', [$name, $id])) {
            throw new RuntimeException('Another SR is already named "' . $name . '". Sales are matched by name, so names must differ.');
        }
        $code = sales_post_string('sr_code', 30);
        if ($code !== '' && sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_code=? AND sr_id<>?', 'si', [$code, $id])) {
            throw new RuntimeException('SR code "' . $code . '" is already used.');
        }
        $territory = (int) ($_POST['territory_id'] ?? 0);
        if ($territory && !sales_row($conn, 'SELECT 1 FROM sr_territories WHERE territory_id=?', 'i', [$territory])) {
            throw new RuntimeException('Pick a valid territory.');
        }
        $joined = sales_valid_date($_POST['joining_date'] ?? '', true);
        if ($joined === null) {
            throw new RuntimeException('Enter a valid joining date or leave it blank.');
        }
        $email = sales_post_string('email', 190);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address or leave it blank.');
        }
        $basis = array_key_exists($_POST['commission_basis'] ?? '', SP_BASIS) ? $_POST['commission_basis'] : 'net_sales';
        $cols = ['sr_code' => $code !== '' ? $code : null, 'sr_name' => $name, 'mobile' => sales_post_string('mobile', 40), 'email' => $email, 'address' => sales_post_string('address', 255),
            'designation' => sales_post_string('designation', 80), 'territory_id' => $territory ?: null, 'joining_date' => $joined ?: null, 'commission_basis' => $basis,
            'commission_rate' => min(100.0, sales_amount($_POST['commission_rate'] ?? 0)), 'target_bonus_rate' => min(100.0, sales_amount($_POST['target_bonus_rate'] ?? 0)),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0];
        $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $cols));
        if ($old) {
            sales_exec($conn, 'UPDATE sales_representatives SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($cols))) . ' WHERE sr_id=?', $types . 'i', array_merge(array_values($cols), [$id]))->close();
            if ($old['sr_name'] !== $name) {
                // Keep the Sales Person name on this SR's invoices and orders in step.
                sales_exec($conn, 'UPDATE sales_invoices SET sales_person=? WHERE sr_id=?', 'si', [$name, $id])->close();
                sales_exec($conn, 'UPDATE sales_orders SET sales_person=? WHERE sr_id=?', 'si', [$name, $id])->close();
            }
        } else {
            $id = sales_insert($conn, 'INSERT INTO sales_representatives (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $types, array_values($cols));
            if ($code === '') {
                sales_exec($conn, "UPDATE sales_representatives SET sr_code=CONCAT('SR', LPAD(sr_id, 4, '0')) WHERE sr_id=?", 'i', [$id])->close();
            }
        }
        // Earlier invoices typed with this SR's name now count for the SR.
        sales_exec($conn, "UPDATE sales_invoices SET sr_id=? WHERE sr_id IS NULL AND sales_person=?", 'is', [$id, $name])->close();
        sales_exec($conn, "UPDATE sales_orders SET sr_id=? WHERE sr_id IS NULL AND sales_person=?", 'is', [$id, $name])->close();
        $conn->commit();
        sales_flash_redirect($self, 'success', $name . ' saved.');
    } catch (RuntimeException $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
sp_page_start($conn, 'Sales Person (SR)', 'sr');
echo sales_flash_html($flash);
$edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM sales_representatives WHERE sr_id=?', 'i', [(int) $_GET['edit']]) : null;
$territories = sales_rows($conn, 'SELECT territory_id, territory_name FROM sr_territories WHERE is_active=1 ORDER BY territory_name');
$month = date('Y-m-01');
$stats = sp_month_stats($conn, $month);
$targets = array_column(sales_rows($conn, 'SELECT sr_id, sales_target FROM sr_targets WHERE target_month=?', 's', [$month]), 'sales_target', 'sr_id');
$srs = sales_rows($conn, 'SELECT s.*, COALESCE(t.territory_name, \'\') AS territory_name, (SELECT COUNT(*) FROM trader_sr_assignments a WHERE a.sr_id=s.sr_id AND a.effective_to IS NULL) AS customers FROM sales_representatives s LEFT JOIN sr_territories t ON t.territory_id=s.territory_id ORDER BY s.is_active DESC, s.sr_name');
$srs = array_values(array_filter($srs, fn($s) => empty($s['staff_only'])));
$v = fn($k, $d = '') => sales_h($edit[$k] ?? $d);
$canSave = sp_can($conn, $edit ? 'edit' : 'add');
?>
<form method="post" class="section product-form" style="margin-top:0"><?= sales_csrf_field() ?><input type="hidden" name="sr_id" value="<?= (int) ($edit['sr_id'] ?? 0) ?>">
<div class="section-title"><?= $edit ? 'Edit ' . sales_h($edit['sr_name']) : 'New Sales Person' ?></div><div class="hgrid">
<div class="field s2"><label for="sr_code">SR Code</label><input id="sr_code" name="sr_code" maxlength="30" value="<?= $v('sr_code') ?>" placeholder="Auto"></div>
<div class="field s4"><label for="sr_name">Name *</label><input id="sr_name" name="sr_name" required maxlength="140" value="<?= $v('sr_name') ?>"></div>
<div class="field s2"><label for="mobile">Mobile</label><input id="mobile" name="mobile" maxlength="40" value="<?= $v('mobile') ?>"></div>
<div class="field s4"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" value="<?= $v('email') ?>"></div>
<div class="field s2"><label for="designation">Designation</label><input id="designation" name="designation" maxlength="80" value="<?= $v('designation') ?>"></div>
<div class="field s2"><label for="territory_id">Territory</label><select id="territory_id" name="territory_id"><option value="0">None</option><?php foreach ($territories as $t): ?><option value="<?= (int) $t['territory_id'] ?>"<?= (int) ($edit['territory_id'] ?? 0) === (int) $t['territory_id'] ? ' selected' : '' ?>><?= sales_h($t['territory_name']) ?></option><?php endforeach; ?></select></div>
<div class="field s2"><label for="joining_date">Joining Date</label><input id="joining_date" name="joining_date" type="date" value="<?= $v('joining_date') ?>"></div>
<div class="field s6"><label for="address">Address</label><input id="address" name="address" maxlength="255" value="<?= $v('address') ?>"></div>
<div class="field s2"><label for="commission_basis">Commission On</label><select id="commission_basis" name="commission_basis"><?php foreach (SP_BASIS as $k => $l): ?><option value="<?= $k ?>"<?= ($edit['commission_basis'] ?? 'net_sales') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<div class="field s2"><label for="commission_rate">Commission %</label><input id="commission_rate" name="commission_rate" type="number" min="0" max="100" step="0.01" value="<?= $v('commission_rate', '0') ?>"></div>
<div class="field s2"><label for="target_bonus_rate">Target Bonus %</label><input id="target_bonus_rate" name="target_bonus_rate" type="number" min="0" max="100" step="0.01" value="<?= $v('target_bonus_rate', '0') ?>" title="Extra percent of net sales when the month target is reached"></div>
<div class="field s2"><label>&nbsp;</label><label><input class="check" type="checkbox" name="is_active" value="1"<?= (int) ($edit['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label></div>
<div class="field s4"><label>&nbsp;</label><div class="inline"><button class="btn primary"<?= $canSave ? '' : ' disabled' ?>><?= $edit ? 'Save' : 'Add' ?></button><?php if ($edit): ?><a class="btn" href="<?= $self ?>">New</a>
<?php if (sp_can($conn, 'delete')): ?><button class="btn danger" name="action" value="delete" formnovalidate onclick="return confirm('Delete this SR?')">Delete</button><?php endif; ?><?php endif; ?></div></div>
</div><p class="footnote">Sales count for an SR when the invoice's Sales Person is the SR's name. Customers are given an SR in Customer Profile or Trader wise SR Fixing.</p></form>
<div class="table-scroll" style="max-height:600px;margin-top:8px"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Designation</th><th>Territory</th><th>Mobile</th><th class="num">Customers</th><th>Commission</th>
<th class="num">Net Sales <?= date('M Y') ?></th><th class="num">Target</th><th class="num">Achieved</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$srs): ?><tr><td colspan="13" class="empty">No sales persons yet.</td></tr><?php endif; ?>
<?php foreach ($srs as $i => $s): $net = $stats[(int) $s['sr_id']]['net_sales'] ?? 0; $tg = (float) ($targets[(int) $s['sr_id']] ?? 0); $pct = sp_pct($net, $tg); ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($s['sr_code']) ?></td><td><?= sales_h($s['sr_name']) ?></td><td><?= sales_h($s['designation']) ?></td><td><?= sales_h($s['territory_name']) ?></td><td><?= sales_h($s['mobile']) ?></td>
<td class="num"><?= (int) $s['customers'] ?></td><td><?= $s['commission_basis'] === 'none' ? 'None' : sales_h((float) $s['commission_rate'] . '% of ' . strtolower(SP_BASIS[$s['commission_basis']])) . ((float) $s['target_bonus_rate'] ? ' +' . (float) $s['target_bonus_rate'] . '% bonus' : '') ?></td>
<td class="num"><?= sales_money($net) ?></td><td class="num"><?= $tg ? sales_money($tg) : '' ?></td><td class="num"><?= $pct === null ? '' : $pct . '%' ?></td>
<td><?= (int) $s['is_active'] ? '<span class="badge converted">Active</span>' : '<span class="badge cancelled">Inactive</span>' ?></td><td><a class="btn small" href="<?= $self ?>?edit=<?= (int) $s['sr_id'] ?>">Edit</a></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
