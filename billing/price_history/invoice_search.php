<?php
/**
 * Invoice Search by Mobile No.: everything sold to a customer, found by mobile number (last 10 digits, so 017..., +88017...
 * and 17... all match), invoice number or serial/IMEI. Shows the customer's totals and due, invoices, items with serials
 * and warranty, returns, sales orders, quotations and challans, with links to open, print or return.
 * View: price_history/schema.sql (sales_mobile_history).
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

$self = 'invoice_search.php';
inv_require_ready($conn, 'Invoice Search by Mobile No.', 'invoice_search');
$ready = sales_tables_ready($conn, ['sales_mobile_history', 'sales_invoices', 'sales_invoice_items', 'sales_returns', 'sales_orders']);

$q = trim((string) ($_GET['q'] ?? ''));
$allDate = !isset($_GET['from']) || !empty($_GET['all_date']);
$from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-01-01');
$to = sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d');
$error = '';
$key = '';
$found = null;

if ($ready && $q !== '') {
    // An invoice number or a serial points to that invoice's mobile; otherwise the input is the mobile itself.
    $hit = sales_row($conn, 'SELECT mobile FROM sales_invoices WHERE invoice_no=? LIMIT 1', 's', [$q])
        ?? sales_row($conn, "SELECT v.mobile FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id WHERE CONCAT('\n', REPLACE(REPLACE(REPLACE(i.serial_imei, '\r', ''), ', ', '\n'), ',', '\n'), '\n') LIKE ? ORDER BY v.invoice_id DESC LIMIT 1", 's', ['%' . "\n" . $q . "\n" . '%']);
    $digits = preg_replace('/\D/', '', $hit ? (string) $hit['mobile'] : $q);
    if ($hit && $digits === '') {
        $error = 'That invoice has no mobile number saved. Open it from the Sales Invoice list instead.';
    } elseif (strlen($digits) < 6) {
        $error = 'Enter at least 6 digits of the mobile number, an invoice number or a serial/IMEI.';
    } else {
        $key = substr($digits, -10);
    }
}

/** Rows of the history view for this mobile, optionally within the date range. */
function search_rows(mysqli $db, $key, array $kinds, $allDate, $from, $to)
{
    $sql = 'SELECT * FROM sales_mobile_history WHERE mobile_key=? AND doc_kind IN (' . implode(',', array_fill(0, count($kinds), '?')) . ')';
    $types = 's' . str_repeat('s', count($kinds));
    $params = array_merge([$key], $kinds);
    if (!$allDate) {
        $sql .= ' AND doc_date BETWEEN ? AND ?';
        $types .= 'ss';
        array_push($params, $from, $to);
    }
    return sales_rows($db, $sql . ' ORDER BY doc_date DESC, doc_id DESC LIMIT 1000', $types, $params);
}

inv_page_start($conn, 'Invoice Search by Mobile No.', 'invoice_search');
echo '<fieldset class="box"><legend>Invoice Search by Mobile No.</legend>';
if (!$ready) {
    echo '<div class="errorbox"><strong>The search view is not installed.</strong><br>Import <code>billing/price_history/schema.sql</code> (after the sales, inventory and <code>sales/schema_v2.sql</code> files) and reload this page.</div></fieldset></main></div></body></html>';
    exit;
}
?>
<form method="get" class="list-search" style="align-items:center">
<input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Mobile No, Invoice No or Serial/IMEI" autofocus style="max-width:320px">
<label class="nowrap"><input class="check" type="checkbox" name="all_date" value="1" <?= $allDate ? 'checked' : '' ?> onchange="this.form.from.disabled=this.form.to.disabled=this.checked"> All Date</label>
<input class="input" type="date" name="from" value="<?= sales_h($from) ?>" style="max-width:160px" <?= $allDate ? 'disabled' : '' ?>><input class="input" type="date" name="to" value="<?= sales_h($to) ?>" style="max-width:160px" <?= $allDate ? 'disabled' : '' ?>>
<button class="btn primary">Search</button>
</form>
<?php
if ($error !== '') {
    echo '<div class="alert error">' . sales_h($error) . '</div>';
}
if ($key !== '') {
    $invoices = search_rows($conn, $key, ['invoice'], $allDate, $from, $to);
    if (!$invoices && !search_rows($conn, $key, ['return', 'order', 'quotation', 'challan', 'challan_return'], true, $from, $to)) {
        echo '<div class="alert error">Nothing found for mobile ending ' . sales_h($key) . '.</div></fieldset></main></div></body></html>';
        exit;
    }
    $returns = search_rows($conn, $key, ['return'], $allDate, $from, $to);
    $orders = search_rows($conn, $key, ['order'], $allDate, $from, $to);
    $others = search_rows($conn, $key, ['quotation', 'challan', 'challan_return'], $allDate, $from, $to);
    // Totals over all dates, so the due is always the real outstanding.
    $tot = sales_row($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS sales, COALESCE(SUM(paid), 0) AS paid, COALESCE(SUM(due), 0) AS due, MIN(doc_date) AS first_date, MAX(doc_date) AS last_date FROM sales_mobile_history WHERE mobile_key=? AND doc_kind='invoice' AND status='posted'", 's', [$key]);
    $retTot = (float) sales_row($conn, "SELECT COALESCE(SUM(amount), 0) AS r FROM sales_mobile_history WHERE mobile_key=? AND doc_kind='return' AND status='posted'", 's', [$key])['r'];
    $customer = sales_row($conn, "SELECT * FROM sales_customers WHERE RIGHT(REGEXP_REPLACE(mobile, '[^0-9]', ''), 10)=? ORDER BY customer_id LIMIT 1", 's', [$key]);
    $latest = $invoices[0] ?? $returns[0] ?? $orders[0] ?? $others[0];
    $member = sales_tables_ready($conn, ['sales_members'])
        ? sales_row($conn, "SELECT m.*, m.opening_points + COALESCE((SELECT SUM(points) FROM sales_member_points p WHERE p.member_id=m.member_id), 0) AS balance FROM sales_members m WHERE RIGHT(REGEXP_REPLACE(m.mobile, '[^0-9]', ''), 10)=? OR m.customer_id=? LIMIT 1", 'si', [$key, (int) ($customer['customer_id'] ?? 0)])
        : null;

    echo '<div class="summary" style="margin-top:6px">'
        . '<div>Customer<b>' . sales_h($customer['customer_name'] ?? $latest['party_name']) . '</b></div><div>Mobile<b>' . sales_h($customer['mobile'] ?? $latest['mobile']) . '</b></div>'
        . '<div>Group<b>' . sales_h($customer['customer_group'] ?? $latest['party_group']) . '</b></div><div>Invoices<b>' . (int) $tot['n'] . '</b></div>'
        . '<div>Total Sales<b>' . sales_money($tot['sales']) . '</b></div><div>Returns<b>' . sales_money($retTot) . '</b></div><div>Paid<b>' . sales_money($tot['paid']) . '</b></div>'
        . '<div>Due<b class="qty-info">' . sales_money($tot['due']) . '</b></div>'
        . ($member ? '<div>Member<b>' . sales_h($member['member_no']) . ' · ' . sales_h((float) $member['balance']) . ' pts</b></div>' : '')
        . '<div>First / Last<b>' . sales_h(inv_fmt_date($tot['first_date'])) . ' – ' . sales_h(inv_fmt_date($tot['last_date'])) . '</b></div></div>';
    if (!empty($customer['address'])) {
        echo '<p class="footnote">Address: ' . sales_h($customer['address']) . '</p>';
    }

    // Invoices.
    echo '<section class="section"><div class="section-title">Sales Invoices (' . count($invoices) . ')</div><div class="table-scroll"><table><thead><tr><th>SL</th><th>Date</th><th>Invoice No</th><th>Ref No</th><th>Customer</th><th>Bill Type</th><th class="num">Qty</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th><th></th></tr></thead><tbody>';
    echo $invoices ? '' : '<tr><td colspan="12" class="empty">No invoices in this period.</td></tr>';
    foreach ($invoices as $i => $r) {
        $posted = $r['status'] === 'posted';
        echo '<tr><td>' . ($i + 1) . '</td><td>' . inv_fmt_date($r['doc_date']) . '</td><td>' . sales_h($r['doc_no']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td>' . sales_h($r['party_name']) . '</td><td>' . sales_h($r['bill_type']) . '</td>'
            . '<td class="num">' . sales_qty_fmt($r['total_quantity']) . '</td><td class="num">' . sales_money($r['amount']) . '</td><td class="num">' . sales_money($r['paid']) . '</td><td class="num">' . sales_money($r['due']) . '</td>'
            . '<td>' . ($posted ? '<span class="badge converted">Posted</span>' : '<span class="badge cancelled">Void</span>') . '</td><td class="nowrap">'
            . '<a class="btn small" href="../sales/sales_invoice/sales_invoice.php?id=' . (int) $r['doc_id'] . '">Open</a> <a class="btn small" target="_blank" href="../sales/sales_invoice/sales_invoice.php?print=' . (int) $r['doc_id'] . '">Print</a>'
            . ($posted ? ' <a class="btn small" href="../sales/sales_retrun/sales_return.php?invoice=' . urlencode($r['doc_no']) . '">Return</a>' : '') . '</td></tr>';
    }
    echo '</tbody></table></div></section>';

    // Items bought, with serials and warranty.
    if ($invoices) {
        $ids = array_map(fn($r) => (int) $r['doc_id'], array_filter($invoices, fn($r) => $r['status'] === 'posted'));
        $items = $ids ? sales_rows($conn, 'SELECT i.*, v.invoice_no, v.sales_date FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id WHERE i.invoice_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY v.sales_date DESC, i.invoice_id DESC, i.line_number', str_repeat('i', count($ids)), $ids) : [];
        echo '<section class="section"><div class="section-title">Items Bought (' . count($items) . ')</div><div class="table-scroll"><table><thead><tr><th>Date</th><th>Invoice No</th><th>Model No/Caption</th><th>Description</th><th>Serial/IMEI</th><th>Warranty</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Amount</th></tr></thead><tbody>';
        echo $items ? '' : '<tr><td colspan="9" class="empty">No items.</td></tr>';
        foreach ($items as $it) {
            $serials = inv_parse_serials($it['serial_imei']);
            $match = $q !== '' && in_array($q, $serials, true);
            echo '<tr' . ($match ? ' class="selected-row"' : '') . '><td>' . inv_fmt_date($it['sales_date']) . '</td><td>' . sales_h($it['invoice_no']) . '</td><td>' . sales_h(trim($it['product_code'] . ' ' . $it['model_caption'])) . '</td><td>' . sales_h($it['description']) . '</td>'
                . '<td>' . sales_h(implode(', ', $serials)) . '</td><td>' . sales_h($it['warranty']) . '</td><td class="num">' . sales_qty_fmt((float) $it['quantity'] + (float) $it['free_quantity']) . '</td>'
                . '<td class="num">' . sales_money($it['unit_price']) . '</td><td class="num">' . sales_money($it['line_total']) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    $links = [
        'return' => fn($r) => '../sales/sales_retrun/sales_return.php?print=' . (int) $r['doc_id'],
        'order' => fn($r) => '../sales/sales_order/sales_order.php?id=' . (int) $r['doc_id'],
        'quotation' => fn($r) => 'price_quotation.php?id=' . (int) $r['doc_id'],
        'challan' => fn($r) => '../challan/challan.php?id=' . (int) $r['doc_id'],
        'challan_return' => fn($r) => '../challan/challan_return.php?id=' . (int) $r['doc_id'],
    ];
    $labels = ['return' => 'Sales Return', 'order' => 'Sales Order', 'quotation' => 'Price Quotation', 'challan' => 'Challan', 'challan_return' => 'Challan Return'];
    foreach (['Sales Returns' => $returns, 'Sales Orders' => $orders, 'Quotations and Challans' => $others] as $title => $rows) {
        if (!$rows) {
            continue;
        }
        echo '<section class="section"><div class="section-title">' . $title . ' (' . count($rows) . ')</div><div class="table-scroll" style="max-height:260px"><table><thead><tr><th>Date</th><th>Type</th><th>No</th><th>Ref / Invoice</th><th class="num">Qty</th><th class="num">Amount</th><th class="num">' . ($title === 'Sales Returns' ? 'Refund' : 'Advance / Paid') . '</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . inv_fmt_date($r['doc_date']) . '</td><td>' . sales_h($labels[$r['doc_kind']] ?? $r['doc_kind']) . '</td><td>' . sales_h($r['doc_no']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td class="num">' . sales_qty_fmt($r['total_quantity']) . '</td>'
                . '<td class="num">' . sales_money($r['amount']) . '</td><td class="num">' . sales_money($r['paid']) . '</td><td>' . sales_h(ucfirst($r['status'])) . '</td>'
                . '<td><a class="btn small" target="' . ($r['doc_kind'] === 'return' ? '_blank' : '_self') . '" href="' . sales_h($links[$r['doc_kind']]($r)) . '">' . ($r['doc_kind'] === 'return' ? 'Print' : 'Open') . '</a></td></tr>';
        }
        echo '</tbody></table></div></section>';
    }
}
echo '</fieldset>';
inv_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
