<?php
/** Orders & Quotations: the customer's sales orders (with what is still to deliver) and price quotations. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];
$orders = cp_table_exists('sales_orders') ? cp_rows('SELECT o.order_id, o.order_no, o.order_date, o.delivery_date, o.status, o.net_amount, o.advance_received, o.total_quantity FROM sales_orders o WHERE o.customer_id=? ORDER BY o.order_date DESC, o.order_id DESC LIMIT 200', 'i', [$cid]) : [];
$quotes = !cp_table_exists('inv_docs') ? [] : cp_rows("SELECT doc_id, doc_no, doc_date, required_date, net_amount, total_quantity FROM inv_docs WHERE doc_type='quotation' AND status='active' AND party_id=? ORDER BY doc_date DESC, doc_id DESC LIMIT 200", 'i', [$cid]);

if (isset($_GET['order'])) {
    $o = cp_row('SELECT * FROM sales_orders WHERE order_id=? AND customer_id=?', 'ii', [(int) $_GET['order'], $cid]);
    if (!$o) {
        cp_redirect('orders.php', 'err', 'Order not found.');
    }
    $lines = cp_rows('SELECT i.*, COALESCE((SELECT SUM(b.quantity) FROM sales_order_bill_items b WHERE b.order_item_id=i.item_id), 0) AS billed FROM sales_order_items i WHERE i.order_id=? ORDER BY i.line_number', 'i', [(int) $o['order_id']]);
    cp_page_start('Order ' . $o['order_no'], 'orders');
    echo '<div class="card meta"><div><b>Order date:</b> ' . cp_date($o['order_date']) . '</div><div><b>Delivery date:</b> ' . cp_date($o['delivery_date']) . '</div><div><b>Status:</b> ' . cp_h(ucfirst($o['status'])) . '</div><div><b>Advance paid:</b> ' . cp_money($o['advance_received']) . '</div></div>';
    echo '<div class="scroll"><table><thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Billed</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($lines as $l) {
        echo '<tr><td>' . cp_h(trim($l['model_caption'] . ' ' . $l['description'])) . '</td><td class="num">' . cp_qty($l['quantity']) . '</td><td class="num">' . cp_qty($l['billed']) . '</td><td class="num">' . cp_money($l['unit_price']) . '</td><td class="num">' . cp_money($l['line_total']) . '</td></tr>';
    }
    echo '</tbody></table></div><table class="totals"><tr><td><b>Order total</b></td><td class="num"><b>' . cp_money($o['net_amount']) . '</b></td></tr></table><p><a class="btn light" href="orders.php">Back</a></p>';
    cp_page_end();
    exit;
}
if (isset($_GET['quote'])) {
    $q = cp_row("SELECT * FROM inv_docs WHERE doc_id=? AND doc_type='quotation' AND status='active' AND party_id=?", 'ii', [(int) $_GET['quote'], $cid]);
    if (!$q) {
        cp_redirect('orders.php', 'err', 'Quotation not found.');
    }
    $lines = cp_rows('SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [(int) $q['doc_id']]);
    $print = isset($_GET['print']);
    $print ? cp_print_start('Price Quotation ' . $q['doc_no']) : cp_page_start('Quotation ' . $q['doc_no'], 'orders');
    echo '<div class="meta"><div><b>Date:</b> ' . cp_date($q['doc_date']) . '</div>' . ($q['required_date'] ? '<div><b>Valid until:</b> ' . cp_date($q['required_date']) . '</div>' : '') . '</div>';
    echo '<div class="scroll"><table><thead><tr><th>Item</th><th>Warranty</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($lines as $l) {
        echo '<tr><td>' . cp_h(trim($l['model_caption'] . ' ' . $l['description'])) . '</td><td>' . cp_h($l['warranty']) . '</td><td class="num">' . cp_qty($l['quantity']) . '</td><td class="num">' . cp_money($l['unit_price']) . '</td><td class="num">' . cp_money($l['line_total']) . '</td></tr>';
    }
    echo '</tbody></table></div><table class="totals"><tr><td><b>Total</b></td><td class="num"><b>' . cp_money($q['net_amount']) . '</b></td></tr></table>';
    if (!empty($q['payment_term'])) {
        echo '<p class="pre"><b>Terms:</b> ' . cp_h($q['payment_term']) . '</p>';
    }
    if ($print) {
        cp_print_end();
        exit;
    }
    echo '<p><a class="btn" href="orders.php?quote=' . (int) $q['doc_id'] . '&amp;print=1">Print</a> <a class="btn light" href="requests.php?new=quotation&amp;ref=' . urlencode($q['doc_no']) . '">Accept or ask about this quotation</a> <a class="btn light" href="orders.php">Back</a></p>';
    cp_page_end();
    exit;
}

cp_page_start('Orders & Quotations', 'orders');
echo '<h2>Sales orders</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Order No</th><th>Delivery</th><th class="num">Amount</th><th class="num">Advance</th><th>Status</th><th></th></tr></thead><tbody>';
echo $orders ? '' : '<tr><td colspan="7" class="empty">No orders.</td></tr>';
foreach ($orders as $o) {
    $badge = ['open' => 'warn', 'converted' => 'ok', 'cancelled' => 'bad'][$o['status']] ?? '';
    echo '<tr><td>' . cp_date($o['order_date']) . '</td><td>' . cp_h($o['order_no']) . '</td><td>' . cp_date($o['delivery_date']) . '</td><td class="num">' . cp_money($o['net_amount']) . '</td><td class="num">' . cp_money($o['advance_received']) . '</td>'
        . '<td><span class="badge ' . $badge . '">' . cp_h($o['status'] === 'converted' ? 'Delivered / billed' : ucfirst($o['status'])) . '</span></td><td><a class="btn small light" href="orders.php?order=' . (int) $o['order_id'] . '">View</a></td></tr>';
}
echo '</tbody></table></div><h2>Price quotations</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Quotation No</th><th>Valid Until</th><th class="num">Amount</th><th></th></tr></thead><tbody>';
echo $quotes ? '' : '<tr><td colspan="5" class="empty">No quotations. <a href="requests.php?new=quotation">Ask for one</a>.</td></tr>';
foreach ($quotes as $q) {
    echo '<tr><td>' . cp_date($q['doc_date']) . '</td><td>' . cp_h($q['doc_no']) . '</td><td>' . cp_date($q['required_date']) . '</td><td class="num">' . cp_money($q['net_amount']) . '</td><td><a class="btn small light" href="orders.php?quote=' . (int) $q['doc_id'] . '">View</a></td></tr>';
}
echo '</tbody></table></div>';
cp_page_end();
