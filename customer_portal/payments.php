<?php
/** Payments: every payment received on the customer's invoices, and refunds paid back. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];
$rows = cp_rows("SELECT entry_date, entry_type, doc_no, ref_no, debit, credit FROM customer_ledger_entries WHERE customer_id=? AND (entry_type LIKE 'Receipt%' OR entry_type='Refund') ORDER BY entry_date DESC, ref_id DESC LIMIT 500", 'i', [$cid]);
cp_page_start('Payments', 'payments');
$total = array_sum(array_column($rows, 'credit'));
echo '<div class="tiles"><div class="tile ok"><span>Paid to us (all time)</span><b>' . cp_money($total) . '</b></div><div class="tile' . ((float) $a['balance'] > 0 ? ' bad' : '') . '"><span>Still owing</span><b>' . cp_money($a['balance']) . '</b></div></div>';
echo '<div class="scroll"><table><thead><tr><th>Date</th><th>Type</th><th>Invoice</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="5" class="empty">No payments yet.</td></tr>';
foreach ($rows as $r) {
    $refund = $r['entry_type'] === 'Refund';
    echo '<tr><td>' . cp_date($r['entry_date']) . '</td><td>' . ($refund ? 'Refund to you' : cp_h('Payment (' . substr($r['entry_type'], 9, -1) . ')')) . '</td><td>' . cp_h($refund ? $r['ref_no'] : $r['doc_no']) . '</td><td>' . cp_h($refund ? $r['doc_no'] : $r['ref_no']) . '</td>'
        . '<td class="num">' . cp_money($refund ? $r['debit'] : $r['credit']) . '</td></tr>';
}
echo '</tbody></table></div><p><a href="requests.php?new=payment">Tell us about a payment</a></p>';
cp_page_end();
