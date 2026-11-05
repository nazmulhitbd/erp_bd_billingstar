<?php
/**
 * Sales Invoice/Bill: sell from a store (stock out with serials), with payment schedule, cash receipt, product exchange,
 * member points, additional info, attachments and 12 print layouts (Custom Preview), plus challan prints.
 * A saved invoice can be edited (Edit), re-priced (Edit Price) or have its details changed (Edit Details) until it is
 * approved; Edit and Edit Price are also blocked once a return, order bill or challan bill depends on it.
 * Tables: sales/schema.sql, inventory/schema.sql, sales/schema_v2.sql.
 */
require_once dirname(__DIR__) . '/sales_doc.php';

$self = 'sales_invoice.php';
$ready = sdoc_ready($conn) && sales_tables_ready($conn, ['sales_invoices', 'sales_invoice_items', 'sales_invoice_payments', 'sales_invoice_schedules', 'sales_invoice_sequences', 'sales_returns']);
if (!$ready) {
    sdoc_page_start($conn, 'Sales Invoice/Bill', 'invoice');
    echo sdoc_missing_html();
    sdoc_page_end();
    exit;
}
$userId = (int) ($GLOBALS['user_id'] ?? 0);
const SI_CASH_REF = 'Cash at sale';

/** Custom Preview layouts: [button label, title, options]. */
const SI_TEMPLATES = [
    1 => ['Invoice1', 'Standard A4', []],
    2 => ['Invoice2', 'With Serial and Warranty', ['warranty' => true]],
    3 => ['Invoice3', 'Without Price (delivery copy)', ['price' => false]],
    4 => ['Invoice4', 'Half Page (A5)', ['size' => 'a5']],
    5 => ['Invoice5', 'POS Receipt 80 mm', ['size' => '80']],
    6 => ['Invoice6', 'POS Receipt 58 mm', ['size' => '58']],
    7 => ['Invoice7', 'Customer and Office Copy', ['copies' => ['Customer Copy', 'Office Copy']]],
    8 => ['Invoice8', 'With Previous Due', ['due' => true]],
    9 => ['Invoice9', 'With Schedule and Receipts', ['schedule' => true]],
    10 => ['Invoice10', 'Pad Print (no letterhead)', ['header' => false]],
    11 => ['Invoice11', 'VAT Invoice', ['vat' => true]],
    12 => ['Invoice12', 'Gate Pass', ['price' => false, 'gatepass' => true]],
];

function si_load(mysqli $db, $id)
{
    $inv = sales_row($db, 'SELECT * FROM sales_invoices WHERE invoice_id=?', 'i', [(int) $id]);
    if (!$inv) {
        return null;
    }
    return [
        'inv' => $inv,
        'items' => sales_rows($db, 'SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number, item_id', 'i', [(int) $id]),
        'payments' => sales_rows($db, 'SELECT * FROM sales_invoice_payments WHERE invoice_id=? ORDER BY paid_at, payment_id', 'i', [(int) $id]),
        'schedules' => sales_rows($db, 'SELECT * FROM sales_invoice_schedules WHERE invoice_id=? ORDER BY due_date, schedule_id', 'i', [(int) $id]),
        'exchanges' => sales_rows($db, 'SELECT * FROM sales_invoice_exchanges WHERE invoice_id=? ORDER BY exchange_id', 'i', [(int) $id]),
    ];
}

/** Why the lines of an invoice can no longer change ('' when they can). */
function si_line_lock(mysqli $db, array $inv)
{
    if ($inv['status'] !== 'posted') {
        return $inv['invoice_no'] . ' is void.';
    }
    if (($inv['approval_status'] ?? '') === 'approved') {
        return $inv['invoice_no'] . ' is approved and can no longer be changed.';
    }
    if (sales_row($db, "SELECT 1 FROM sales_returns WHERE invoice_id=? AND status='posted' LIMIT 1", 'i', [(int) $inv['invoice_id']])) {
        return $inv['invoice_no'] . ' has a sales return. Void the return first.';
    }
    if (sales_tables_ready($db, ['sales_order_bills']) && sales_row($db, 'SELECT 1 FROM sales_order_bills WHERE invoice_id=? LIMIT 1', 'i', [(int) $inv['invoice_id']])) {
        return $inv['invoice_no'] . ' was billed from a sales order. Change it through the order instead.';
    }
    if (sales_row($db, "SELECT 1 FROM inv_docs WHERE sales_invoice_id=? AND doc_type='challan_bill' AND status='active' LIMIT 1", 'i', [(int) $inv['invoice_id']])) {
        return $inv['invoice_no'] . ' was billed from a challan. Change the challan bill instead.';
    }
    return '';
}

/** Paid = sum of payments; balance = net - paid - due adjusted by posted returns. */
function si_refresh_balance(mysqli $db, $invoiceId)
{
    sales_exec(
        $db,
        'UPDATE sales_invoices v SET cash_received=(SELECT COALESCE(SUM(amount), 0) FROM sales_invoice_payments p WHERE p.invoice_id=v.invoice_id), '
            . "balance_amount=GREATEST(0, net_amount - cash_received - (SELECT COALESCE(SUM(due_adjusted), 0) FROM sales_returns r WHERE r.invoice_id=v.invoice_id AND r.status='posted')) WHERE invoice_id=?",
        'i',
        [(int) $invoiceId]
    )->close();
}

/** Header values from the form. */
function si_header_post()
{
    return [
        'ref_no' => sales_post_string('ref_no', 80), 'customer_group' => sales_post_string('customer_group', 120), 'customer_name' => sales_post_string('customer_name', 190),
        'mobile' => sales_post_string('mobile', 40), 'attention' => sales_post_string('attention', 190), 'address' => sales_post_string('address', 2000),
        'delivery_point' => sales_post_string('delivery_point', 255), 'sales_person' => sales_post_string('sales_person', 140), 'narration' => sales_post_string('narration', 2000),
        'bin' => sales_post_string('bin', 60), 'nid' => sales_post_string('nid', 60), 'create_installation' => !empty($_POST['create_installation']) ? 1 : 0,
        'additional_info' => sdoc_additional_from_post(),
    ];
}

function si_bind(array $values)
{
    return implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $values));
}

/** Payment schedule rows from the form: days after the sales date or a date, and an amount. */
function si_schedules_post($salesDate)
{
    $out = [];
    foreach (json_decode((string) ($_POST['schedules_json'] ?? '[]'), true) ?: [] as $s) {
        $date = sales_valid_date($s['due_date'] ?? '');
        if ($date === null) {
            throw new RuntimeException('Each payment schedule row needs a valid date.');
        }
        $out[] = [$date, sales_amount($s['amount'] ?? 0), sales_str($s['note'] ?? '', 190)];
    }
    return $out;
}

/* ---------- Requests that are not page loads ---------- */

sdoc_attachment_request($conn, 'invoice', $self);

if (isset($_GET['info'])) {
    $info = sdoc_product_info($conn, (int) $_GET['info'], trim((string) ($_GET['store'] ?? '')), (int) ($_GET['customer'] ?? 0));
    sales_json($info);
}
if (isset($_GET['customer_info'])) {
    $cid = (int) $_GET['customer_info'];
    $advance = sales_tables_ready($conn, ['sales_order_bills'])
        ? (float) (sales_row($conn, "SELECT COALESCE(SUM(o.advance_received - COALESCE((SELECT SUM(b.advance_applied) FROM sales_order_bills b WHERE b.order_id=o.order_id), 0)), 0) AS a FROM sales_orders o WHERE o.customer_id=? AND o.status='open'", 'i', [$cid])['a'] ?? 0)
        : 0.0;
    $due = (float) (sales_row($conn, "SELECT COALESCE(SUM(balance_amount), 0) AS d FROM sales_invoices WHERE customer_id=? AND status='posted'", 'i', [$cid])['d'] ?? 0);
    sales_json(['advance' => $advance, 'due' => $due]);
}
if (isset($_GET['member'])) {
    $m = sdoc_member($conn, trim((string) $_GET['member']));
    if (!$m) {
        sales_json(['error' => 'No active member with this number.'], 404);
    }
    sales_json(['member_id' => (int) $m['member_id'], 'member_no' => $m['member_no'], 'name' => $m['member_name'], 'balance' => sdoc_member_balance($conn, (int) $m['member_id'], (int) ($_GET['exclude'] ?? 0)), 'settings' => sdoc_point_settings($conn)]);
}
foreach (['search' => 'invoice_no', 'ref' => 'ref_no', 'mobile' => 'mobile'] as $param => $col) {
    if (isset($_GET[$param]) && trim((string) $_GET[$param]) !== '') {
        $found = sales_row($conn, "SELECT invoice_id FROM sales_invoices WHERE $col=? ORDER BY invoice_id DESC LIMIT 1", 's', [trim((string) $_GET[$param])]);
        if ($found) {
            header('Location: ' . $self . '?id=' . (int) $found['invoice_id'], true, 303);
            exit;
        }
        sales_flash_redirect($self, 'error', 'No invoice matches "' . trim((string) $_GET[$param]) . '".');
    }
}

/* ---------- Prints ---------- */

/** Invoice print in one of the Custom Preview layouts, or the delivery challan. */
/** Unpaid balance of the customer's other invoices. */
function si_previous_due(mysqli $db, array $inv)
{
    return (float) (sales_row($db, "SELECT COALESCE(SUM(balance_amount), 0) AS d FROM sales_invoices WHERE status='posted' AND invoice_id<>? AND (customer_id=? OR (customer_id IS NULL AND customer_name=? AND mobile=?))", 'iiss',
        [(int) $inv['invoice_id'], (int) $inv['customer_id'], $inv['customer_name'], $inv['mobile']])['d'] ?? 0);
}

/** The A4 invoice: company head, invoice box, one line per product with its serials under it, amount in words and totals. */
function si_print_standard(mysqli $db, array $d, array $opt, $title)
{
    $inv = $d['inv'];
    $c = inv_company($db);
    $hasDiscount = (bool) array_filter($d['items'], fn($it) => (float) $it['discount_amount'] > 0);
    $prevDue = si_previous_due($db, $inv);
    $preparedBy = $inv['created_by'] ? (string) (sales_row($db, 'SELECT user_name FROM admin_user WHERE user_id=?', 'i', [(int) $inv['created_by']])['user_name'] ?? '') : '';
    $paidOn = $d['payments'] ? substr((string) end($d['payments'])['paid_at'], 0, 10) : (string) ($d['schedules'][0]['due_date'] ?? '');
    $logo = $c['logo'] !== '' && is_file(dirname(__DIR__, 3) . '/' . $c['logo']) ? '../../../' . $c['logo'] : '';
    $box = ['Invoice No.' => $inv['invoice_no'], 'Date' => inv_fmt_date($inv['sales_date']), 'Ref No' => $inv['ref_no'], 'Prepared By' => $preparedBy,
        'Entry Time' => date('d/m/Y g:i:sA', strtotime((string) $inv['created_at'])), 'Bill Status' => $inv['bill_type'], 'Payment Date' => $paidOn !== '' ? inv_fmt_date($paidOn) : '', 'Sales Person' => $inv['sales_person']];
    $party = ['Address' => $inv['address'], 'Mobile' => $inv['mobile'], 'Attention' => $inv['attention'], 'Delivery Point' => $inv['delivery_point'], 'BIN' => $inv['bin'], 'NID' => $inv['nid']];
    foreach (json_decode((string) ($inv['additional_info'] ?? ''), true) ?: [] as $k => $v) {
        $party[SDOC_ADDITIONAL[$k] ?? $k] = $v;
    }
    $vatLabel = (float) $inv['ait_amount'] != 0 ? 'VAT &amp; AIT' : 'VAT';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($inv['invoice_no']) . '</title><style>' . INV_PRINT_CSS
        . 'body{font-size:13px}.copy{display:flex;flex-direction:column;min-height:262mm;page-break-after:always}@page{size:A4;margin:8mm}@media print{body{margin:0}}.inv-foot{flex:1}.copy:last-child{page-break-after:auto}.tag{float:right;border:1px solid #333;padding:2px 8px;font-size:11px}.pad{height:110px}'
        . '.co-head{display:grid;grid-template-columns:120px minmax(0,1fr) 120px;align-items:start}.co-head img{max-width:110px;max-height:80px;object-fit:contain}.co-info{width:max-content;max-width:100%;margin:0 auto;font-size:14px;line-height:1.45}.co-info b{display:block;font-size:24px}'
        . '.inv-top{display:grid;grid-template-columns:minmax(0,1fr) 285px;margin-top:6px}.inv-left{display:flex;flex-direction:column;justify-content:flex-end;min-width:0}.inv-title{margin:12px 0 10px;text-align:center;font-size:23px;text-decoration:underline}'
        . '.party{display:grid;grid-template-columns:max-content minmax(0,1fr);column-gap:10px;padding:6px;border:1px solid #bbb;border-right:0;line-height:1.75}.party div{display:contents}.party b.name{font-size:16px}'
        . 'table.inv-box,table.inv-items,table.inv-sum{margin:0}.inv-box th,.inv-box td{padding:3px 6px;background:none;line-height:1.35}.inv-box th{width:96px;white-space:nowrap}.inv-box td{overflow-wrap:anywhere;white-space:nowrap}.inv-box tr:first-child td{font-weight:bold}'
        . '.inv-items{table-layout:auto;border-top:0}.inv-items th{padding:5px 6px;background:none;border-top:0;font-size:14px}.inv-items td{padding:4px 6px}.inv-items tbody{page-break-inside:avoid}.inv-items .product{font-size:14px;font-weight:bold}.inv-items .sn{font-size:13px;overflow-wrap:anywhere}.inv-items .center{text-align:center}.inv-items .qty{font-weight:bold}'
        . '.inv-foot{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:start;padding:10px 6px;border:1px solid #bbb;border-top:3px solid #bbb;page-break-inside:avoid}.copy>.sign{margin-top:44px}.inv-note p{margin:0 0 9px;font-weight:bold}.inv-note .plain{font-weight:normal}'
        . '.inv-sum td{padding:2px 4px;border:0;font-size:14px}.inv-sum td:first-child{font-weight:bold}.inv-sum td.plain{font-weight:normal}.inv-sum .net td{padding-top:6px;border-top:3px solid #bbb;font-size:15px;font-weight:bold}.inv-sum .net td:last-child{font-weight:bold}'
        . '</style></head><body' . (!empty($_GET['auto']) ? ' onload="window.print()"' : '') . '><div class="bar"><button onclick="window.print()">Print</button></div>';
    foreach ($opt['copies'] as $copy) {
        echo '<div class="copy">' . ($copy !== '' ? '<span class="tag">' . sales_h($copy) . '</span>' : '');
        if ($opt['header']) {
            echo '<div class="co-head"><div>' . ($logo !== '' ? '<img src="' . sales_h($logo) . '" alt="">' : '') . '</div><div class="co-info"><b>' . sales_h($c['name']) . '</b>' . nl2br(sales_h($c['address']));
            foreach (['Mobile' => 'mobile', 'Email' => 'email', 'Phone' => 'phone', 'Web' => 'website'] as $label => $key) {
                echo '<br>' . $label . ' : ' . sales_h($c[$key]);
            }
            echo '</div><div></div></div>';
        } else {
            echo '<div class="pad"></div>';
        }
        echo '<div class="inv-top"><div class="inv-left"><div class="inv-title"><b>' . sales_h($title) . '</b></div>';
        if ($inv['status'] === 'void') {
            echo '<p style="text-align:center;color:#a33;font-weight:bold;margin:0 0 8px">VOID</p>';
        }
        echo '<div class="party"><div><b>Customer :</b><b class="name">' . sales_h($inv['customer_name'] . ($inv['customer_group'] ? ' (' . $inv['customer_group'] . ')' : '')) . '</b></div>';
        foreach ($party as $k => $v) {
            if ((string) $v !== '' || in_array($k, ['Address', 'Mobile', 'Attention'], true)) {
                echo '<div><b>' . sales_h($k) . ' :</b><span>' . sales_h($v) . '</span></div>';
            }
        }
        echo '</div></div><table class="inv-box">';
        foreach ($box as $k => $v) {
            echo '<tr><th>' . sales_h($k) . '</th><td>' . sales_h($v) . '</td></tr>';
        }
        echo '</table></div><table class="inv-items"><thead><tr><th class="center">SL</th><th>Product Description</th><th class="center">Warranty</th><th class="center">Qty</th><th class="num">Unit Price</th>'
            . ($hasDiscount ? '<th class="num">Discount</th>' : '') . '<th class="num">Amount</th></tr></thead>';
        foreach ($d['items'] as $i => $it) {
            $serials = inv_parse_serials($it['serial_imei']);
            $notes = ($serials ? '<div>S/N: ' . sales_h(implode(', ', $serials)) . '</div>' : '') . ($it['product_narration'] !== '' ? '<div class="muted">' . sales_h($it['product_narration']) . '</div>' : '');
            $span = $notes !== '' ? ' rowspan="2"' : '';
            echo '<tbody><tr><td class="center"' . $span . '>' . ($i + 1) . '</td><td class="product">' . sales_h($it['description'] ?: $it['model_caption']) . '</td><td class="center"' . $span . '>' . sales_h($it['warranty']) . '</td>'
                . '<td class="center qty"' . $span . '>' . sales_qty_fmt($it['quantity']) . ((float) $it['free_quantity'] > 0 ? ' +' . sales_qty_fmt($it['free_quantity']) . ' free' : '') . '</td>'
                . '<td class="num"' . $span . '>' . sales_money($it['unit_price']) . '</td>' . ($hasDiscount ? '<td class="num"' . $span . '>' . sales_money($it['discount_amount']) . '</td>' : '') . '<td class="num"' . $span . '>' . sales_money($it['line_total']) . '</td></tr>'
                . ($notes !== '' ? '<tr><td class="sn">' . $notes . '</td></tr>' : '') . '</tbody>';
        }
        echo '</table><div class="inv-foot"><div class="inv-note"><p>Total Qty :&nbsp; ' . sales_qty_fmt($inv['total_quantity']) . '</p><p>In Word : ' . sales_h(acc_words((float) $inv['net_amount'])) . '</p>';
        if ($d['exchanges']) {
            echo '<p>Product Exchange : <span class="plain">' . sales_h(implode('; ', array_map(fn($x) => $x['description'] . ($x['serial_no'] ? ' (' . $x['serial_no'] . ')' : '') . ' Tk ' . sales_money($x['amount']), $d['exchanges']))) . '</span></p>';
        }
        echo '<p>Narration : <span class="plain">' . nl2br(sales_h((string) $inv['narration'])) . '</span></p></div><table class="inv-sum">';
        $rows = [['Total Amount', $inv['subtotal']], ['Less Discount', $inv['discount_amount']], [$vatLabel, (float) $inv['vat_amount'] + (float) $inv['ait_amount']], ['Extra Charges', $inv['extra_charges']]];
        foreach ([['Product Exchange', $inv['exchange_amount']], ['Point Adjust', $inv['point_adjust'] ?? 0]] as $row) {
            if ((float) $row[1] != 0) {
                $rows[] = $row;
            }
        }
        foreach ($rows as [$label, $v]) {
            echo '<tr><td>' . $label . '</td><td class="num">' . sales_money($v) . '</td></tr>';
        }
        // A sales return lowers the balance without any money coming in, so it gets its own row and the due rows still add up.
        $returned = round((float) $inv['net_amount'] - (float) $inv['cash_received'] - (float) $inv['balance_amount'], 2);
        echo '<tr class="net"><td>Net Payable Amount</td><td class="num">' . sales_money($inv['net_amount']) . '</td></tr><tr><td class="plain">Previous Due Amount</td><td class="num">' . sales_money($prevDue) . '</td></tr>'
            . '<tr class="net"><td>Total Due Amount</td><td class="num">' . sales_money($prevDue + (float) $inv['net_amount']) . '</td></tr><tr><td class="plain">Received Amount</td><td class="num">' . sales_money($inv['cash_received']) . '</td></tr>'
            . ($returned > 0 ? '<tr><td class="plain">Sales Return Adjusted</td><td class="num">' . sales_money($returned) . '</td></tr>' : '')
            . '<tr class="net"><td>Current Due</td><td class="num">' . sales_money($prevDue + (float) $inv['balance_amount']) . '</td></tr>';
        if ((float) ($inv['points_earned'] ?? 0) > 0) {
            echo '<tr><td>Points Earned</td><td class="num">' . sales_h((float) $inv['points_earned']) . '</td></tr>';
        }
        echo '</table></div><div class="sign"><div>Customer</div><div>Prepared By</div><div>Checked By</div><div>Authorised By</div></div></div>';
    }
    echo '</body></html>';
}

function si_print(mysqli $db, array $d, array $opt, $title)
{
    $opt += ['price' => true, 'warranty' => false, 'size' => 'a4', 'copies' => [''], 'due' => false, 'schedule' => false, 'header' => true, 'vat' => false, 'gatepass' => false];
    if ($opt['size'] === 'a4' && $opt['price'] && !$opt['schedule'] && !$opt['vat'] && !$opt['gatepass']) {
        si_print_standard($db, $d, $opt, $title);
        return;
    }
    $inv = $d['inv'];
    $narrow = in_array($opt['size'], ['80', '58'], true);
    $width = ['a4' => '', 'a5' => 'max-width:148mm;', '80' => 'max-width:76mm;font-size:11px;', '58' => 'max-width:54mm;font-size:10px;'][$opt['size']];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($inv['invoice_no']) . '</title><style>' . INV_PRINT_CSS
        . 'body{' . $width . '}.copy{page-break-after:always}.copy:last-child{page-break-after:auto}.tag{float:right;border:1px solid #333;padding:2px 8px;font-size:11px}.pad{height:110px}'
        . ($narrow ? '.meta{grid-template-columns:1fr}.ph{display:block;text-align:center}.sign{display:none}th,td{padding:2px 3px}' : '') . '</style></head><body' . (!empty($_GET['auto']) ? ' onload="window.print()"' : '') . '>';
    echo '<div class="bar"><button onclick="window.print()">Print</button></div>';
    $prevDue = $opt['due'] ? si_previous_due($db, $inv) : 0.0;
    foreach ($opt['copies'] as $copy) {
        echo '<div class="copy">' . ($copy !== '' ? '<span class="tag">' . sales_h($copy) . '</span>' : '');
        echo $opt['header'] ? inv_print_head($db, $title) : '<div class="pad"></div><h2 class="pt">' . sales_h($title) . '</h2>';
        if ($inv['status'] === 'void') {
            echo '<p style="text-align:center;color:#a33;font-weight:bold">VOID</p>';
        }
        $meta = ['Invoice No' => $inv['invoice_no'], 'Date' => inv_fmt_date($inv['sales_date']), 'Bill Type' => $inv['bill_type'], 'Ref No' => $inv['ref_no'],
            'Customer' => $inv['customer_name'] . ($inv['customer_group'] ? ' (' . $inv['customer_group'] . ')' : ''), 'Mobile' => $inv['mobile'], 'Address' => $inv['address'],
            'Attention' => $inv['attention'], 'Sales Person' => $inv['sales_person'], 'Delivery Point' => $inv['delivery_point'], 'BIN' => $inv['bin'], 'NID' => $inv['nid']];
        foreach (json_decode((string) ($inv['additional_info'] ?? ''), true) ?: [] as $k => $v) {
            $meta[SDOC_ADDITIONAL[$k] ?? $k] = $v;
        }
        echo '<div class="meta">';
        foreach ($meta as $k => $v) {
            if ((string) $v !== '') {
                echo '<div><b>' . sales_h($k) . ':</b> ' . sales_h($v) . '</div>';
            }
        }
        echo '</div><table><thead><tr><th>SL</th><th>' . ($narrow ? 'Item' : 'Model No/Caption') . '</th>' . ($narrow ? '' : '<th>Description</th>') . '<th class="num">Qty</th>'
            . ($narrow ? '' : '<th>Unit</th>') . ($opt['price'] ? '<th class="num">Rate</th>' . ($narrow ? '' : '<th class="num">Discount</th>') . '<th class="num">Amount</th>' : '') . '</tr></thead><tbody>';
        foreach ($d['items'] as $i => $it) {
            $serials = inv_parse_serials($it['serial_imei']);
            $detail = ($it['product_narration'] !== '' ? '<br><span class="muted">' . sales_h($it['product_narration']) . '</span>' : '')
                . (($opt['warranty'] || $opt['gatepass']) && $it['warranty'] !== '' ? '<br><span class="muted">Warranty: ' . sales_h($it['warranty']) . '</span>' : '')
                . ($serials ? '<div class="serials">S/N: ' . sales_h(implode(', ', $serials)) . '</div>' : '');
            $name = $narrow ? sales_h($it['model_caption'] ?: $it['description']) . $detail : sales_h(trim($it['product_code'] . ' ' . $it['model_caption']));
            echo '<tr><td>' . ($i + 1) . '</td><td>' . $name . '</td>' . ($narrow ? '' : '<td>' . sales_h($it['description']) . $detail . '</td>')
                . '<td class="num">' . sales_qty_fmt($it['quantity']) . ((float) $it['free_quantity'] > 0 ? ' +' . sales_qty_fmt($it['free_quantity']) . ' free' : '') . '</td>' . ($narrow ? '' : '<td>' . sales_h($it['unit_name']) . '</td>')
                . ($opt['price'] ? '<td class="num">' . sales_money($it['unit_price']) . '</td>' . ($narrow ? '' : '<td class="num">' . sales_money($it['discount_amount']) . '</td>') . '<td class="num">' . sales_money($it['line_total']) . '</td>' : '') . '</tr>';
        }
        echo '</tbody></table>';
        if ($d['exchanges'] && $opt['price']) {
            echo '<p><b>Product Exchange:</b> ' . sales_h(implode('; ', array_map(fn($x) => $x['description'] . ($x['serial_no'] ? ' (' . $x['serial_no'] . ')' : '') . ' Tk ' . sales_money($x['amount']), $d['exchanges']))) . '</p>';
        }
        echo '<table class="totals">';
        if ($opt['price']) {
            $rows = ['Total Amount' => $inv['subtotal'], 'Add Extra Charges' => $inv['extra_charges'], 'Less Discount' => $inv['discount_amount'], 'Add VAT & AIT' => (float) $inv['vat_amount'] + (float) $inv['ait_amount'],
                'Product Exchange' => $inv['exchange_amount'], 'Point Adjust' => $inv['point_adjust'] ?? 0];
            foreach ($rows as $label => $v) {
                if ($label === 'Total Amount' || (float) $v != 0) {
                    echo '<tr><td>' . $label . '</td><td class="num">' . sales_money($v) . '</td></tr>';
                }
            }
            echo '<tr><td><b>Net Payable Amount</b></td><td class="num"><b>' . sales_money($inv['net_amount']) . '</b></td></tr><tr><td>Paid</td><td class="num">' . sales_money($inv['cash_received']) . '</td></tr><tr><td>Due</td><td class="num">' . sales_money($inv['balance_amount']) . '</td></tr>';
            if ($opt['vat']) {
                $base = (float) $inv['net_amount'] - (float) $inv['vat_amount'] - (float) $inv['ait_amount'];
                echo '<tr><td>Value before VAT</td><td class="num">' . sales_money($base) . '</td></tr><tr><td>VAT &amp; AIT</td><td class="num">' . sales_money((float) $inv['vat_amount'] + (float) $inv['ait_amount']) . '</td></tr>';
            }
            if ($opt['due']) {
                echo '<tr><td>Previous Due</td><td class="num">' . sales_money($prevDue) . '</td></tr><tr><td><b>Total Due</b></td><td class="num"><b>' . sales_money($prevDue + (float) $inv['balance_amount']) . '</b></td></tr>';
            }
            if ((float) ($inv['points_earned'] ?? 0) > 0) {
                echo '<tr><td>Points Earned</td><td class="num">' . sales_h((float) $inv['points_earned']) . '</td></tr>';
            }
        }
        echo '<tr><td>Total Quantity</td><td class="num">' . sales_qty_fmt($inv['total_quantity']) . '</td></tr></table>';
        if ($opt['schedule']) {
            echo '<h3>Payment Schedule</h3><table><thead><tr><th>Due Date</th><th>Note</th><th class="num">Amount</th></tr></thead><tbody>';
            foreach ($d['schedules'] as $s) {
                echo '<tr><td>' . inv_fmt_date($s['due_date']) . '</td><td>' . sales_h($s['note']) . '</td><td class="num">' . sales_money($s['amount']) . '</td></tr>';
            }
            echo ($d['schedules'] ? '' : '<tr><td colspan="3" class="muted">No schedule.</td></tr>') . '</tbody></table><h3>Receipts</h3><table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>';
            foreach ($d['payments'] as $p) {
                echo '<tr><td>' . inv_fmt_date($p['paid_at']) . '</td><td>' . sales_h(SDOC_METHODS[$p['payment_method']] ?? $p['payment_method']) . '</td><td>' . sales_h($p['reference']) . '</td><td class="num">' . sales_money($p['amount']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        if ($inv['narration'] !== '' && $inv['narration'] !== null) {
            echo '<p><b>Narration:</b> ' . nl2br(sales_h($inv['narration'])) . '</p>';
        }
        echo $opt['gatepass'] ? '<div class="sign"><div>Store Keeper</div><div>Security</div><div>Authorised By</div><div>Received By</div></div>' : '<div class="sign"><div>Customer</div><div>Prepared By</div><div>Checked By</div><div>Authorised By</div></div>';
        echo '</div>';
    }
    echo '</body></html>';
}

if (isset($_GET['print']) || isset($_GET['challan']) || isset($_GET['receipt'])) {
    $d = si_load($conn, (int) ($_GET['print'] ?? $_GET['challan'] ?? $_GET['receipt']));
    if (!$d) {
        http_response_code(404);
        exit('Invoice not found.');
    }
    if (isset($_GET['receipt'])) {
        $only = (int) ($_GET['payment'] ?? 0);
        $rows = [];
        foreach ($d['payments'] as $p) {
            if (!$only || (int) $p['payment_id'] === $only) {
                $rows[] = [substr($p['paid_at'], 0, 10), 'MR-' . str_pad((string) $p['payment_id'], 6, '0', STR_PAD_LEFT), $p['payment_method'], $p['reference'], $p['amount']];
            }
        }
        sdoc_receipt_print($conn, 'Money Receipt ' . $d['inv']['invoice_no'], $d['inv'] + ['doc_no' => $d['inv']['invoice_no']], $rows, 'Against Invoice');
        exit;
    }
    if (isset($_GET['challan'])) {
        si_print($conn, $d, ['price' => false, 'warranty' => true], 'Delivery Challan');
        exit;
    }
    $tpl = SI_TEMPLATES[(int) ($_GET['tpl'] ?? 1)] ?? SI_TEMPLATES[1];
    si_print($conn, $d, $tpl[2], $tpl[2]['gatepass'] ?? false ? 'Gate Pass' : 'Sales Invoice');
    exit;
}

/* ---------- Saving ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $back = $self . ($invoiceId ? '?id=' . $invoiceId : '');

    if ($action === 'save_customer') {
        // Customer button: add the customer typed in the header, or update the picked one. Answers JSON.
        if (!bus_admin_csrf_valid()) {
            sales_json(['error' => 'Your form expired. Reload the page.'], 403);
        }
        $h = si_header_post();
        if ($h['customer_name'] === '') {
            sales_json(['error' => 'Enter the customer name first.'], 422);
        }
        $cid = (int) ($_POST['customer_id'] ?? 0);
        $vals = [$h['customer_group'], $h['customer_name'], $h['mobile'], $h['attention'], $h['address'], $h['bin'], $h['nid']];
        if ($cid > 0 && sales_row($conn, 'SELECT 1 FROM sales_customers WHERE customer_id=?', 'i', [$cid])) {
            sales_exec($conn, 'UPDATE sales_customers SET customer_group=?, customer_name=?, mobile=?, attention=?, address=?, bin=?, nid=? WHERE customer_id=?', 'sssssssi', array_merge($vals, [$cid]))->close();
        } else {
            $cid = sales_insert($conn, 'INSERT INTO sales_customers (customer_group, customer_name, mobile, attention, address, bin, nid) VALUES (?, ?, ?, ?, ?, ?, ?)', 'sssssss', $vals);
        }
        sales_json(['customer_id' => $cid, 'message' => 'Customer saved.']);
    }

    sales_require_csrf($back);
    try {
        $conn->begin_transaction();
        $old = null;
        if ($invoiceId > 0) {
            $old = sales_row($conn, 'SELECT * FROM sales_invoices WHERE invoice_id=? FOR UPDATE', 'i', [$invoiceId]);
            if (!$old) {
                throw new RuntimeException('Invoice not found.');
            }
            inv_assert_unlocked($conn, $old['sales_date'], (int) ($old['branch_id'] ?? 0));
        }
        $done = '';
        switch ($action) {
            case 'edit_details':
                if (!$old || $old['status'] !== 'posted') {
                    throw new RuntimeException('Open a posted invoice to edit its details.');
                }
                $h = si_header_post();
                if ($h['customer_name'] === '') {
                    throw new RuntimeException('Enter the customer name.');
                }
                $h['customer_id'] = ((int) ($_POST['customer_id'] ?? 0)) ?: null;
                sales_exec($conn, 'UPDATE sales_invoices SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($h))) . ' WHERE invoice_id=?', si_bind($h) . 'i', array_merge(array_values($h), [$invoiceId]))->close();
                sdoc_link_sr($conn, 'sales_invoices', 'invoice_id', $invoiceId);
                $done = 'Details of ' . $old['invoice_no'] . ' saved.';
                break;

            case 'edit_price':
                if (!$old) {
                    throw new RuntimeException('Open an invoice first.');
                }
                if ($lock = si_line_lock($conn, $old)) {
                    throw new RuntimeException($lock);
                }
                $prices = [];
                foreach (json_decode((string) ($_POST['items_json'] ?? '[]'), true) ?: [] as $l) {
                    if (!empty($l['item_id'])) {
                        $prices[(int) $l['item_id']] = [sales_amount($l['unit_price'] ?? 0), sales_amount($l['discount_amount'] ?? 0)];
                    }
                }
                foreach (sales_rows($conn, 'SELECT item_id, quantity FROM sales_invoice_items WHERE invoice_id=?', 'i', [$invoiceId]) as $it) {
                    if (isset($prices[(int) $it['item_id']])) {
                        [$p, $dsc] = $prices[(int) $it['item_id']];
                        $dsc = min($dsc, round((float) $it['quantity'] * $p, 2));
                        sales_exec($conn, 'UPDATE sales_invoice_items SET unit_price=?, discount_amount=?, line_total=? WHERE item_id=?', 'dddi', [$p, $dsc, max(0.0, round((float) $it['quantity'] * $p - $dsc, 2)), (int) $it['item_id']])->close();
                    }
                }
                sales_exec($conn, 'UPDATE sales_invoices v SET subtotal=(SELECT COALESCE(SUM(line_total), 0) FROM sales_invoice_items i WHERE i.invoice_id=v.invoice_id), '
                        . 'net_amount=GREATEST(0, subtotal + extra_charges + vat_amount + ait_amount - discount_amount - exchange_amount - point_adjust) WHERE invoice_id=?', 'i', [$invoiceId])->close();
                $paid = (float) sales_row($conn, 'SELECT COALESCE(SUM(amount), 0) AS p FROM sales_invoice_payments WHERE invoice_id=?', 'i', [$invoiceId])['p'];
                $net = (float) sales_row($conn, 'SELECT net_amount FROM sales_invoices WHERE invoice_id=?', 'i', [$invoiceId])['net_amount'];
                if ($paid > $net + 0.004) {
                    throw new RuntimeException('The new net amount ' . sales_money($net) . ' is below the ' . sales_money($paid) . ' already received.');
                }
                si_refresh_balance($conn, $invoiceId);
                sdoc_credit_check($conn, $invoiceId);
                $done = 'Prices of ' . $old['invoice_no'] . ' updated.';
                break;

            case 'receipt':
                if (!$old || $old['status'] !== 'posted') {
                    throw new RuntimeException('Open a posted invoice to take a payment.');
                }
                $amount = sales_amount($_POST['cash_received'] ?? 0);
                $method = array_key_exists($_POST['payment_method'] ?? '', SDOC_METHODS) ? $_POST['payment_method'] : 'cash';
                if ($amount <= 0) {
                    throw new RuntimeException('Enter the Cash Amount received.');
                }
                if ($amount > (float) $old['balance_amount'] + 0.004) {
                    throw new RuntimeException('Only ' . sales_money($old['balance_amount']) . ' is due on ' . $old['invoice_no'] . '.');
                }
                $pid = sales_insert($conn, 'INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, ?, ?, ?, ?)', 'isdsi', [$invoiceId, $method, $amount, sales_post_string('payment_reference', 120), $userId]);
                si_refresh_balance($conn, $invoiceId);
                $done = 'Received ' . sales_money($amount) . '. <a target="_blank" href="' . $self . '?receipt=' . $invoiceId . '&payment=' . $pid . '">Print receipt</a>';
                break;

            case 'save_schedule':
                if (!$old || $old['status'] !== 'posted') {
                    throw new RuntimeException('Open a posted invoice first.');
                }
                sales_exec($conn, 'DELETE FROM sales_invoice_schedules WHERE invoice_id=?', 'i', [$invoiceId])->close();
                foreach (si_schedules_post($old['sales_date']) as [$date, $amount, $note]) {
                    sales_insert($conn, 'INSERT INTO sales_invoice_schedules (invoice_id, due_date, amount, note) VALUES (?, ?, ?, ?)', 'isds', [$invoiceId, $date, $amount, $note]);
                }
                $done = 'Schedule saved.';
                break;

            case 'void':
                if (!$old) {
                    throw new RuntimeException('Open an invoice first.');
                }
                if ($lock = si_line_lock($conn, $old)) {
                    throw new RuntimeException($lock);
                }
                sales_stock_remove($conn, (int) ($old['stock_doc_id'] ?? 0));
                sales_exec($conn, 'DELETE FROM sales_member_points WHERE invoice_id=?', 'i', [$invoiceId])->close();
                sales_exec($conn, "UPDATE sales_invoices SET status='void', stock_doc_id=NULL, points_earned=0, points_redeemed=0 WHERE invoice_id=?", 'i', [$invoiceId])->close();
                $done = $old['invoice_no'] . ' is void. Its stock was returned to the store; refund any money received separately.';
                break;

            case 'save':
                $date = sales_valid_date($_POST['sales_date'] ?? '');
                if ($date === null) {
                    throw new RuntimeException('Enter a valid sales date.');
                }
                if ($old && ($lock = si_line_lock($conn, $old))) {
                    throw new RuntimeException($lock);
                }
                $branch = $old ? (int) ($old['branch_id'] ?: inv_current_branch($conn)) : inv_current_branch($conn);
                inv_assert_unlocked($conn, $date, $branch);
                $h = si_header_post();
                if ($h['customer_name'] === '') {
                    throw new RuntimeException('Enter or select a customer.');
                }
                [$lines, $subtotal, $qtyTotal] = sdoc_clean_lines($_POST['items_json'] ?? '', inv_stores($conn), true);
                if (sales_stock_on($conn)) {
                    foreach ($lines as $l) {
                        if (!$l['product_id']) {
                            throw new RuntimeException('"' . $l['description'] . '" is not a saved product. Pick it from the product list so stock can be tracked.');
                        }
                    }
                }
                $exchanges = [];
                foreach (json_decode((string) ($_POST['exchanges_json'] ?? '[]'), true) ?: [] as $x) {
                    $desc = sales_str($x['description'] ?? '', 255);
                    if ($desc !== '' && sales_amount($x['amount'] ?? 0) > 0) {
                        $exchanges[] = [$desc, sales_str($x['serial_no'] ?? '', 120), max(0.001, sales_qty($x['quantity'] ?? 1)), sales_amount($x['amount'])];
                    }
                }
                $exchange = round(array_sum(array_column($exchanges, 3)), 2);
                $extra = sales_amount($_POST['extra_charges'] ?? 0);
                $discount = sales_amount($_POST['discount_amount'] ?? 0);
                $vat = sales_amount($_POST['vat_amount'] ?? 0);
                $before = round($subtotal + $extra + $vat - $discount - $exchange, 2);
                if ($before < 0) {
                    throw new RuntimeException('Discount and product exchange are more than the invoice value.');
                }
                // Member points: redeem (Point Adjust) lowers the net; points are earned on what is left.
                $member = sdoc_member($conn, sales_post_string('member_no', 40));
                if (sales_post_string('member_no', 40) !== '' && !$member) {
                    throw new RuntimeException('No active member with number "' . sales_post_string('member_no', 40) . '".');
                }
                $ps = sdoc_point_settings($conn);
                $redeem = $member ? round(sales_amount($_POST['point_adjust'] ?? 0), 2) : 0.0;
                if ($redeem > 0 && $redeem > sdoc_member_balance($conn, (int) $member['member_id'], $invoiceId) + 0.004) {
                    throw new RuntimeException('Member ' . $member['member_no'] . ' has only ' . sdoc_member_balance($conn, (int) $member['member_id'], $invoiceId) . ' points.');
                }
                $pointValue = round($redeem * $ps['value'], 2);
                if ($pointValue > $before + 0.004) {
                    throw new RuntimeException('Point Adjust (' . sales_money($pointValue) . ') is more than the payable amount.');
                }
                $net = max(0.0, round($before - $pointValue, 2));
                $earned = $member ? floor($net / 100 * $ps['per100'] * 100) / 100 : 0.0;
                $cash = min(sales_amount($_POST['cash_received'] ?? 0), $net);
                $schedules = si_schedules_post($date);
                $billType = ($_POST['bill_type'] ?? '') === 'Cash' ? 'Cash' : 'Credit';

                $cols = $h + ['sales_date' => $date, 'bill_type' => $billType, 'customer_id' => ((int) ($_POST['customer_id'] ?? 0)) ?: null, 'member_id' => $member ? (int) $member['member_id'] : null,
                    'subtotal' => $subtotal, 'extra_charges' => $extra, 'discount_amount' => $discount, 'vat_amount' => $vat, 'ait_amount' => 0.0, 'exchange_amount' => $exchange,
                    'point_adjust' => $pointValue, 'points_redeemed' => $redeem, 'points_earned' => $earned, 'net_amount' => $net, 'total_quantity' => $qtyTotal];
                if ($old) {
                    sales_stock_remove($conn, (int) ($old['stock_doc_id'] ?? 0));
                    $cols['stock_doc_id'] = null;
                    sales_exec($conn, 'UPDATE sales_invoices SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE invoice_id=?', si_bind($cols) . 'i', array_merge(array_values($cols), [$invoiceId]))->close();
                    foreach (['sales_invoice_items', 'sales_invoice_schedules', 'sales_invoice_exchanges'] as $t) {
                        sales_exec($conn, "DELETE FROM $t WHERE invoice_id=?", 'i', [$invoiceId])->close();
                    }
                    sales_exec($conn, 'DELETE FROM sales_invoice_payments WHERE invoice_id=? AND reference=?', 'is', [$invoiceId, SI_CASH_REF])->close();
                    $invoiceNo = $old['invoice_no'];
                } else {
                    $invoiceNo = sales_next_number($conn, 'SI', (int) substr($date, 0, 4), 'sales_invoice_sequences');
                    $cols += ['invoice_no' => $invoiceNo, 'branch_id' => $branch, 'created_by' => $userId];
                    $invoiceId = sales_insert($conn, 'INSERT INTO sales_invoices (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', si_bind($cols), array_values($cols));
                }
                $costs = sales_avg_costs($conn, array_column($lines, 'product_id'));
                foreach ($lines as $n => $l) {
                    $row = ['invoice_id' => $invoiceId, 'line_number' => $n + 1] + array_diff_key($l, ['serials' => 1])
                        + ['serial_imei' => implode("\n", $l['serials']), 'sequence_type' => $l['serials'] ? 'available' : 'not_available', 'unit_cost' => round($costs[(int) $l['product_id']] ?? 0, 2)];
                    sales_insert($conn, 'INSERT INTO sales_invoice_items (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', si_bind($row), array_values($row));
                }
                foreach ($schedules as [$sd, $amount, $note]) {
                    sales_insert($conn, 'INSERT INTO sales_invoice_schedules (invoice_id, due_date, amount, note) VALUES (?, ?, ?, ?)', 'isds', [$invoiceId, $sd, $amount, $note]);
                }
                foreach ($exchanges as [$desc, $serial, $q, $amount]) {
                    sales_insert($conn, 'INSERT INTO sales_invoice_exchanges (invoice_id, description, serial_no, quantity, amount) VALUES (?, ?, ?, ?, ?)', 'issdd', [$invoiceId, $desc, $serial, $q, $amount]);
                }
                if ($cash > 0) {
                    sales_insert($conn, "INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, 'cash', ?, ?, ?)", 'idsi', [$invoiceId, $cash, SI_CASH_REF, $userId]);
                }
                $paid = (float) sales_row($conn, 'SELECT COALESCE(SUM(amount), 0) AS p FROM sales_invoice_payments WHERE invoice_id=?', 'i', [$invoiceId])['p'];
                if ($paid > $net + 0.004) {
                    throw new RuntimeException('Payments received (' . sales_money($paid) . ') are more than the new net amount ' . sales_money($net) . '.');
                }
                si_refresh_balance($conn, $invoiceId);
                sdoc_credit_check($conn, $invoiceId);
                sdoc_link_sr($conn, 'sales_invoices', 'invoice_id', $invoiceId);
                if (!(int) ($_POST['invoice_id'] ?? 0)) {
                    notify_approval_needed('sales', 'Sales Invoice', $invoiceNo, $cols['customer_name'] ?? '');
                }
                sdoc_points_post($conn, $invoiceId, $member ? (int) $member['member_id'] : 0, $date, $earned, $redeem);
                sales_stock_post_invoice($conn, $invoiceId);
                $done = $invoiceNo . ' saved.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
        $conn->commit();
        $_SESSION['sales_flash_html'] = $action === 'receipt';
        sales_flash_redirect($self . '?id=' . $invoiceId, 'success', $done);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Sales invoice ' . $action . ' failed: ' . $e->getMessage());
        if ($action === 'save') {
            $_SESSION['si_repost'] = $_POST;
        }
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Page ---------- */

$flash = sales_take_flash();
$flashHtml = !empty($_SESSION['sales_flash_html']);
unset($_SESSION['sales_flash_html']);
$repost = ($flash['kind'] ?? '') === 'error' ? ($_SESSION['si_repost'] ?? null) : null;
unset($_SESSION['si_repost']);
$view = (string) ($_GET['view'] ?? 'form');

sdoc_page_start($conn, 'Sales Invoice/Bill', 'invoice');
if ($flash) {
    echo '<div class="alert ' . ($flash['kind'] === 'success' ? 'success' : 'error') . '">' . ($flashHtml ? strip_tags($flash['message'], '<a>') : sales_h($flash['message'])) . '</div>';
}

if ($view === 'list') {
    $from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01');
    $to = sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d');
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $rows = sales_list_rows($conn, 'SELECT * FROM sales_invoices WHERE sales_date BETWEEN ? AND ? AND (invoice_no LIKE ? OR ref_no LIKE ? OR customer_name LIKE ? OR mobile LIKE ?)' . perm_branch_sql('branch_id') . ' ORDER BY sales_date DESC, invoice_id DESC', 'ssssss', [$from, $to, $like, $like, $like, $like]);
    echo '<form method="get" class="list-search"><input type="hidden" name="view" value="list"><input class="input" type="date" name="from" value="' . sales_h($from) . '" style="max-width:160px"><input class="input" type="date" name="to" value="' . sales_h($to) . '" style="max-width:160px">'
        . '<input class="input" name="q" value="' . sales_h($q) . '" placeholder="Invoice, ref, customer or mobile"><button class="btn primary">Search</button><a class="btn" href="' . $self . '">New</a></form>';
    echo '<div class="table-scroll" style="max-height:640px"><table><thead><tr><th>SL</th><th>Invoice No</th><th>Date</th><th>Ref No</th><th>Customer</th><th>Mobile</th><th>Bill Type</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th><th>Select</th><th>Print</th></tr></thead><tbody>';
    echo $rows ? '' : '<tr><td colspan="13" class="empty">Nothing in this period.</td></tr>';
    foreach ($rows as $i => $r) {
        $st = $r['status'] === 'void' ? '<span class="badge cancelled">Void</span>' : (($r['approval_status'] ?? '') === 'approved' ? '<span class="badge converted">Approved</span>' : '<span class="badge open">Pending</span>');
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['invoice_no']) . '</td><td>' . inv_fmt_date($r['sales_date']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td>' . sales_h($r['customer_name']) . '</td><td>' . sales_h($r['mobile']) . '</td><td>' . sales_h($r['bill_type']) . '</td>'
            . '<td class="num">' . sales_money($r['net_amount']) . '</td><td class="num">' . sales_money($r['cash_received']) . '</td><td class="num">' . sales_money($r['balance_amount']) . '</td><td>' . $st . '</td>'
            . '<td><a class="btn small" href="' . $self . '?id=' . (int) $r['invoice_id'] . '">Select</a></td><td><a class="btn small" target="_blank" href="' . $self . '?print=' . (int) $r['invoice_id'] . '">Print</a></td></tr>';
    }
    echo '</tbody></table></div>';
    sdoc_page_end();
    exit;
}

$d = isset($_GET['id']) ? si_load($conn, (int) $_GET['id']) : null;
$inv = $d['inv'] ?? null;
$lock = $inv ? si_line_lock($conn, $inv) : '';
$h = $repost ?: ($inv ?: ['sales_date' => date('Y-m-d'), 'bill_type' => 'Credit', 'sales_person' => $GLOBALS['user_name'] ?? '']);
$val = fn($k, $def = '') => sales_h($h[$k] ?? $def);
$items = $repost ? (json_decode((string) ($repost['items_json'] ?? '[]'), true) ?: []) : array_map(fn($it) => $it + ['serials' => inv_parse_serials($it['serial_imei'])], $d['items'] ?? []);
// Make Sales Invoice from a Price Quotation: start a new invoice with its customer, lines and charges.
$fromQuotation = null;
if (!$inv && !$repost && isset($_GET['quotation'])) {
    $fromQuotation = sales_row($conn, "SELECT * FROM inv_docs WHERE doc_type='quotation' AND status='active' AND doc_no=?", 's', [trim((string) $_GET['quotation'])]);
    if ($fromQuotation) {
        $h = array_merge($h, ['ref_no' => $fromQuotation['doc_no'], 'customer_id' => $fromQuotation['party_id'], 'customer_name' => $fromQuotation['party_name'],
            'customer_group' => $fromQuotation['party_group'], 'mobile' => $fromQuotation['mobile'], 'attention' => $fromQuotation['attention'], 'address' => $fromQuotation['address'],
            'sales_person' => $fromQuotation['sales_person'] ?: ($h['sales_person'] ?? ''), 'narration' => $fromQuotation['narration'], 'extra_charges' => $fromQuotation['extra_charges'],
            'discount_amount' => $fromQuotation['discount_amount'], 'vat_amount' => $fromQuotation['vat_amount']]);
        $items = array_map(fn($l) => $l + ['product_narration' => $l['narration'], 'store_name' => $l['store_name'] ?: inv_setting($conn, 'default_store', ''), 'serials' => []],
            sales_rows($conn, 'SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [(int) $fromQuotation['doc_id']]));
    } else {
        $flash = ['kind' => 'error', 'message' => 'No active quotation "' . trim((string) $_GET['quotation']) . '".'];
        echo '<div class="alert error">' . sales_h($flash['message']) . '</div>';
    }
    $val = fn($k, $def = '') => sales_h($h[$k] ?? $def);
}
$member = $inv && !empty($inv['member_id']) ? sales_row($conn, 'SELECT * FROM sales_members WHERE member_id=?', 'i', [(int) $inv['member_id']]) : null;
$prevId = sales_row($conn, 'SELECT invoice_id FROM sales_invoices WHERE invoice_id<? ORDER BY invoice_id DESC LIMIT 1', 'i', [$inv ? (int) $inv['invoice_id'] : PHP_INT_MAX]);
$nextId = $inv ? sales_row($conn, 'SELECT invoice_id FROM sales_invoices WHERE invoice_id>? ORDER BY invoice_id LIMIT 1', 'i', [(int) $inv['invoice_id']]) : null;
$id = (int) ($inv['invoice_id'] ?? 0);
$nav = fn($r) => $r ? 'href="' . $self . '?id=' . (int) $r['invoice_id'] . '"' : 'aria-disabled="true" style="opacity:.4"';
$stores = inv_stores($conn);
$defaultStore = inv_setting($conn, 'default_store', '');
?>
<form method="post" id="docform" class="si" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="action" id="action" value="save"><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="customer_id" id="customer_id" value="<?= $val('customer_id') ?>">
<input type="hidden" name="items_json" id="items_json"><input type="hidden" name="schedules_json" id="schedules_json"><input type="hidden" name="exchanges_json" id="exchanges_json">
<header class="si-hero">
  <div class="si-hero-main">
    <span class="si-hero-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3"/></svg></span>
    <div>
      <h2>Sales Invoice</h2>
      <?php $siStatus = !$inv ? ['New', 'new'] : ($inv['status'] === 'void' ? ['Void', 'void'] : ((($inv['approval_status'] ?? '') === 'approved') ? ['Approved', 'ok'] : ['Pending approval', 'wait'])); ?>
      <p class="si-meta"><b><?= sales_h($inv['invoice_no'] ?? sales_peek_number($conn, 'SI', 'sales_invoice_sequences')) ?></b>
        <span class="si-status si-<?= $siStatus[1] ?>"><?= $siStatus[0] ?></span>
        <span><?= sales_h(inv_branch_name($conn, $inv['branch_id'] ?? inv_current_branch($conn))) ?></span>
        <?php if ($inv): ?><span><?= sales_h(date('d M Y', strtotime($inv['sales_date']))) ?></span><?= $inv['stock_doc_id'] ?? null ? '<span>Stock out posted</span>' : '' ?><?php endif; ?>
        <?= $lock ? '<span class="si-lock">' . sales_h($lock) . '</span>' : '' ?>
        <?= $fromQuotation ? '<span>From quotation ' . sales_h($fromQuotation['doc_no']) . '</span>' : '' ?></p>
    </div>
  </div>
  <div class="si-hero-tools">
    <div class="sd-group" role="group" aria-label="Record"><a class="btn" href="<?= $self ?>">New</a><a class="btn" href="<?= $self ?>?view=list">List View</a>
        <a class="btn round" title="Previous" <?= $nav($prevId) ?>>&#9664;</a><button type="button" class="btn round" id="hold" title="Hold this unsaved invoice, or bring back the held one">&#10074;&#10074;</button><a class="btn round" title="Next" <?= $nav($nextId) ?>>&#9654;</a></div>
  </div>
</header>

<div class="si-layout">
<div class="si-main">
<section class="sd-card" data-step="1">
  <header class="sd-head"><span class="sd-num"><span>1</span></span><div><h3>Customer</h3><p>Search by mobile number, or type the name and pick from the list. A saved customer fills the rest.</p></div></header>
  <div class="sgrid sd-grid">
    <div class="sd-f"><label for="mobile">Mobile No</label><div class="inline"><input id="mobile" name="mobile" maxlength="40" inputmode="tel" value="<?= $val('mobile') ?>" placeholder="Mobile Number"><button type="button" class="btn small" id="mobile-search">Search</button></div></div>
    <div class="sd-f s2"><label for="customer_name">Customer Name</label><div class="inline"><div class="relative" style="flex:1"><input id="customer_name" name="customer_name" maxlength="190" value="<?= $val('customer_name') ?>" placeholder="Customer Name"><div class="suggestions" id="customer-suggestions" hidden></div></div><button type="button" class="btn small" id="save-customer" title="Save this customer">Save customer</button></div></div>
    <div class="sd-f"><label for="customer_group">Customer Group</label><input id="customer_group" name="customer_group" maxlength="120" value="<?= $val('customer_group') ?>"></div>
    <div class="sd-f"><label for="attention">Attention</label><input id="attention" name="attention" maxlength="190" value="<?= $val('attention') ?>" placeholder="Attention"></div>
    <div class="sd-f"><label for="sales_person">Sales Person</label><input id="sales_person" name="sales_person" maxlength="140" value="<?= $val('sales_person') ?>" placeholder="Sales Person" list="dl-sr"><datalist id="dl-sr"><?php foreach (sdoc_sr_names($conn) as $n): ?><option value="<?= sales_h($n) ?>"><?php endforeach; ?></datalist></div>
    <div class="sd-f s2"><label for="address">Address</label><input id="address" name="address" maxlength="2000" value="<?= $val('address') ?>" placeholder="Address"></div>
    <div class="sd-f"><label for="delivery_point">Delivery Point</label><input id="delivery_point" name="delivery_point" maxlength="255" value="<?= $val('delivery_point') ?>"></div>
    <div class="sd-f"><label for="bin">BIN</label><input id="bin" name="bin" maxlength="60" value="<?= $val('bin') ?>"></div>
    <div class="sd-f"><label for="nid">NID</label><input id="nid" name="nid" maxlength="60" value="<?= $val('nid') ?>"></div>
  </div>
</section>
<section class="sd-card" data-step="2">
  <header class="sd-head"><span class="sd-num"><span>2</span></span><div><h3>Invoice details</h3><p>The number fills itself. Check the date and the bill type.</p></div></header>
  <div class="sgrid sd-grid si-details">
    <div class="sd-f"><label for="doc_no">Invoice No</label><div class="inline"><input id="doc_no" value="<?= sales_h($inv['invoice_no'] ?? sales_peek_number($conn, 'SI', 'sales_invoice_sequences')) ?>"><button type="button" class="btn small" data-find="search">Find</button></div></div>
    <div class="sd-f half"><label for="sales_date">Sales Date</label><input id="sales_date" type="date" name="sales_date" value="<?= $val('sales_date') ?>" required></div>
    <div class="sd-f half"><label for="bill_type">Bill Type</label><select id="bill_type" name="bill_type"><?php foreach (['Credit', 'Cash'] as $o): ?><option<?= ($h['bill_type'] ?? '') === $o ? ' selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="sd-f"><label for="ref_no">Ref No</label><div class="inline"><input id="ref_no" name="ref_no" maxlength="80" value="<?= $val('ref_no') ?>"><button type="button" class="btn small" data-find="ref">Find</button></div></div>
    <div class="sd-f"><label for="narration">Narration</label><input id="narration" name="narration" maxlength="2000" value="<?= $val('narration') ?>"></div>
  </div>
</section>

<section class="sd-card entry" data-step="3">
  <header class="sd-head"><span class="sd-num"><span>3</span></span><div><h3>Add products</h3><p>Search the model, pick the store, enter quantity and price, then press Add. Repeat for every product.</p></div></header>
  <div class="sd-grid sd-line">
    <div class="sd-f s3"><label for="e_model">Model No/Caption</label><div class="relative"><input id="e_model" placeholder="Search model, code or name"><div class="suggestions" id="product-suggestions" hidden></div></div></div>
    <div class="sd-f s2"><label for="e_store">Store</label><select id="e_store"><option value="">Pick store</option><?php foreach ($stores as $s): ?><option<?= $s === $defaultStore ? ' selected' : '' ?>><?= sales_h($s) ?></option><?php endforeach; ?></select></div>
    <div class="sd-f"><label for="e_qty">Quantity</label><input id="e_qty" type="number" min="0" step="0.001"></div>
    <div class="sd-f"><label for="e_price">Unit Price</label><input id="e_price" type="number" min="0" step="0.01"></div>
    <div class="sd-f"><label for="e_disc">Discount</label><input id="e_disc" type="number" min="0" step="0.01" value="0"></div>
    <div class="sd-f"><label for="e_free">Free Qty</label><input id="e_free" type="number" min="0" step="0.001" value="0"></div>
    <div class="sd-f s2"><label for="e_serial">Serial / IMEI No.</label><textarea id="e_serial" rows="1" title="One per line or separated by commas"></textarea></div>
    <div class="sd-f sd-add"><button type="button" class="btn primary" id="add-line">Add</button></div>
  </div>
  <div class="sd-chips" aria-live="polite">
    <span>In stock <b id="i_qty">0.00</b></span><span>Last price <b id="i_last">0.00</b></span><span>Last serial <b id="i_serial"></b></span>
    <span>Sales price <b id="i_sprice"></b></span><span>Dealer A <b id="i_dealer"></b></span><span class="sd-adv">Advance sales <b id="i_advance"></b></span><span id="i_cost"></span>
  </div>
  <details class="sd-more">
    <summary>Product details and serial options <small>(filled from the product; change only if needed)</small></summary>
    <div class="sd-grid sd-details">
      <div class="sd-f"><label for="e_group">Product Group</label><input id="e_group"></div>
      <div class="sd-f"><label for="e_brand">Brand</label><input id="e_brand"></div>
      <div class="sd-f s2"><label for="e_desc">Description</label><input id="e_desc"></div>
      <div class="sd-f s2"><label for="e_narr">Product Narration</label><input id="e_narr"></div>
      <div class="sd-f"><label for="e_warranty">Warranty</label><input id="e_warranty"></div>
      <div class="sd-f"><label for="e_unit">Unit Name</label><input id="e_unit" value="Pcs"></div>
    </div>
    <div class="radios sd-serial">
      <label><input type="radio" name="sq" value="none" checked> Sequence Not Available</label><label><input type="radio" name="sq" value="range"> Sequence Available</label><label><input type="radio" name="sq" value="list"> Bulk Qty</label>
      <label><input class="check" type="checkbox" id="serial_clear" checked> Serial Clear</label><label><input class="check" type="checkbox" id="serial_storage"> Serial Storage</label>
      <label>Serial Common <input id="s_common" class="sm"></label><label>From <input id="s_from" class="sm"></label><label>To <input id="s_to" class="sm"></label>
    </div>
  </details>
</section>

<section class="sd-card">
  <header class="sd-head sd-head-bar"><div><h3>Items <span class="si-count" id="si_count_head"></span></h3><?php if ($inv): ?><p><?= sales_h($inv['invoice_no']) ?> · <?= sales_h(inv_branch_name($conn, $inv['branch_id'] ?? 0)) ?> · <b><?= $inv['status'] === 'void' ? 'Void' : (($inv['approval_status'] ?? '') === 'approved' ? 'Approved' : 'Pending approval') ?></b><?= $inv['stock_doc_id'] ?? null ? ' · Stock out posted' : (sales_stock_on($conn) ? '' : ' · Sales stock is off') ?><?= $lock ? ' · ' . sales_h($lock) : '' ?></p><?php elseif ($fromQuotation): ?><p>Filled from quotation <?= sales_h($fromQuotation['doc_no']) ?>. Add serials and check stores and prices, then Save.</p><?php else: ?><p>New invoice. Products you add appear here.</p><?php endif; ?></div>
    <div class="sd-tools">
      <div class="sd-group" role="group" aria-label="Change"><button type="button" class="btn" data-mode="edit"<?= $inv ? '' : ' disabled' ?>>Edit</button><button type="button" class="btn" data-mode="price"<?= $inv ? '' : ' disabled' ?>>Edit Price</button><button type="button" class="btn" data-mode="details"<?= $inv ? '' : ' disabled' ?>>Edit Details</button></div>
      <div class="sd-group" role="group" aria-label="Print"><?php if ($inv): ?><a class="btn" target="_blank" href="<?= $self ?>?print=<?= $id ?>">Preview</a><a class="btn" target="_blank" href="<?= $self ?>?print=<?= $id ?>&auto=1">Print</a>
        <a class="btn" target="_blank" href="<?= $self ?>?challan=<?= $id ?>">Challan Preview</a><a class="btn" target="_blank" href="<?= $self ?>?challan=<?= $id ?>&auto=1">Challan Print</a><?php else: ?>
        <button type="button" class="btn" disabled>Preview</button><button type="button" class="btn" disabled>Print</button><button type="button" class="btn" disabled>Challan Preview</button><button type="button" class="btn" disabled>Challan Print</button><?php endif; ?>
        <button type="button" class="btn" data-toggle="custom-preview"<?= $inv ? '' : ' disabled' ?>>Custom Preview</button><button type="button" class="btn" id="direct-print"<?= $inv ? '' : ' disabled' ?>>Direct Print</button>
        <?php if ($inv): ?><a class="btn" target="_blank" href="<?= $self ?>?receipt=<?= $id ?>">Receipt</a><?php else: ?><button type="button" class="btn" disabled>Receipt</button><?php endif; ?></div>
      <div class="sd-group" role="group" aria-label="More"><button type="button" class="btn" data-focus="t_discount">VAT &amp; Discount</button><button type="button" class="btn" id="serial-view">Serial View</button><button type="button" class="btn" id="show-cost">Show Cost</button><button type="button" class="btn" id="show-price">Show Price</button>
        <button type="button" class="btn" data-toggle="additional-info">Additional Info</button><label class="btn">Import<input type="file" id="import-file" accept=".csv,text/csv" hidden></label><button type="button" class="btn" data-toggle="exchange">Exchange</button>
        <button type="button" class="btn" data-toggle="attached"<?= $inv ? '' : ' disabled' ?>>Attached</button><button type="button" class="btn" data-toggle="schedule">Schedule</button></div>
    </div>
  </header>
<details class="section" id="custom-preview" hidden open><summary>Custom Preview Hide</summary><div class="toolbar"><?php foreach (SI_TEMPLATES as $n => [$label, $title]): ?><a class="btn" target="_blank" title="<?= sales_h($title) ?>" href="<?= $self ?>?print=<?= $id ?>&tpl=<?= $n ?>"><?= $label ?></a><?php endforeach; ?></div></details>
<?= sdoc_additional_html($h['additional_info'] ?? '') ?>
<section class="section" id="exchange" hidden><div class="section-title">Product Exchange <span class="muted">(items the customer hands back; their value lowers the bill)</span></div>
<div class="radios"><label>Description <input id="x_desc" style="width:240px"></label><label>Serial <input id="x_serial" class="sm"></label><label>Qty <input id="x_qty" type="number" min="0" step="0.001" value="1" class="sm"></label><label>Amount <input id="x_amount" type="number" min="0" step="0.01" class="sm"></label><button type="button" class="btn" id="x_add">Add Exchange</button></div>
<table><thead><tr><th>Description</th><th>Serial</th><th class="num">Qty</th><th class="num">Amount</th><th></th></tr></thead><tbody id="x_body"></tbody></table></section>
<section class="section" id="schedule" hidden><div class="section-title">Payment Schedule</div><table><thead><tr><th>Due Date</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead><tbody id="s_body"></tbody></table>
<?php if ($inv): ?><button type="button" class="btn" id="save-schedule" style="margin-top:6px">Save Schedule</button><?php endif; ?></section>
  <div class="table-scroll grid-box"><table><thead><tr id="grid-head"></tr></thead><tbody id="grid-body"></tbody></table></div>
</section>
</div>

<aside class="si-side">
  <section class="si-sum" aria-label="Summary">
    <div class="si-sum-top">
      <span>Net payable</span>
      <b><small>&#2547;</small> <span id="si_net">0.00</span></b>
      <p><span id="si_count">0</span> item(s) &middot; <span id="si_qty">0</span> qty<span id="si_change_wrap" hidden> &middot; change <span id="si_change">0.00</span></span></p>
    </div>
    <table class="stotals" id="totals"></table>
  </section>
  <section class="sd-card si-pay" data-step="4">
    <header class="sd-head"><span class="sd-num"><span>4</span></span><div><h3>Payment</h3><p>Cash received now. For credit, add a payment day or a schedule.</p></div></header>
  <div class="sd-pay">
    <div class="sd-grid sd-paygrid si-paygrid">
      <div class="sd-f"><label for="t_cash">Cash Amount</label><input id="t_cash" name="cash_received" type="number" min="0" step="0.01" value="<?= $inv ? '' : $val('cash_received', '0') ?>"></div>
      <div class="sd-f"><label for="pay_method">Paid by</label><select id="pay_method" name="payment_method"><?php foreach (SDOC_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="sd-f sd-end"><button type="button" class="btn" id="cash-receipt"<?= $inv && $inv['status'] === 'posted' && (float) $inv['balance_amount'] > 0 ? '' : ' disabled' ?>>Cash Receipt</button></div>
      <div class="sd-f"><label for="pay_days">Payment Day/Days</label><input id="pay_days" type="number" min="0" placeholder="e.g. 30"></div>
      <div class="sd-f"><label for="pay_date">Due date</label><input id="pay_date" type="date"></div>
      <div class="sd-f sd-end"><button type="button" class="btn" id="date-add">Date Add</button></div>
      <div class="sd-f"><label for="member_no">Member No</label><div class="inline"><input id="member_no" name="member_no" maxlength="40" value="<?= sales_h($repost['member_no'] ?? ($member['member_no'] ?? '')) ?>"><button type="button" class="btn small" id="member-add">Add</button></div></div>
      <div class="sd-f"><label for="point_adjust">Point Adjust</label><div class="inline"><input id="point_adjust" name="point_adjust" type="number" min="0" step="0.01" value="<?= sales_h($repost['point_adjust'] ?? ($inv['points_redeemed'] ?? '0')) ?>"><button type="button" class="btn small" id="point-add">Add</button></div></div>
      <label class="sd-f sd-check"><input class="check" type="checkbox" name="create_installation" value="1"<?= !empty($h['create_installation']) ? ' checked' : '' ?>> Create Installation</label>
    </div>
    <div class="points"><span>Previous Point: <b id="p_prev"></b></span><span>Invoice Point: <b id="p_earn"></b></span><span>Invoice Adjust: <b id="p_adj"></b></span><span>Net Point: <b id="p_net"></b></span><span>Current Point: <b id="p_cur"></b></span><span id="p_name" class="muted"></span></div>
    <?php if ($inv && $d['payments']): ?><table class="mini"><thead><tr><th>Received</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead><tbody><?php foreach ($d['payments'] as $p): ?><tr><td><?= inv_fmt_date($p['paid_at']) ?></td><td><?= sales_h(SDOC_METHODS[$p['payment_method']] ?? $p['payment_method']) ?></td><td><?= sales_h($p['reference']) ?></td><td class="num"><?= sales_money($p['amount']) ?></td><td><a target="_blank" href="<?= $self ?>?receipt=<?= $id ?>&payment=<?= (int) $p['payment_id'] ?>">Receipt</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  </section>
  <div class="si-actions" data-step="5">
    <button class="btn primary si-save" id="save-btn" type="submit">Save</button>
    <div class="si-actions-row"><a class="btn" href="javascript:history.back()">Close</a>
  <?php if ($inv && !$lock): ?><button class="btn danger" name="action" value="void" formnovalidate onclick="return confirm('Void <?= sales_h($inv['invoice_no']) ?>? Its stock goes back to the store.')">Void Invoice</button><?php endif; ?></div>
    <p class="si-hint">Save first; Print, Preview and Challan open after saving.</p>
  </div>
</aside>
</div>
<input type="hidden" name="extra_charges" id="h_extra" value="<?= $val('extra_charges', '0') ?>"><input type="hidden" name="discount_amount" id="h_discount" value="<?= $val('discount_amount', '0') ?>"><input type="hidden" name="vat_amount" id="h_vat" value="<?= sales_h((float) ($h['vat_amount'] ?? 0) + (float) ($h['ait_amount'] ?? 0)) ?>">
</form>
<?php
if ($inv) {
    sdoc_attachment_panel($conn, 'invoice', $id, $self, $inv['status'] === 'posted');
}
sdoc_page_end([
    'kind' => 'invoice', 'ui' => '../../doc_ui.js', 'self' => $self, 'docId' => $id, 'saved' => (bool) $inv, 'lineLock' => $lock, 'void' => $inv && $inv['status'] === 'void',
    'items' => $items, 'products' => sdoc_products($conn), 'customers' => sales_customers($conn), 'defaultStore' => $defaultStore, 'stockOn' => sales_stock_on($conn),
    'schedules' => $repost ? (json_decode((string) ($repost['schedules_json'] ?? '[]'), true) ?: []) : array_map(fn($s) => ['due_date' => $s['due_date'], 'amount' => (float) $s['amount'], 'note' => $s['note']], $d['schedules'] ?? []),
    'exchanges' => $repost ? (json_decode((string) ($repost['exchanges_json'] ?? '[]'), true) ?: []) : array_map(fn($x) => ['description' => $x['description'], 'serial_no' => $x['serial_no'], 'quantity' => (float) $x['quantity'], 'amount' => (float) $x['amount']], $d['exchanges'] ?? []),
    'member' => $member ? ['member_no' => $member['member_no'], 'name' => $member['member_name'], 'balance' => sdoc_member_balance($conn, (int) $member['member_id'], $id)] : null,
    'points' => sdoc_point_settings($conn), 'paid' => $inv ? (float) $inv['cash_received'] : 0, 'due' => $inv ? (float) $inv['balance_amount'] : 0, 'held' => 'sdoc_hold_invoice',
]);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
