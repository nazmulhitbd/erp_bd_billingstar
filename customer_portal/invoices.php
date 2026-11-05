<?php
/** Invoices: the customer's own invoices; ?id= shows one invoice with its lines and payments, &print=1 prints it. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];

if (isset($_GET['id'])) {
    $inv = cp_row("SELECT * FROM sales_invoices WHERE invoice_id=? AND customer_id=? AND status='posted'", 'ii', [(int) $_GET['id'], $cid]);
    if (!$inv) {
        cp_redirect('invoices.php', 'err', 'Invoice not found.');
    }
    $items = cp_rows('SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number, item_id', 'i', [(int) $inv['invoice_id']]);
    $pays = cp_rows('SELECT paid_at, payment_method, amount FROM sales_invoice_payments WHERE invoice_id=? ORDER BY paid_at', 'i', [(int) $inv['invoice_id']]);
    $print = isset($_GET['print']);
    $print ? cp_print_start('Invoice ' . $inv['invoice_no']) : cp_page_start('Invoice ' . $inv['invoice_no'], 'invoices');
    echo '<div class="' . ($print ? '' : 'card ') . 'meta"><div><b>Invoice No:</b> ' . cp_h($inv['invoice_no']) . '</div><div><b>Date:</b> ' . cp_date($inv['sales_date']) . '</div>'
        . '<div><b>Customer:</b> ' . cp_h($inv['customer_name']) . '</div><div><b>Bill Type:</b> ' . cp_h($inv['bill_type']) . '</div>'
        . ($inv['delivery_point'] !== '' ? '<div><b>Delivery Point:</b> ' . cp_h($inv['delivery_point']) . '</div>' : '') . ($inv['sales_person'] !== '' ? '<div><b>Sales Person:</b> ' . cp_h($inv['sales_person']) . '</div>' : '') . '</div>';
    echo '<div class="scroll"><table><thead><tr><th>SL</th><th>Item</th><th>Serial / IMEI</th><th>Warranty</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Discount</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($items as $n => $it) {
        $serials = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $it['serial_imei'])), 'strlen');
        echo '<tr><td>' . ($n + 1) . '</td><td>' . cp_h(trim($it['model_caption'] . ' ' . $it['description'])) . '</td><td>' . cp_h(implode(', ', $serials)) . '</td><td>' . cp_h($it['warranty']) . '</td>'
            . '<td class="num">' . cp_qty($it['quantity']) . ((float) $it['free_quantity'] > 0 ? ' +' . cp_qty($it['free_quantity']) . ' free' : '') . '</td><td class="num">' . cp_money($it['unit_price']) . '</td><td class="num">' . cp_money($it['discount_amount']) . '</td><td class="num">' . cp_money($it['line_total']) . '</td></tr>';
    }
    echo '</tbody></table></div><table class="totals">';
    $tot = ['Total Amount' => $inv['subtotal'], 'Extra Charges' => $inv['extra_charges'], 'Discount' => $inv['discount_amount'], 'VAT & AIT' => (float) $inv['vat_amount'] + (float) $inv['ait_amount'], 'Product Exchange' => $inv['exchange_amount'] ?? 0, 'Point Adjust' => $inv['point_adjust'] ?? 0];
    foreach ($tot as $label => $v) {
        if ($label === 'Total Amount' || (float) $v != 0) {
            echo '<tr><td>' . cp_h($label) . '</td><td class="num">' . cp_money($v) . '</td></tr>';
        }
    }
    echo '<tr><td><b>Net Payable</b></td><td class="num"><b>' . cp_money($inv['net_amount']) . '</b></td></tr><tr><td>Paid</td><td class="num">' . cp_money($inv['cash_received']) . '</td></tr><tr><td><b>Due</b></td><td class="num"><b>' . cp_money($inv['balance_amount']) . '</b></td></tr></table>';
    if ($pays) {
        echo '<h2>Payments</h2><table><thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead><tbody>';
        foreach ($pays as $p) {
            echo '<tr><td>' . cp_date($p['paid_at']) . '</td><td>' . cp_h(ucfirst($p['payment_method'])) . '</td><td class="num">' . cp_money($p['amount']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    if ($print) {
        cp_print_end();
        exit;
    }
    echo '<p><a class="btn" href="invoices.php?id=' . (int) $inv['invoice_id'] . '&amp;print=1">Print</a> <a class="btn light" href="invoices.php">All invoices</a> <a class="btn light" href="requests.php?new=support&amp;ref=' . urlencode($inv['invoice_no']) . '">Ask about this invoice</a></p>';
    cp_page_end();
    exit;
}

$due = !empty($_GET['due']);
$rows = cp_rows("SELECT invoice_id, invoice_no, sales_date, net_amount, cash_received, balance_amount, total_quantity FROM sales_invoices WHERE customer_id=? AND status='posted'" . ($due ? ' AND balance_amount>0.004' : '') . ' ORDER BY sales_date DESC, invoice_id DESC LIMIT 500', 'i', [$cid]);
cp_page_start('Invoices', 'invoices');
echo '<div class="tabs"><a class="btn small' . ($due ? ' light' : '') . '" href="invoices.php">All</a><a class="btn small' . ($due ? '' : ' light') . '" href="invoices.php?due=1">Unpaid</a></div>';
echo '<div class="scroll"><table><thead><tr><th>Date</th><th>Invoice No</th><th class="num">Qty</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th><th></th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="7" class="empty">No invoices.</td></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . cp_date($r['sales_date']) . '</td><td>' . cp_h($r['invoice_no']) . '</td><td class="num">' . cp_qty($r['total_quantity']) . '</td><td class="num">' . cp_money($r['net_amount']) . '</td><td class="num">' . cp_money($r['cash_received']) . '</td>'
        . '<td class="num">' . ((float) $r['balance_amount'] > 0.004 ? '<span class="badge bad">' . cp_money($r['balance_amount']) . '</span>' : '<span class="badge ok">Paid</span>') . '</td><td><a class="btn small light" href="invoices.php?id=' . (int) $r['invoice_id'] . '">View</a></td></tr>';
}
echo '</tbody></table></div>';
cp_page_end();
