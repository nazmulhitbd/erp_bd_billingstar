<?php
/** Voucher Posting: post draft vouchers so they count in the reports, cancel drafts, or unpost posted vouchers for correction. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Voucher Posting', 'posting');

$self = 'acc_posting.php';
$status = in_array(($_REQUEST['status'] ?? 'draft'), ['draft', 'posted', 'cancelled', 'all'], true) ? (string) ($_REQUEST['status'] ?? 'draft') : 'draft';
$vtype = isset(ACC_VOUCHER_TYPES[$_REQUEST['vtype'] ?? '']) ? (string) $_REQUEST['vtype'] : '';
$ibFilter = in_array(($_REQUEST['ib'] ?? ''), ['0', '1'], true) ? (string) $_REQUEST['ib'] : '';
[$from, $to, , $all] = acc_period_from_request($_REQUEST + ['all' => !isset($_REQUEST['from']) ? 1 : 0]);
$keep = http_build_query(['status' => $status, 'vtype' => $vtype, 'ib' => $ibFilter, 'from' => $all ? '' : $from, 'to' => $all ? '' : $to, 'all' => $all ? 1 : '']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self . '?' . $keep);
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $rules = ['post' => ['draft', 'posted'], 'cancel' => ['draft', 'cancelled'], 'unpost' => ['posted', 'draft']];
    try {
        if (!isset($rules[$action])) {
            throw new RuntimeException('Unknown action.');
        }
        if (!$ids) {
            throw new RuntimeException('Tick at least one voucher.');
        }
        [$fromStatus, $toStatus] = $rules[$action];
        $conn->begin_transaction();
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $v = sales_row($conn, 'SELECT voucher_no, status, voucher_date, branch_id, created_by FROM acc_vouchers WHERE voucher_id=? FOR UPDATE', 'i', [$id]);
            if (!$v || $v['status'] !== $fromStatus) {
                $skipped[] = $v['voucher_no'] ?? ('#' . $id);
                continue;
            }
            acc_assert_unlocked($conn, $v['voucher_date'], (int) $v['branch_id']);
            if ($action === 'post') {
                $sum = sales_row($conn, 'SELECT COUNT(*) n, COALESCE(SUM(debit),0) dr, COALESCE(SUM(credit),0) cr FROM acc_voucher_lines WHERE voucher_id=?', 'i', [$id]);
                if ((int) $sum['n'] < 2 || abs((float) $sum['dr'] - (float) $sum['cr']) > 0.004) {
                    throw new RuntimeException($v['voucher_no'] . ' does not balance, so it was not posted. Open and fix it first.');
                }
                sales_exec($conn, "UPDATE acc_vouchers SET status='posted', posted_by=?, posted_at=NOW() WHERE voucher_id=?", 'ii', [$userId, $id])->close();
            } else {
                sales_exec($conn, 'UPDATE acc_vouchers SET status=?, posted_by=NULL, posted_at=NULL WHERE voucher_id=?', 'si', [$toStatus, $id])->close();
            }
            acc_sync_lines($conn, $id);
            if ($action !== 'post') {
                acc_sync_cheques($conn, $id);
            }
            if ((int) $v['created_by'] !== $userId) {
                notify_user($v['created_by'], 'Voucher ' . ['post' => 'posted', 'cancel' => 'cancelled', 'unpost' => 'moved back to draft'][$action] . ': ' . $v['voucher_no']);
            }
            $done++;
        }
        $conn->commit();
        $verb = ['post' => 'posted', 'cancel' => 'cancelled', 'unpost' => 'moved back to draft'][$action];
        sales_flash_redirect($self . '?' . $keep, 'success', $done . ' voucher(s) ' . $verb . '.' . ($skipped ? ' Skipped (wrong status): ' . implode(', ', $skipped) . '.' : ''));
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($self . '?' . $keep, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$where = ['v.voucher_date BETWEEN ? AND ?'];
$types = 'ss';
$params = [$from, $to];
if ($status !== 'all') { $where[] = 'v.status=?'; $types .= 's'; $params[] = $status; }
if ($vtype !== '') { $where[] = 'v.voucher_type=?'; $types .= 's'; $params[] = $vtype; }
if ($ibFilter !== '') { $where[] = 'v.is_inter_branch=' . (int) $ibFilter; }
$rows = sales_list_rows(
    $conn,
    'SELECT v.*, (SELECT GROUP_CONCAT(COALESCE(s.sub_ledger_name, a.account_name) ORDER BY l.line_no SEPARATOR \', \') FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id LEFT JOIN acc_sub_ledgers s ON s.sub_ledger_id=l.sub_ledger_id WHERE l.voucher_id=v.voucher_id) AS particulars, '
        . '(SELECT SUM(debit) - SUM(credit) FROM acc_voucher_lines l WHERE l.voucher_id=v.voucher_id) AS diff FROM acc_vouchers v WHERE ' . implode(' AND ', $where) . ' ORDER BY v.voucher_date, v.voucher_id',
    $types,
    $params
);

acc_page_start('Voucher Posting', 'posting');
echo sales_flash_html($flash);
?>
<fieldset class="box"><legend>Voucher Posting</legend>
<form method="get" class="list-search">
<select class="input" name="status" style="max-width:150px"><?= acc_options(['draft' => 'Draft', 'posted' => 'Posted', 'cancelled' => 'Cancelled', 'all' => 'All status'], $status) ?></select>
<select class="input" name="vtype" style="max-width:170px"><option value="">All voucher types</option><?php foreach (ACC_VOUCHER_TYPES as $k => $t): ?><option value="<?= $k ?>" <?= $vtype === $k ? 'selected' : '' ?>><?= $t['label'] ?></option><?php endforeach; ?></select>
<select class="input" name="ib" style="max-width:190px"><?= acc_options(['' => 'All branches mode', '0' => 'Normal vouchers', '1' => 'Inter branch only'], $ibFilter) ?></select>
<label style="align-self:center"><input class="check" type="checkbox" name="all" value="1" <?= $all ? 'checked' : '' ?>> All Date</label>
<input class="input" type="date" name="from" value="<?= $all ? '' : sales_h($from) ?>" style="max-width:160px"><input class="input" type="date" name="to" value="<?= $all ? '' : sales_h($to) ?>" style="max-width:160px">
<button class="btn primary">Show</button></form>
<form method="post"><?= sales_csrf_field() ?>
<input type="hidden" name="status" value="<?= sales_h($status) ?>"><input type="hidden" name="vtype" value="<?= sales_h($vtype) ?>"><input type="hidden" name="ib" value="<?= sales_h($ibFilter) ?>"><input type="hidden" name="from" value="<?= $all ? '' : sales_h($from) ?>"><input type="hidden" name="to" value="<?= $all ? '' : sales_h($to) ?>"><input type="hidden" name="all" value="<?= $all ? 1 : '' ?>">
<div class="toolbar"><button class="btn primary" name="action" value="post">Post ticked</button><button class="btn" name="action" value="unpost" onclick="return confirm('Move the ticked posted vouchers back to draft?')">Unpost ticked</button><button class="btn danger" name="action" value="cancel" onclick="return confirm('Cancel the ticked draft vouchers?')">Cancel ticked</button><b style="align-self:center">Total Select Amount: <span id="sel">0.00</span></b></div>
<div class="table-scroll" style="max-height:600px"><table><thead><tr><th><input class="check" type="checkbox" id="all-box"></th><th>SL</th><th>Voucher No</th><th>Type</th><th>Date</th><th>Particulars</th><th>Narration</th><th>Mode</th><th class="num">Amount</th><th>Status</th><th>Open</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="11" class="empty">No vouchers match.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?><tr<?= abs((float) $r['diff']) > 0.004 ? ' style="background:#fff0f0" title="Does not balance"' : '' ?>>
<td><input class="check" type="checkbox" name="ids[]" value="<?= (int) $r['voucher_id'] ?>" data-amt="<?= sales_h($r['total_amount']) ?>"></td><td><?= $i + 1 ?></td><td><?= sales_h($r['voucher_no']) ?></td><td><?= (int) $r['is_inter_branch'] ? 'Inter Branch ' : '' ?><?= sales_h(ACC_VOUCHER_TYPES[$r['voucher_type']]['label']) ?></td><td><?= acc_fmt_date($r['voucher_date']) ?></td><td><?= sales_h($r['particulars']) ?></td><td><?= sales_h($r['narration']) ?></td><td><?= sales_h($r['transaction_mode']) ?></td><td class="num"><?= sales_money($r['total_amount']) ?></td>
<td><span class="badge <?= ['draft' => 'open', 'posted' => 'converted', 'cancelled' => 'cancelled'][$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td>
<td class="nowrap"><a class="btn small" href="<?= in_array($r['voucher_type'], ['credit', 'debit'], true) ? 'acc_rp_voucher.php?type=' . sales_h($r['voucher_type']) . ((int) $r['is_inter_branch'] ? '&ib=1' : '') : 'acc_voucher.php?type=' . sales_h($r['voucher_type']) ?>&id=<?= (int) $r['voucher_id'] ?>">Open</a> <a class="btn small" target="_blank" href="acc_voucher.php?print=<?= (int) $r['voucher_id'] ?>">Print</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</form>
</fieldset>
<?php
acc_page_end('const boxes=()=>document.querySelectorAll("input[name=\'ids[]\']");function sum(){let t=0;boxes().forEach(b=>{if(b.checked)t+=Number(b.dataset.amt)});document.getElementById("sel").textContent=money(t)}
document.getElementById("all-box").addEventListener("change",e=>{boxes().forEach(b=>b.checked=e.target.checked);sum()});boxes().forEach(b=>b.addEventListener("change",sum));');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
