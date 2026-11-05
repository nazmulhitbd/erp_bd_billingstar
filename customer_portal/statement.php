<?php
/** Statement: opening balance, then sales, payments, returns and refunds with a running balance for a period; printable. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) && strtotime($d) ? $d : null;
$from = $valid($_GET['from'] ?? '') ?? date('Y-01-01');
$to = $valid($_GET['to'] ?? '') ?? date('Y-m-d');
$before = (float) cp_row('SELECT COALESCE(SUM(debit - credit), 0) AS b FROM customer_ledger_entries WHERE customer_id=? AND entry_date<?', 'is', [$cid, $from])['b'];
$opening = round((float) $a['opening_balance'] + $before, 2);
$rows = cp_rows('SELECT * FROM customer_ledger_entries WHERE customer_id=? AND entry_date BETWEEN ? AND ? ORDER BY entry_date, sort_order, ref_id', 'iss', [$cid, $from, $to]);
$print = isset($_GET['print']);
$print ? cp_print_start('Statement of Account') : cp_page_start('Statement', 'statement');
if ($print) {
    echo '<div class="meta"><div><b>Customer:</b> ' . cp_h($a['customer_name']) . '</div><div><b>Period:</b> ' . cp_date($from) . ' to ' . cp_date($to) . '</div><div><b>Mobile:</b> ' . cp_h($a['mobile']) . '</div><div><b>Printed:</b> ' . date('d/m/Y h:i A') . '</div></div>';
} else {
    echo '<form method="get" class="card row"><div><label for="from">From</label><input id="from" type="date" name="from" value="' . cp_h($from) . '"></div><div><label for="to">To</label><input id="to" type="date" name="to" value="' . cp_h($to) . '"></div>'
        . '<div class="auto"><button class="btn">Show</button></div><div class="auto"><a class="btn light" href="statement.php?from=' . cp_h($from) . '&amp;to=' . cp_h($to) . '&amp;print=1">Print</a></div></form>';
}
$labels = ['Sales Invoice' => 'Purchase (invoice)', 'Sales Return' => 'Goods returned', 'Refund' => 'Refund paid to you'];
echo '<div class="scroll"><table><thead><tr><th>Date</th><th>Particulars</th><th>Document</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>'
    . '<tr><td></td><td><i>Opening balance</i></td><td></td><td></td><td></td><td class="num"><b>' . cp_money($opening) . '</b></td></tr>';
$bal = $opening;
$dr = $cr = 0.0;
foreach ($rows as $r) {
    $bal += (float) $r['debit'] - (float) $r['credit'];
    $dr += (float) $r['debit'];
    $cr += (float) $r['credit'];
    $type = str_starts_with($r['entry_type'], 'Receipt') ? 'Payment received (' . substr($r['entry_type'], 9, -1) . ')' : ($labels[$r['entry_type']] ?? $r['entry_type']);
    $doc = $r['sort_order'] == 1 && !$print ? '<a href="invoices.php?id=' . (int) $r['ref_id'] . '">' . cp_h($r['doc_no']) . '</a>' : cp_h($r['doc_no']);
    echo '<tr><td>' . cp_date($r['entry_date']) . '</td><td>' . cp_h($type) . '</td><td>' . $doc . '</td><td class="num">' . ((float) $r['debit'] ? cp_money($r['debit']) : '') . '</td><td class="num">' . ((float) $r['credit'] ? cp_money($r['credit']) : '') . '</td><td class="num">' . cp_money($bal) . '</td></tr>';
}
echo '<tr><td colspan="3"><b>Closing balance</b></td><td class="num"><b>' . cp_money($dr) . '</b></td><td class="num"><b>' . cp_money($cr) . '</b></td><td class="num"><b>' . cp_money($bal) . '</b></td></tr></tbody></table></div>';
echo '<p class="muted">A positive balance is the amount you owe. Payments recorded directly in our accounts may take a day to appear.</p>';
$print ? cp_print_end() : cp_page_end();
