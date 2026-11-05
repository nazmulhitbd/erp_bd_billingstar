<?php
/**
 * SR Collections: money SRs recorded on the SR Portal. Verifying one (the amount can be corrected to what was
 * actually handed in) pays the customer's oldest due invoices: each part becomes a sales_invoice_payments row and
 * is listed in sr_collection_allocations. Rejecting keeps the record with the reason, which the SR sees.
 */
require_once __DIR__ . '/sales_person_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/notify.php';

$self = 'sr_collections.php';

/** Same rule as Sales Invoice: paid = sum of payments; balance = net - paid - due adjusted by posted returns. */
function src_refresh_invoice(mysqli $db, $invoiceId)
{
    sales_exec($db, 'UPDATE sales_invoices v SET cash_received=(SELECT COALESCE(SUM(amount), 0) FROM sales_invoice_payments p WHERE p.invoice_id=v.invoice_id), '
            . "balance_amount=GREATEST(0, net_amount - cash_received - (SELECT COALESCE(SUM(due_adjusted), 0) FROM sales_returns r WHERE r.invoice_id=v.invoice_id AND r.status='posted')) WHERE invoice_id=?", 'i', [(int) $invoiceId])->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_portal_ready($conn)) {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['status', 'sr', 'month'])));
    sales_require_csrf($back);
    mgmt_require(sp_can($conn, 'edit'), 'verifying SR collections');
    $id = (int) ($_POST['collection_id'] ?? 0);
    $note = sales_post_string('staff_note', 255);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $k = sales_row($conn, "SELECT k.*, c.customer_name FROM sr_collections k JOIN sales_customers c ON c.customer_id=k.customer_id WHERE k.collection_id=? FOR UPDATE", 'i', [$id]);
        if ($k && $k['status'] === 'verified' && ($_POST['action'] ?? '') === 'reverse') {
            $allocs = sales_rows($conn, 'SELECT invoice_id, payment_id FROM sr_collection_allocations WHERE collection_id=?', 'i', [$id]);
            foreach ($allocs as $al) {
                sales_exec($conn, 'DELETE FROM sales_invoice_payments WHERE payment_id=? AND invoice_id=?', 'ii', [(int) $al['payment_id'], (int) $al['invoice_id']])->close();
                src_refresh_invoice($conn, (int) $al['invoice_id']);
            }
            sales_exec($conn, 'DELETE FROM sr_collection_allocations WHERE collection_id=?', 'i', [$id])->close();
            sales_exec($conn, "UPDATE sr_collections SET status='pending', verified_amount=0, verified_by=NULL, verified_at=NULL, staff_note=? WHERE collection_id=?", 'si', [$note !== '' ? $note : 'Verification reversed', $id])->close();
            $conn->commit();
            sales_flash_redirect($back, 'success', $k['collection_no'] . ' reversed: its invoice payments were removed and it is waiting again.');
        }
        if (!$k || $k['status'] !== 'pending') {
            throw new RuntimeException('This collection is not waiting for verification any more.');
        }
        if (($_POST['action'] ?? '') === 'reject') {
            if ($note === '') {
                throw new RuntimeException('Give the reason for rejecting ' . $k['collection_no'] . ' (the SR sees it).');
            }
            sales_exec($conn, "UPDATE sr_collections SET status='rejected', verified_by=?, verified_at=NOW(), staff_note=? WHERE collection_id=?", 'isi', [$userId, $note, $id])->close();
            notify_staff((int) $k['sr_id'], 'Collection rejected: ' . $k['collection_no'], 'Please check with the office.', 'collections.php');
            $conn->commit();
            sales_flash_redirect($back, 'success', $k['collection_no'] . ' rejected.');
        }
        $amount = round((float) ($_POST['verified_amount'] ?? 0), 2);
        $invoices = sales_rows($conn, "SELECT invoice_id, invoice_no, balance_amount FROM sales_invoices WHERE customer_id=? AND status='posted' AND balance_amount>0.004 ORDER BY sales_date, invoice_id FOR UPDATE", 'i', [(int) $k['customer_id']]);
        $due = round(array_sum(array_column($invoices, 'balance_amount')), 2);
        if ($amount <= 0 || $amount > $due + 0.004) {
            throw new RuntimeException($due > 0 ? 'Verify an amount between 0.01 and ' . sales_money($due) . ' (' . $k['customer_name'] . "'s invoice due)." : $k['customer_name'] . ' has no invoice due any more. Reject the collection or record it as an advance in the office.');
        }
        $left = $amount;
        $paidAt = $k['collected_on'] . ' ' . date('H:i:s');
        $reference = sales_cut('SR collection ' . $k['collection_no'] . ($k['reference'] !== '' ? ' / ' . $k['reference'] : ''), 120);
        $paid = [];
        foreach ($invoices as $inv) {
            if ($left <= 0.004) {
                break;
            }
            $part = round(min($left, (float) $inv['balance_amount']), 2);
            $paymentId = sales_insert($conn, 'INSERT INTO sales_invoice_payments (invoice_id, paid_at, payment_method, amount, reference, received_by) VALUES (?, ?, ?, ?, ?, ?)',
                'issdsi', [(int) $inv['invoice_id'], $paidAt, $k['payment_method'], $part, $reference, $userId]);
            sales_insert($conn, 'INSERT INTO sr_collection_allocations (collection_id, invoice_id, payment_id, amount) VALUES (?, ?, ?, ?)', 'iiid', [$id, (int) $inv['invoice_id'], $paymentId, $part]);
            src_refresh_invoice($conn, (int) $inv['invoice_id']);
            $paid[] = $inv['invoice_no'];
            $left = round($left - $part, 2);
        }
        sales_exec($conn, "UPDATE sr_collections SET status='verified', verified_amount=?, verified_by=?, verified_at=NOW(), staff_note=? WHERE collection_id=?", 'disi', [$amount, $userId, $note, $id])->close();
        notify_staff((int) $k['sr_id'], 'Collection verified: ' . $k['collection_no'], sales_money($amount) . ' accepted by the office.', 'collections.php');
        notify_customer($k['customer_id'], 'Payment received', sales_money($amount) . ' received with thanks.', 'payments.php');
        $conn->commit();
        sales_flash_redirect($back, 'success', $k['collection_no'] . ' verified: ' . sales_money($amount) . ' paid on ' . implode(', ', $paid) . '.');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
sp_portal_page_start($conn, 'SR Collections', 'sr_collections');
echo sales_flash_html($flash);
$status = in_array($_GET['status'] ?? '', ['pending', 'verified', 'rejected', 'all'], true) ? (string) $_GET['status'] : 'pending';
$sr = (int) ($_GET['sr'] ?? 0);
$month = isset($_GET['month']) && $_GET['month'] !== '' ? sp_month($_GET['month']) : '';
$where = [$status === 'all' ? '1=1' : 'k.status=?'];
$types = $status === 'all' ? '' : 's';
$params = $status === 'all' ? [] : [$status];
if ($sr) {
    $where[] = 'k.sr_id=?';
    $types .= 'i';
    $params[] = $sr;
}
if ($month !== '') {
    $where[] = 'k.collected_on BETWEEN ? AND ?';
    $types .= 'ss';
    array_push($params, $month, date('Y-m-t', strtotime($month)));
}
$rows = sales_list_rows($conn, 'SELECT k.*, s.sr_name, c.customer_name, COALESCE(ca.invoice_due, 0) AS invoice_due FROM sr_collections k JOIN sales_representatives s ON s.sr_id=k.sr_id JOIN sales_customers c ON c.customer_id=k.customer_id '
        . 'LEFT JOIN customer_accounts ca ON ca.customer_id=k.customer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY k.status=\'pending\' DESC, k.collection_id DESC', $types, $params);
$alloc = [];
if ($rows) {
    $ids = array_column($rows, 'collection_id');
    foreach (sales_rows($conn, 'SELECT a.collection_id, v.invoice_no, a.amount FROM sr_collection_allocations a JOIN sales_invoices v ON v.invoice_id=a.invoice_id WHERE a.collection_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY a.allocation_id') as $a) {
        $alloc[(int) $a['collection_id']][] = $a['invoice_no'] . ' ' . sales_money($a['amount']);
    }
}
$pending = sales_row($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS s FROM sr_collections WHERE status='pending'");
$srs = sales_rows($conn, 'SELECT sr_id, sr_name FROM sales_representatives ORDER BY sr_name');
$can = sp_can($conn, 'edit');
$hidden = '<input type="hidden" name="status" value="' . sales_h($status) . '"><input type="hidden" name="sr" value="' . $sr . '"><input type="hidden" name="month" value="' . sales_h($month ? substr($month, 0, 7) : '') . '">';
?>
<div class="summary"><div>Waiting for verification<b><?= (int) $pending['n'] ?></b></div><div>Amount waiting<b><?= sales_money($pending['s']) ?></b></div></div>
<form method="get" class="list-search" style="align-items:center;margin-top:8px">
<select class="input" name="status" style="max-width:200px"><?php foreach (['pending' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<select class="input" name="sr" style="max-width:220px"><option value="0">All sales persons</option><?php foreach ($srs as $s): ?><option value="<?= (int) $s['sr_id'] ?>"<?= $sr === (int) $s['sr_id'] ? ' selected' : '' ?>><?= sales_h($s['sr_name']) ?></option><?php endforeach; ?></select>
<input class="input" type="month" name="month" value="<?= sales_h($month ? substr($month, 0, 7) : '') ?>" style="max-width:170px"><button class="btn primary">Show</button></form>
<div class="table-scroll" style="max-height:620px;margin-top:8px"><table><thead><tr><th>No</th><th>Date</th><th>Sales Person</th><th>Customer</th><th>Method / Ref</th><th>SR Note</th><th class="num">Amount</th><th class="num">Invoice Due</th><th>Status</th><th>Verify / Reject</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="10" class="empty">No collections.</td></tr><?php endif; ?>
<?php foreach ($rows as $k): ?>
<tr><td><?= sales_h($k['collection_no']) ?></td><td><?= inv_fmt_date($k['collected_on']) ?></td><td><?= sales_h($k['sr_name']) ?></td><td><a href="../customer/customer_profile.php?id=<?= (int) $k['customer_id'] ?>"><?= sales_h($k['customer_name']) ?></a></td>
<td><?= sales_h((SP_METHODS[$k['payment_method']] ?? $k['payment_method']) . ($k['reference'] !== '' ? ' · ' . $k['reference'] : '')) ?></td><td><?= sales_h($k['note']) ?></td><td class="num"><?= sales_money($k['amount']) ?></td><td class="num"><?= sales_money($k['invoice_due']) ?></td>
<?php if ($k['status'] === 'pending'): ?>
<td><span class="badge open">Waiting</span></td>
<td class="nowrap"><?php if ($can): ?><form method="post" class="inline"><?= sales_csrf_field() . $hidden ?><input type="hidden" name="collection_id" value="<?= (int) $k['collection_id'] ?>">
<input name="verified_amount" type="number" step="0.01" min="0.01" value="<?= sales_h(number_format(min((float) $k['amount'], (float) $k['invoice_due']), 2, '.', '')) ?>" style="width:110px;height:28px" aria-label="Amount received">
<input name="staff_note" maxlength="255" placeholder="Note / reason" style="width:150px;height:28px">
<button class="btn small primary" name="action" value="verify" onclick="return confirm('Verify and post this amount to the customer\'s oldest due invoices?')">Verify</button>
<button class="btn small danger" name="action" value="reject">Reject</button></form><?php endif; ?></td>
<?php else: ?>
<td><?= $k['status'] === 'verified' ? '<span class="badge converted">Verified ' . sales_money($k['verified_amount']) . '</span>' : '<span class="badge cancelled">Rejected</span>' ?><br><span class="muted"><?= $k['verified_at'] ? sales_h(date('d/m/Y h:i A', strtotime($k['verified_at']))) : '' ?></span></td>
<td><?= sales_h(implode(', ', $alloc[(int) $k['collection_id']] ?? [])) ?><?= $k['staff_note'] !== '' ? '<br><span class="muted">' . sales_h($k['staff_note']) . '</span>' : '' ?>
<?php if ($can && $k['status'] === 'verified'): ?><form method="post" class="inline"><?= sales_csrf_field() . $hidden ?><input type="hidden" name="collection_id" value="<?= (int) $k['collection_id'] ?>"><button class="btn small" name="action" value="reverse" onclick="return confirm('Remove the invoice payments this collection made and put it back to waiting?')">Reverse</button></form><?php endif; ?></td>
<?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div>
<p class="footnote">Verifying pays the customer's oldest due invoices first and counts toward the SR's collection figures and commission. Reverse removes the payments a verified collection made and puts it back to waiting.</p>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
