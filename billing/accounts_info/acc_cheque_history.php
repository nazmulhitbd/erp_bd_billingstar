<?php
/** Cheque History: search every received and issued cheque, with its status history. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Cheque History', 'cheque_history');

$q = trim((string) ($_GET['q'] ?? ''));
$status = in_array(($_GET['status'] ?? ''), ['pending', 'honoured', 'dishonoured', 'cancelled'], true) ? (string) $_GET['status'] : '';
[$from, $to, , $all] = acc_period_from_request($_GET + ['all' => !isset($_GET['from']) ? 1 : 0]);
$where = ['COALESCE(c.cheque_date, v.voucher_date) BETWEEN ? AND ?'];
$types = 'ss';
$params = [$from, $to];
if ($q !== '') { $where[] = '(c.cheque_no LIKE ? OR c.party_name LIKE ? OR v.voucher_no LIKE ?)'; $types .= 'sss'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($status !== '') { $where[] = 'c.status=?'; $types .= 's'; $params[] = $status; }
$rows = sales_list_rows(
    $conn,
    "SELECT c.*, v.voucher_no, a.account_name AS bank_name, rv.voucher_no AS reversal_no, (SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(e.event_date, '%d/%m/%Y'), ' ', e.status, IF(e.note<>'', CONCAT(' (', e.note, ')'), '')) ORDER BY e.event_id SEPARATOR '; ') FROM acc_cheque_events e WHERE e.cheque_id=c.cheque_id) AS events "
        . 'FROM acc_cheques c JOIN acc_vouchers v ON v.voucher_id=c.voucher_id JOIN acc_accounts a ON a.account_id=c.bank_account_id LEFT JOIN acc_vouchers rv ON rv.voucher_id=c.reversal_voucher_id WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(c.cheque_date, v.voucher_date) DESC, c.cheque_id DESC',
    $types,
    $params
);

acc_page_start('Cheque History', 'cheque_history');
?>
<fieldset class="box"><legend>Cheque History</legend>
<form method="get" class="list-search"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Cheque no, party or voucher no" autofocus>
<select class="input" name="status" style="max-width:160px"><?= acc_options(['' => 'Any status', 'pending' => 'Pending', 'honoured' => 'Honoured', 'dishonoured' => 'Dishonoured', 'cancelled' => 'Cancelled'], $status) ?></select>
<label style="align-self:center"><input class="check" type="checkbox" name="all" value="1" <?= $all ? 'checked' : '' ?>> All Date</label><input class="input" type="date" name="from" value="<?= $all ? '' : sales_h($from) ?>" style="max-width:160px"><input class="input" type="date" name="to" value="<?= $all ? '' : sales_h($to) ?>" style="max-width:160px"><button class="btn primary">Search</button></form>
<div class="table-scroll" style="max-height:620px"><table><thead><tr><th>SL</th><th>Cheque No</th><th>Cheque Date</th><th>Direction</th><th>Party</th><th>Bank Account</th><th>Voucher</th><th class="num">Amount</th><th>Status</th><th>History</th><th>Reversal</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="11" class="empty">No cheques found.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($r['cheque_no']) ?></td><td><?= acc_fmt_date($r['cheque_date']) ?></td><td><?= ucfirst($r['direction']) ?></td><td><?= sales_h($r['party_name']) ?></td><td><?= sales_h($r['bank_name']) ?></td><td><a target="_blank" href="acc_voucher.php?print=<?= (int) $r['voucher_id'] ?>"><?= sales_h($r['voucher_no']) ?></a></td><td class="num"><?= sales_money($r['amount']) ?></td>
<td><span class="badge <?= ['pending' => 'open', 'honoured' => 'converted', 'dishonoured' => 'cancelled', 'cancelled' => 'cancelled'][$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td><td><?= sales_h($r['events'] ?? '') ?></td><td><?= $r['reversal_no'] ? '<a target="_blank" href="acc_voucher.php?print=' . (int) $r['reversal_voucher_id'] . '">' . sales_h($r['reversal_no']) . '</a>' : '' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>
<?php
acc_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
