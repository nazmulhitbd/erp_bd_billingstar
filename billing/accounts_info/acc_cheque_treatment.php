<?php
/**
 * Cheque Treatment: mark received or issued cheques honoured or dishonoured.
 * Dishonour posts a journal voucher that reverses the original entry.
 */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Cheque Treatment', 'cheque_treat');
$self = 'acc_cheque_treatment.php';
$direction = in_array(($_REQUEST['direction'] ?? ''), ['received', 'issued'], true) ? (string) $_REQUEST['direction'] : '';
$upTo = sales_valid_date($_REQUEST['upto'] ?? '', true) ?: '';
$keep = http_build_query(['direction' => $direction, 'upto' => $upTo]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self . '?' . $keep);
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $date = sales_valid_date($_POST['treat_date'] ?? '') ;
    $note = sales_post_string('note', 255);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    try {
        if (!in_array($action, ['honoured', 'dishonoured'], true)) {
            throw new RuntimeException('Unknown action.');
        }
        if (!$ids) {
            throw new RuntimeException('Tick at least one cheque.');
        }
        if ($date === null) {
            throw new RuntimeException('Enter the treatment date.');
        }
        $conn->begin_transaction();
        $done = [];
        foreach ($ids as $id) {
            $c = sales_row($conn, "SELECT c.*, v.status AS voucher_status, v.voucher_no, v.branch_id FROM acc_cheques c JOIN acc_vouchers v ON v.voucher_id=c.voucher_id WHERE c.cheque_id=? FOR UPDATE", 'i', [$id]);
            if (!$c || $c['status'] !== 'pending') {
                continue;
            }
            if ($c['voucher_status'] !== 'posted') {
                throw new RuntimeException('Cheque ' . $c['cheque_no'] . ' is on ' . $c['voucher_no'] . ', which is not posted yet.');
            }
            acc_assert_unlocked($conn, $date, (int) $c['branch_id']);
            $reversal = null;
            if ($action === 'dishonoured') {
                $party = ['account_id' => (int) $c['party_account_id'], 'sub_ledger_id' => (int) $c['party_sub_ledger_id'], 'line_narration' => 'Cheque ' . $c['cheque_no'] . ' dishonoured', 'branch_id' => (int) $c['branch_id']];
                $bank = ['account_id' => (int) $c['bank_account_id'], 'line_narration' => 'Cheque ' . $c['cheque_no'] . ' dishonoured', 'branch_id' => (int) $c['branch_id']];
                $amount = (float) $c['amount'];
                $lines = $c['direction'] === 'received'
                    ? [$party + ['debit' => $amount], $bank + ['credit' => $amount]]
                    : [$bank + ['debit' => $amount], $party + ['credit' => $amount]];
                [$reversal, $no] = acc_create_voucher($conn, 'journal', $date, $lines, ['narration' => 'Dishonour of cheque ' . $c['cheque_no'] . ' (' . $c['voucher_no'] . ')' . ($note !== '' ? ': ' . $note : ''), 'source' => 'cheque_dishonour', 'ref_no' => $c['voucher_no']]);
                sales_exec($conn, 'UPDATE acc_vouchers SET branch_id=? WHERE voucher_id=?', 'ii', [(int) $c['branch_id'], $reversal])->close();
            }
            sales_exec($conn, 'UPDATE acc_cheques SET status=?, status_date=?, reversal_voucher_id=? WHERE cheque_id=?', 'ssii', [$action, $date, $reversal, $id])->close();
            sales_insert($conn, 'INSERT INTO acc_cheque_events (cheque_id, event_date, status, note, created_by) VALUES (?, ?, ?, ?, ?)', 'isssi', [$id, $date, $action, $note, $userId]);
            $done[] = $c['cheque_no'] . (isset($no) && $reversal ? ' (reversal ' . $no . ')' : '');
            unset($no);
        }
        $conn->commit();
        sales_flash_redirect($self . '?' . $keep, 'success', $done ? 'Marked ' . $action . ': ' . implode(', ', $done) . '.' : 'Nothing changed. Only pending cheques can be treated.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($self . '?' . $keep, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$where = ["c.status='pending'"];
$types = '';
$params = [];
if ($direction !== '') { $where[] = 'c.direction=?'; $types .= 's'; $params[] = $direction; }
if ($upTo !== '') { $where[] = 'COALESCE(c.cheque_date, v.voucher_date) <= ?'; $types .= 's'; $params[] = $upTo; }
$rows = sales_list_rows($conn, 'SELECT c.*, v.voucher_no, v.voucher_date, v.status AS voucher_status, a.account_name AS bank_name FROM acc_cheques c JOIN acc_vouchers v ON v.voucher_id=c.voucher_id JOIN acc_accounts a ON a.account_id=c.bank_account_id WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(c.cheque_date, v.voucher_date), c.cheque_id', $types, $params);

acc_page_start('Cheque Treatment', 'cheque_treat');
echo sales_flash_html($flash);
?>
<fieldset class="box"><legend>Cheque Treatment</legend>
<form method="get" class="list-search"><select class="input" name="direction" style="max-width:200px"><?= acc_options(['' => 'Received and issued', 'received' => 'Received cheques', 'issued' => 'Issued cheques'], $direction) ?></select>
<label style="align-self:center">Cheque date up to</label><input class="input" type="date" name="upto" value="<?= sales_h($upTo) ?>" style="max-width:170px"><button class="btn">Show</button></form>
<form method="post"><?= sales_csrf_field() ?><input type="hidden" name="direction" value="<?= sales_h($direction) ?>"><input type="hidden" name="upto" value="<?= sales_h($upTo) ?>">
<div class="table-scroll" style="max-height:520px"><table><thead><tr><th><input class="check" type="checkbox" id="tick-all"></th><th>Cheque No</th><th>Cheque Date</th><th>Direction</th><th>Party</th><th>Bank Account</th><th>Voucher</th><th>Voucher Status</th><th class="num">Amount</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No pending cheques.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><input class="check" type="checkbox" name="ids[]" value="<?= (int) $r['cheque_id'] ?>"></td><td><?= sales_h($r['cheque_no']) ?></td><td><?= acc_fmt_date($r['cheque_date']) ?></td><td><?= ucfirst($r['direction']) ?></td><td><?= sales_h($r['party_name']) ?></td><td><?= sales_h($r['bank_name']) ?></td>
<td><a target="_blank" href="acc_voucher.php?print=<?= (int) $r['voucher_id'] ?>"><?= sales_h($r['voucher_no']) ?></a></td><td><?= ucfirst(sales_h($r['voucher_status'])) ?></td><td class="num"><?= sales_money($r['amount']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="list-search" style="margin-top:10px"><label style="align-self:center">Date</label><input class="input" type="date" name="treat_date" value="<?= date('Y-m-d') ?>" required style="max-width:170px"><input class="input" name="note" maxlength="255" placeholder="Note, e.g. reason for dishonour">
<button class="btn primary" name="action" value="honoured">Mark Honoured</button><button class="btn danger" name="action" value="dishonoured" onclick="return confirm('Mark the ticked cheques dishonoured? A reversing journal voucher will be posted for each.')">Mark Dishonoured</button></div>
</form>
</fieldset>
<?php
acc_page_end('document.getElementById("tick-all")?.addEventListener("change",e=>document.querySelectorAll("input[name=\'ids[]\']").forEach(c=>c.checked=e.target.checked));');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
