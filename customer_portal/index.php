<?php
/** Dashboard: what the customer owes, credit available, latest invoices, open orders, open requests and notices. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];
$credit = (int) $a['allow_credit'] && (float) $a['credit_limit'] > 0;
$orders = cp_table_exists('sales_orders') ? (int) cp_row("SELECT COUNT(*) AS n FROM sales_orders WHERE customer_id=? AND status='open'", 'i', [$cid])['n'] : 0;
$openReq = (int) cp_row("SELECT COUNT(*) AS n FROM portal_requests WHERE customer_id=? AND status<>'closed'", 'i', [$cid])['n'];
$answered = (int) cp_row("SELECT COUNT(*) AS n FROM portal_requests WHERE customer_id=? AND status='answered'", 'i', [$cid])['n'];
$invoices = cp_rows("SELECT invoice_id, invoice_no, sales_date, net_amount, balance_amount FROM sales_invoices WHERE customer_id=? AND status='posted' ORDER BY sales_date DESC, invoice_id DESC LIMIT 5", 'i', [$cid]);
$notices = cp_rows('SELECT title, body, starts_on FROM portal_notices WHERE is_active=1 AND starts_on<=CURDATE() AND (ends_on IS NULL OR ends_on>=CURDATE()) ORDER BY starts_on DESC, notice_id DESC LIMIT 5');

cp_page_start('Welcome, ' . $a['customer_name'], 'index');
foreach ($notices as $n) {
    echo '<div class="alert info"><b>' . cp_h($n['title']) . '</b><div class="pre">' . cp_h($n['body']) . '</div></div>';
}
if ($answered) {
    echo '<div class="alert ok">Staff replied to ' . $answered . ' of your requests. <a href="requests.php">Read the reply</a>.</div>';
}
echo '<div class="tiles"><div class="tile' . ((float) $a['balance'] > 0 ? ' bad' : ' ok') . '"><span>You owe</span><b>' . cp_money($a['balance']) . '</b></div>'
    . ($credit ? '<div class="tile"><span>Credit limit</span><b>' . cp_money($a['credit_limit']) . '</b></div><div class="tile ok"><span>Credit available</span><b>' . cp_money(max(0, (float) $a['credit_limit'] - (float) $a['balance'])) . '</b></div>' : '')
    . '<div class="tile"><span>Total purchases</span><b>' . cp_money($a['total_sales']) . '</b></div><div class="tile"><span>Open orders</span><b>' . $orders . '</b></div><div class="tile"><span>Open requests</span><b>' . $openReq . '</b></div></div>';
if (!empty($a['oldest_due_date']) && (float) $a['balance'] > 0) {
    echo '<p class="muted">Your oldest unpaid invoice is from ' . cp_date($a['oldest_due_date']) . ($a['sr_name'] ? '. Your sales contact is ' . cp_h($a['sr_name']) . '.' : '.') . '</p>';
}
echo '<h2>Latest invoices</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Invoice No</th><th class="num">Amount</th><th class="num">Due</th><th></th></tr></thead><tbody>';
echo $invoices ? '' : '<tr><td colspan="5" class="empty">No invoices yet.</td></tr>';
foreach ($invoices as $i) {
    echo '<tr><td>' . cp_date($i['sales_date']) . '</td><td>' . cp_h($i['invoice_no']) . '</td><td class="num">' . cp_money($i['net_amount']) . '</td><td class="num">' . cp_money($i['balance_amount']) . '</td><td><a class="btn small light" href="invoices.php?id=' . (int) $i['invoice_id'] . '">View</a></td></tr>';
}
echo '</tbody></table></div><p><a href="invoices.php">All invoices</a> · <a href="statement.php">Statement</a> · <a href="requests.php?new=quotation">Ask for a quotation</a></p>';
cp_page_end();
