<?php
/** My Sales: invoices credited to this SR in a month, and returns against them; ?id= shows one invoice (own sale or own customer). */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
$sr = [(int) $a['sr_id'], $a['sr_name']];

if (isset($_GET['id'])) {
    $inv = cp_row("SELECT v.* FROM sales_invoices v WHERE v.invoice_id=? AND v.status='posted'", 'i', [(int) $_GET['id']]);
    $mine = $inv && ((int) $inv['sr_id'] === $sr[0] || ($inv['sr_id'] === null && $inv['sales_person'] !== '' && $inv['sales_person'] === $sr[1]));
    if (!$inv || (!$mine && !($inv['customer_id'] && sr_customer($a, (int) $inv['customer_id'])))) {
        cp_redirect('sales.php', 'err', 'Invoice not found.');
    }
    $items = cp_rows('SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number, item_id', 'i', [(int) $inv['invoice_id']]);
    $pays = cp_rows('SELECT paid_at, payment_method, amount, reference FROM sales_invoice_payments WHERE invoice_id=? ORDER BY paid_at', 'i', [(int) $inv['invoice_id']]);
    sr_page_start('Invoice ' . $inv['invoice_no'], 'sales');
    echo '<div class="card meta"><div><b>Date:</b> ' . cp_date($inv['sales_date']) . '</div><div><b>Customer:</b> ' . ($inv['customer_id'] ? '<a href="customers.php?id=' . (int) $inv['customer_id'] . '">' . cp_h($inv['customer_name']) . '</a>' : cp_h($inv['customer_name'])) . '</div>'
        . '<div><b>Mobile:</b> ' . cp_h($inv['mobile']) . '</div><div><b>Bill type:</b> ' . cp_h($inv['bill_type']) . '</div><div><b>Sales person:</b> ' . cp_h($inv['sales_person']) . '</div></div>';
    echo '<div class="scroll"><table><thead><tr><th>SL</th><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Discount</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($items as $n => $it) {
        echo '<tr><td>' . ($n + 1) . '</td><td>' . cp_h(trim($it['model_caption'] . ' ' . $it['description'])) . '</td><td class="num">' . cp_qty($it['quantity']) . ((float) $it['free_quantity'] > 0 ? ' +' . cp_qty($it['free_quantity']) . ' free' : '') . '</td>'
            . '<td class="num">' . cp_money($it['unit_price']) . '</td><td class="num">' . cp_money($it['discount_amount']) . '</td><td class="num">' . cp_money($it['line_total']) . '</td></tr>';
    }
    echo '</tbody></table></div><table class="totals"><tr><td>Total</td><td class="num">' . cp_money($inv['subtotal']) . '</td></tr>'
        . ((float) $inv['discount_amount'] ? '<tr><td>Discount</td><td class="num">' . cp_money($inv['discount_amount']) . '</td></tr>' : '')
        . '<tr><td><b>Net</b></td><td class="num"><b>' . cp_money($inv['net_amount']) . '</b></td></tr><tr><td>Paid</td><td class="num">' . cp_money($inv['cash_received']) . '</td></tr><tr><td><b>Due</b></td><td class="num"><b>' . cp_money($inv['balance_amount']) . '</b></td></tr></table>';
    if ($pays) {
        echo '<h2>Payments</h2><table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>';
        foreach ($pays as $p) {
            echo '<tr><td>' . cp_date($p['paid_at']) . '</td><td>' . cp_h(ucfirst($p['payment_method'])) . '</td><td>' . cp_h($p['reference']) . '</td><td class="num">' . cp_money($p['amount']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<p><a class="btn light" href="sales.php?month=' . substr($inv['sales_date'], 0, 7) . '">Back to my sales</a></p>';
    sr_page_end();
    exit;
}

$month = sr_month($_GET['month'] ?? '');
$to = date('Y-m-t', strtotime($month));
$rows = cp_rows('SELECT v.invoice_id, v.invoice_no, v.sales_date, v.customer_id, v.customer_name, v.net_amount, v.cash_received, v.balance_amount FROM sales_invoices v WHERE ' . SR_MATCH . " AND v.status='posted' AND v.sales_date BETWEEN ? AND ? ORDER BY v.sales_date DESC, v.invoice_id DESC", 'isss', [$sr[0], $sr[1], $month, $to]);
$returns = cp_rows('SELECT r.return_no, r.return_date, v.invoice_no, r.customer_name, r.return_amount FROM sales_invoices v JOIN sales_returns r ON r.invoice_id=v.invoice_id AND r.status=\'posted\' WHERE ' . SR_MATCH . " AND v.status='posted' AND r.return_date BETWEEN ? AND ? ORDER BY r.return_date DESC", 'isss', [$sr[0], $sr[1], $month, $to]);
$sum = fn($k) => array_sum(array_column($rows, $k));
sr_page_start('My Sales', 'sales');
echo '<form method="get" class="card row"><div><label for="month">Month</label><input id="month" type="month" name="month" value="' . substr($month, 0, 7) . '"></div><div class="auto"><button class="btn">Show</button></div></form>';
echo '<div class="tiles"><div class="tile"><span>Invoices</span><b>' . count($rows) . '</b></div><div class="tile"><span>Sales</span><b>' . cp_money($sum('net_amount')) . '</b></div>'
    . '<div class="tile"><span>Returns</span><b>' . cp_money(array_sum(array_column($returns, 'return_amount'))) . '</b></div><div class="tile bad"><span>Still due on these</span><b>' . cp_money($sum('balance_amount')) . '</b></div></div>';
echo '<div class="scroll"><table><thead><tr><th>Date</th><th>Invoice No</th><th>Customer</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="6" class="empty">No sales credited to you this month.</td></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . cp_date($r['sales_date']) . '</td><td><a href="sales.php?id=' . (int) $r['invoice_id'] . '">' . cp_h($r['invoice_no']) . '</a></td><td>' . cp_h($r['customer_name']) . '</td>'
        . '<td class="num">' . cp_money($r['net_amount']) . '</td><td class="num">' . cp_money($r['cash_received']) . '</td><td class="num">' . cp_money($r['balance_amount']) . '</td></tr>';
}
echo '</tbody></table></div>';
if ($returns) {
    echo '<h2>Returns</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Return No</th><th>Invoice</th><th>Customer</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($returns as $r) {
        echo '<tr><td>' . cp_date($r['return_date']) . '</td><td>' . cp_h($r['return_no']) . '</td><td>' . cp_h($r['invoice_no']) . '</td><td>' . cp_h($r['customer_name']) . '</td><td class="num">' . cp_money($r['return_amount']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
sr_page_end();
