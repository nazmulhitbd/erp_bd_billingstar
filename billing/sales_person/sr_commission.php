<?php
/**
 * Commission: each SR's commission for a month (rate x net sales, collection or gross profit, plus the target bonus
 * when the sales target is reached), what has been paid and what is still due. Pay records a payment; Print gives
 * the commission sheet.
 */
require_once __DIR__ . '/sales_person_common.php';

$self = 'sr_commission.php';
$month = sp_month($_REQUEST['month'] ?? '');
$ym = substr($month, 0, 7);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_ready($conn)) {
    $back = $self . '?month=' . $ym;
    sales_require_csrf($back);
    mgmt_require(sp_can($conn, 'commission'), 'paying commission');
    try {
        $conn->begin_transaction();
        if (($_POST['action'] ?? '') === 'delete') {
            mgmt_require(sp_can($conn, 'delete'), 'deleting commission payments');
            sales_exec($conn, 'DELETE FROM sr_commission_payments WHERE payment_id=? AND period_month=?', 'is', [(int) ($_POST['payment_id'] ?? 0), $month])->close();
            $conn->commit();
            sales_flash_redirect($back, 'success', 'Payment deleted.');
        }
        $srId = (int) ($_POST['sr_id'] ?? 0);
        $row = null;
        foreach (sp_commission_rows($conn, $month) as $r) {
            if ((int) $r['sr_id'] === $srId) {
                $row = $r;
            }
        }
        if (!$row) {
            throw new RuntimeException('Pick a sales person.');
        }
        $amount = sales_amount($_POST['amount'] ?? 0);
        $paidOn = sales_valid_date($_POST['paid_on'] ?? '');
        if ($amount <= 0 || $paidOn === null) {
            throw new RuntimeException('Enter the amount and the payment date.');
        }
        if ($amount > $row['due'] + 0.004) {
            throw new RuntimeException('Only ' . sales_money(max(0, $row['due'])) . ' commission is due to ' . $row['sr_name'] . ' for ' . date('M Y', strtotime($month)) . '.');
        }
        $method = array_key_exists($_POST['payment_method'] ?? '', SP_METHODS) ? $_POST['payment_method'] : 'cash';
        sales_insert($conn, 'INSERT INTO sr_commission_payments (sr_id, period_month, amount, paid_on, payment_method, reference, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'isdssssi', [$srId, $month, $amount, $paidOn, $method, sales_post_string('reference', 120), sales_post_string('note', 190), (int) ($GLOBALS['user_id'] ?? 0)]);
        notify_staff($srId, 'Commission paid: ' . date('F Y', strtotime($month)), sales_money($amount) . ' paid on ' . date('d/m/Y', strtotime($paidOn)) . '.', 'performance.php');
        $conn->commit();
        sales_flash_redirect($back, 'success', 'Paid ' . sales_money($amount) . ' to ' . $row['sr_name'] . '.');
    } catch (RuntimeException $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$rows = sp_ready($conn) ? sp_commission_rows($conn, $month) : [];
$sum = fn($k) => array_sum(array_column($rows, $k));
$cols = ['sr_name' => 'SR', 'territory_name' => 'Territory', 'net_sales' => 'Net Sales', 'collection' => 'Collection', 'gross_profit' => 'Gross Profit', 'basis' => 'Commission On', 'basis_amount' => 'Basis Amount',
    'rate' => 'Rate', 'commission' => 'Commission', 'sales_target' => 'Sales Target', 'bonus' => 'Target Bonus', 'total' => 'Total', 'paid' => 'Paid', 'due' => 'Due'];
$cell = function (array $r, $k) {
    return match ($k) {
        'sr_name', 'territory_name' => sales_h($r[$k]),
        'basis' => sales_h(SP_BASIS[$r['commission_basis']]),
        'rate' => sales_h((float) $r['commission_rate'] . '%' . ((float) $r['target_bonus_rate'] ? ' +' . (float) $r['target_bonus_rate'] . '%' : '')),
        'sales_target' => $r['sales_target'] ? sales_money($r['sales_target']) . ($r['target_reached'] ? ' ✓' : '') : '',
        default => sales_money($r[$k]),
    };
};

if (isset($_GET['print']) && sp_ready($conn)) {
    mgmt_require(sp_can($conn, 'view'), 'viewing commission');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Commission ' . $ym . '</title><style>' . INV_PRINT_CSS . '</style></head><body onload="window.print()"><div class="bar"><button onclick="window.print()">Print</button></div>'
        . inv_print_head($conn, 'SR Commission Sheet · ' . date('F Y', strtotime($month))) . '<table><thead><tr>';
    foreach ($cols as $label) {
        echo '<th>' . $label . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach (array_keys($cols) as $k) {
            echo '<td' . (in_array($k, ['sr_name', 'territory_name', 'basis'], true) ? '' : ' class="num"') . '>' . $cell($r, $k) . '</td>';
        }
        echo '</tr>';
    }
    echo '<tr><td colspan="2"><b>Total</b></td>';
    foreach (array_slice(array_keys($cols), 2) as $k) {
        echo '<td class="num"><b>' . (in_array($k, ['basis', 'rate', 'sales_target'], true) ? '' : sales_money($sum($k))) . '</b></td>';
    }
    echo '</tr></tbody></table><div class="sign"><div>Prepared By</div><div>Checked By</div><div>Approved By</div><div>Received By</div></div></body></html>';
    exit;
}

$flash = sales_take_flash();
sp_page_start($conn, 'Commission', 'commission');
echo sales_flash_html($flash);
$can = sp_can($conn, 'commission');
$payments = sales_rows($conn, 'SELECT p.*, s.sr_name FROM sr_commission_payments p JOIN sales_representatives s ON s.sr_id=p.sr_id WHERE p.period_month=? ORDER BY p.paid_on, p.payment_id', 's', [$month]);
?>
<form method="get" class="list-search" style="align-items:center"><label class="nowrap">Month <input class="input" type="month" name="month" value="<?= $ym ?>" style="max-width:170px"></label><button class="btn primary">Show</button>
<a class="btn" target="_blank" href="<?= $self ?>?month=<?= $ym ?>&amp;print=1">Print</a></form>
<div class="summary"><div>Net Sales<b><?= sales_money($sum('net_sales')) ?></b></div><div>Commission<b><?= sales_money($sum('commission')) ?></b></div><div>Target Bonus<b><?= sales_money($sum('bonus')) ?></b></div>
<div>Paid<b><?= sales_money($sum('paid')) ?></b></div><div>Due<b class="qty-info"><?= sales_money($sum('due')) ?></b></div></div>
<div class="table-scroll" style="max-height:560px;margin-top:8px"><table><thead><tr><th>SL</th><?php foreach ($cols as $label): ?><th<?= in_array($label, ['SR', 'Territory', 'Commission On'], true) ? '' : ' class="num"' ?>><?= $label ?></th><?php endforeach; ?><th>Pay</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="<?= count($cols) + 2 ?>" class="empty">No sales persons.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?><tr><td><?= $i + 1 ?></td><?php foreach (array_keys($cols) as $k): ?><td<?= in_array($k, ['sr_name', 'territory_name', 'basis'], true) ? '' : ' class="num"' ?>><?= $cell($r, $k) ?></td><?php endforeach; ?>
<td class="nowrap"><?php if ($can && $r['due'] > 0.004): ?><form method="post" class="inline"><?= sales_csrf_field() ?><input type="hidden" name="month" value="<?= $ym ?>"><input type="hidden" name="sr_id" value="<?= (int) $r['sr_id'] ?>">
<input type="number" name="amount" min="0.01" step="0.01" value="<?= sales_h(number_format($r['due'], 2, '.', '')) ?>" style="width:100px"><input type="hidden" name="paid_on" value="<?= date('Y-m-d') ?>"><select name="payment_method"><?php foreach (SP_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
<button class="btn small primary">Pay</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<section class="section"><div class="section-title">Commission paid for <?= date('F Y', strtotime($month)) ?></div><table><thead><tr><th>Paid On</th><th>SR</th><th>Method</th><th>Reference</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<?php if (!$payments): ?><tr><td colspan="7" class="empty">No payments yet.</td></tr><?php endif; ?>
<?php foreach ($payments as $p): ?><tr><td><?= inv_fmt_date($p['paid_on']) ?></td><td><?= sales_h($p['sr_name']) ?></td><td><?= sales_h(SP_METHODS[$p['payment_method']] ?? $p['payment_method']) ?></td><td><?= sales_h($p['reference']) ?></td><td><?= sales_h($p['note']) ?></td><td class="num"><?= sales_money($p['amount']) ?></td>
<td><?php if (sp_can($conn, 'delete')): ?><form method="post" onsubmit="return confirm('Delete this payment?')"><?= sales_csrf_field() ?><input type="hidden" name="month" value="<?= $ym ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="payment_id" value="<?= (int) $p['payment_id'] ?>"><button class="btn small danger">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table><p class="footnote">These are commission payments recorded here. Post the matching Payment Voucher in Accounts to keep the ledgers in step.</p></section>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
