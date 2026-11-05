<?php
/**
 * Sales Bill from Sales Order: turns all or part of an open order into a sales invoice.
 * The invoice is written to sales_invoices / sales_invoice_items, so it prints and lists with
 * the normal Sales Invoice page. Order advance is applied as an invoice payment.
 * Quantity already delivered by a challan is not open here (bill it from the challan), and the bill takes goods out of
 * stock like a Sales Invoice when sales stock is on.
 * Tables: billing/sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql; stock needs inventory/schema.sql and sales/schema_v2.sql.
 */
require_once dirname(__DIR__) . '/sales_common.php';
require_once dirname(__DIR__) . '/sales_stock.php';

$self = 'sal_bill_from_sal_order.php';
$schemaReady = sales_tables_ready($conn, ['sales_invoices', 'sales_invoice_items', 'sales_invoice_payments', 'sales_invoice_sequences', 'sales_orders', 'sales_order_items', 'sales_order_bills', 'sales_order_bill_items']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$schemaReady) {
        sales_flash_redirect($self, 'error', 'Billing tables are missing. Import the schema files first.');
    }
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $back = $self;
    try {
        $conn->begin_transaction();
        $order = sales_row($conn, 'SELECT * FROM sales_orders WHERE order_id=? FOR UPDATE', 'i', [$orderId]);
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        $back = $self . '?order=' . urlencode($order['order_no']);
        if ($order['status'] !== 'open') {
            throw new RuntimeException('Only open orders can be billed.');
        }
        $salesDate = sales_valid_date($_POST['sales_date'] ?? '');
        if ($salesDate === null) {
            throw new RuntimeException('Enter a valid bill date.');
        }
        if ($salesDate < $order['order_date']) {
            throw new RuntimeException('The bill date cannot be before the order date (' . $order['order_date'] . ').');
        }
        $billType = (($_POST['bill_type'] ?? '') === 'Credit') ? 'Credit' : 'Cash';

        $billed = sales_order_billed_map($conn, $orderId);
        $challan = sales_order_challan_map($conn, $orderId);
        $orderLines = sales_rows($conn, 'SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number FOR UPDATE', 'i', [$orderId]);
        $posted = is_array($_POST['line'] ?? null) ? $_POST['line'] : [];
        $billLines = [];
        $subtotal = 0.0;
        $totalQty = 0.0;
        foreach ($orderLines as $line) {
            $id = (int) $line['item_id'];
            $in = is_array($posted[$id] ?? null) ? $posted[$id] : [];
            $qty = sales_qty($in['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $remaining = round((float) $line['quantity'] - ($billed[$id] ?? 0.0) - ($challan[$id] ?? 0.0), 3);
            if ($qty > $remaining + 0.0005) {
                throw new RuntimeException('Bill quantity for "' . $line['description'] . '" is more than the remaining ' . sales_qty_fmt($remaining) . '.');
            }
            $orderQty = (float) $line['quantity'];
            $disc = $orderQty > 0 ? round((float) $line['discount_amount'] * $qty / $orderQty, 2) : 0.0;
            $total = max(0.0, round($qty * (float) $line['unit_price'] - $disc, 2));
            $subtotal += $total;
            $totalQty += $qty;
            $billLines[] = [
                'line' => $line,
                'quantity' => $qty,
                'discount' => $disc,
                'total' => $total,
                'serial' => implode("\n", array_filter(array_map('trim', preg_split('/[\r\n,]+/', sales_str($in['serial_imei'] ?? '', 4000))), 'strlen')),
                'narration' => sales_str($in['narration'] ?? '', 255),
            ];
        }
        if (!$billLines) {
            throw new RuntimeException('Enter a bill quantity for at least one line.');
        }
        $subtotal = round($subtotal, 2);

        $extra = sales_amount($_POST['extra_charges'] ?? 0);
        $discount = sales_amount($_POST['discount_amount'] ?? 0);
        $vat = sales_amount($_POST['vat_amount'] ?? 0);
        $ait = sales_amount($_POST['ait_amount'] ?? 0);
        $net = max(0.0, round($subtotal + $extra + $vat + $ait - $discount, 2));

        $advanceLeft = max(0.0, round((float) $order['advance_received'] - sales_order_advance_applied($conn, $orderId), 2));
        $advanceApply = sales_amount($_POST['advance_apply'] ?? 0);
        if ($advanceApply > $advanceLeft + 0.004) {
            throw new RuntimeException('Only ' . sales_money($advanceLeft) . ' of the order advance is left to apply.');
        }
        if ($advanceApply > $net) {
            throw new RuntimeException('Advance applied cannot be more than the bill net amount.');
        }
        $cashNow = sales_amount($_POST['cash_now'] ?? 0);
        if ($advanceApply + $cashNow > $net + 0.004) {
            throw new RuntimeException('Advance plus cash received cannot be more than the bill net amount (' . sales_money($net) . ').');
        }
        $cashReceived = round($advanceApply + $cashNow, 2);
        $balance = max(0.0, round($net - $cashReceived, 2));
        $userId = (int) ($GLOBALS['user_id'] ?? 0);
        $narration = sales_post_string('narration', 2000);
        if ($narration === '') {
            $narration = 'Bill from sales order ' . $order['order_no'];
        }
        $refNo = sales_post_string('ref_no', 80);
        if ($refNo === '') {
            $refNo = $order['order_no'];
        }

        $invoiceNo = sales_next_number($conn, 'SI', (int) substr($salesDate, 0, 4), 'sales_invoice_sequences');
        $invoiceId = sales_insert(
            $conn,
            'INSERT INTO sales_invoices (invoice_no, ref_no, sales_date, bill_type, customer_id, customer_group, customer_name, mobile, attention, address, delivery_point, create_installation, bin, nid, sales_person, narration, subtotal, extra_charges, discount_amount, vat_amount, ait_amount, exchange_amount, net_amount, cash_received, balance_amount, total_quantity, created_by) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ssssi' . str_repeat('s', 6) . 'i' . str_repeat('s', 4) . str_repeat('d', 10) . 'i',
            [
                $invoiceNo, $refNo, $salesDate, $billType, $order['customer_id'] !== null ? (int) $order['customer_id'] : null,
                $order['customer_group'], $order['customer_name'], $order['mobile'], $order['attention'], (string) $order['address'], $order['delivery_point'],
                (int) $order['create_installation'], $order['bin'], $order['nid'], $order['sales_person'], $narration,
                $subtotal, $extra, $discount, $vat, $ait, 0.0, $net, $cashReceived, $balance, round($totalQty, 3), $userId,
            ]
        );
        $billId = sales_insert($conn, 'INSERT INTO sales_order_bills (order_id, invoice_id, advance_applied, created_by) VALUES (?, ?, ?, ?)', 'iidi', [$orderId, $invoiceId, $advanceApply, $userId]);

        foreach ($billLines as $n => $b) {
            $l = $b['line'];
            $invoiceItemId = sales_insert(
                $conn,
                'INSERT INTO sales_invoice_items (invoice_id, product_id, product_code, product_group, brand, model_caption, description, product_narration, warranty, store_name, unit_name, serial_imei, quantity, free_quantity, unit_price, discount_amount, line_total, line_number) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)',
                'ii' . str_repeat('s', 10) . 'dddd' . 'i',
                [
                    $invoiceId, $l['product_id'] !== null ? (int) $l['product_id'] : null, $l['product_code'], $l['product_group'], $l['brand'],
                    $l['model_caption'], $l['description'], $b['narration'], $l['warranty'], $l['store_name'], $l['unit_name'], $b['serial'],
                    $b['quantity'], (float) $l['unit_price'], $b['discount'], $b['total'], $n + 1,
                ]
            );
            sales_insert(
                $conn,
                'INSERT INTO sales_order_bill_items (bill_id, order_item_id, invoice_item_id, quantity) VALUES (?, ?, ?, ?)',
                'iiid',
                [$billId, (int) $l['item_id'], $invoiceItemId, $b['quantity']]
            );
        }

        if ($advanceApply > 0) {
            $ref = 'Advance from order ' . $order['order_no'];
            sales_insert($conn, "INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, 'other', ?, ?, ?)", 'idsi', [$invoiceId, $advanceApply, $ref, $userId]);
        }
        if ($cashNow > 0) {
            $method = sales_payment_method('payment_method');
            sales_insert($conn, 'INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, ?, ?, ?, ?)', 'isdsi', [$invoiceId, $method, $cashNow, sales_post_string('payment_reference', 120), $userId]);
        }

        if (sales_tables_ready($conn, ['sales_members'])) {
            sales_exec($conn, 'UPDATE sales_invoices SET branch_id=? WHERE invoice_id=?', 'ii', [(int) ($order['branch_id'] ?? 0) ?: inv_current_branch($conn), $invoiceId])->close();
            sales_stock_post_invoice($conn, $invoiceId);
        }
        $status = sales_order_refresh_status($conn, $orderId);
        $conn->commit();
        $message = 'Invoice ' . $invoiceNo . ' created from order ' . $order['order_no'] . '.';
        $message .= $status === 'converted' ? ' The order is now fully billed.' : ' Some quantity is still open on the order.';
        sales_flash_redirect($self . '?done=' . $invoiceId, 'success', $message);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Bill from order failed: ' . $e->getMessage());
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$view = (string) ($_GET['view'] ?? 'form');
$orderNo = trim((string) ($_GET['order'] ?? ''));
$doneId = (int) ($_GET['done'] ?? 0);
$order = null;
$lines = [];
$billed = [];
$bills = [];
$openOrders = [];
$isFirstBill = true;
$advanceLeft = 0.0;
if ($schemaReady) {
    if ($view === 'list') {
        $like = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
        $bills = sales_list_rows($conn, 'SELECT b.bill_id, b.invoice_id, b.advance_applied, i.invoice_no, i.sales_date, i.net_amount, i.cash_received, i.balance_amount, o.order_no, o.customer_name, o.status FROM sales_order_bills b JOIN sales_invoices i ON i.invoice_id=b.invoice_id JOIN sales_orders o ON o.order_id=b.order_id WHERE i.invoice_no LIKE ? OR o.order_no LIKE ? OR o.customer_name LIKE ? ORDER BY b.bill_id DESC', 'sss', [$like, $like, $like]);
    } elseif ($orderNo !== '') {
        $order = sales_row($conn, 'SELECT * FROM sales_orders WHERE order_no=? OR ref_no=? ORDER BY order_id DESC LIMIT 1', 'ss', [$orderNo, $orderNo]);
        if ($order) {
            $lines = sales_rows($conn, 'SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number', 'i', [(int) $order['order_id']]);
            $billed = sales_order_billed_map($conn, (int) $order['order_id']);
            $challan = sales_order_challan_map($conn, (int) $order['order_id']);
            $isFirstBill = !sales_row($conn, 'SELECT 1 FROM sales_order_bills WHERE order_id=? LIMIT 1', 'i', [(int) $order['order_id']]);
            $advanceLeft = max(0.0, round((float) $order['advance_received'] - sales_order_advance_applied($conn, (int) $order['order_id']), 2));
        }
    } else {
        $openOrders = sales_rows($conn, "SELECT order_id, order_no, ref_no, order_date, delivery_date, customer_name, mobile, net_amount, advance_received FROM sales_orders WHERE status='open' ORDER BY COALESCE(delivery_date, order_date), order_id LIMIT 200");
    }
}

sales_page_start('Sales Bill from Sales Order', 'bill');
?>
<div class="titlebar"><h1>Sales Bill from Sales Order</h1><div class="toolbar"><a class="btn" href="<?= $self ?>">Pick Order</a><a class="btn" href="<?= $self ?>?view=list">Bills from Orders</a><?php if ($doneId > 0): ?><a class="btn primary" target="_blank" href="../sales_invoice/sales_invoice.php?print=<?= $doneId ?>">Print invoice</a><?php endif; ?></div></div>
<?= sales_flash_html($flash) ?>
<?php if (!$schemaReady): ?>
<?= sales_schema_missing_html('billing/sales/sales_order/schema.sql and billing/sales/sal_bill_from_sal_order/schema.sql') ?>
<?php elseif ($view === 'list'): ?>
<form class="list-search" method="get"><input type="hidden" name="view" value="list"><input class="input" name="q" value="<?= sales_h($_GET['q'] ?? '') ?>" placeholder="Invoice no, order no or customer"><button class="btn primary">Search</button></form>
<div class="table-scroll"><table><thead><tr><th>Invoice</th><th>Date</th><th>Order</th><th>Order Status</th><th>Customer</th><th class="num">Net</th><th class="num">Advance Applied</th><th class="num">Paid</th><th class="num">Balance</th><th></th></tr></thead><tbody>
<?php if (!$bills): ?><tr><td colspan="10" class="empty">No bills made from orders yet.</td></tr><?php endif; ?>
<?php foreach ($bills as $b): ?><tr><td><?= sales_h($b['invoice_no']) ?></td><td><?= sales_h($b['sales_date']) ?></td><td><?= sales_h($b['order_no']) ?></td><td><span class="badge <?= sales_h($b['status']) ?>"><?= sales_h(ucfirst($b['status'])) ?></span></td><td><?= sales_h($b['customer_name']) ?></td><td class="num"><?= sales_money($b['net_amount']) ?></td><td class="num"><?= sales_money($b['advance_applied']) ?></td><td class="num"><?= sales_money($b['cash_received']) ?></td><td class="num"><?= sales_money($b['balance_amount']) ?></td><td><a class="btn small" target="_blank" href="../sales_invoice/sales_invoice.php?print=<?= (int) $b['invoice_id'] ?>">Print</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif (!$order): ?>
<form class="list-search" method="get"><input class="input" name="order" value="<?= sales_h($orderNo) ?>" placeholder="Order no or reference" required><button class="btn primary">Load Order</button></form>
<?php if ($orderNo !== ''): ?><div class="alert error">No order matches "<?= sales_h($orderNo) ?>".</div><?php endif; ?>
<div class="section-title">Open orders waiting for a bill</div>
<div class="table-scroll"><table><thead><tr><th>Order</th><th>Reference</th><th>Date</th><th>Delivery</th><th>Customer</th><th>Mobile</th><th class="num">Net</th><th class="num">Advance</th><th></th></tr></thead><tbody>
<?php if (!$openOrders): ?><tr><td colspan="9" class="empty">No open orders.</td></tr><?php endif; ?>
<?php foreach ($openOrders as $o): ?><tr><td><?= sales_h($o['order_no']) ?></td><td><?= sales_h($o['ref_no']) ?></td><td><?= sales_h($o['order_date']) ?></td><td><?= sales_h($o['delivery_date'] ?? '') ?></td><td><?= sales_h($o['customer_name']) ?></td><td><?= sales_h($o['mobile']) ?></td><td class="num"><?= sales_money($o['net_amount']) ?></td><td class="num"><?= sales_money($o['advance_received']) ?></td><td><a class="btn small primary" href="<?= $self ?>?order=<?= urlencode($o['order_no']) ?>">Make Bill</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($order['status'] !== 'open'): ?>
<div class="alert error">Order <?= sales_h($order['order_no']) ?> is <?= sales_h($order['status']) ?>, so it cannot be billed.</div>
<?php else: ?>
<div class="summary"><div>Order<b><?= sales_h($order['order_no']) ?></b></div><div>Customer<b><?= sales_h($order['customer_name']) ?></b></div><div>Mobile<b><?= sales_h($order['mobile']) ?></b></div><div>Order Net<b><?= sales_money($order['net_amount']) ?></b></div><div>Advance Left<b><?= sales_money($advanceLeft) ?></b></div></div>
<form method="post" id="bill-form" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
<div class="grid g4">
<div class="field"><label for="sales_date">Bill Date</label><input id="sales_date" name="sales_date" type="date" value="<?= date('Y-m-d') ?>" min="<?= sales_h($order['order_date']) ?>" required></div>
<div class="field"><label for="bill_type">Bill Type</label><select id="bill_type" name="bill_type"><option <?= $order['bill_type'] === 'Cash' ? 'selected' : '' ?>>Cash</option><option <?= $order['bill_type'] === 'Credit' ? 'selected' : '' ?>>Credit</option></select></div>
<div class="field"><label for="ref_no">Ref No</label><input id="ref_no" name="ref_no" maxlength="80" value="<?= sales_h($order['order_no']) ?>"></div>
<div class="field"><label for="narration">Narration</label><input id="narration" name="narration" maxlength="2000" placeholder="Bill from sales order <?= sales_h($order['order_no']) ?>"></div>
</div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Description</th><th class="num">Ordered</th><th class="num">Billed</th><th class="num">Remaining</th><th>Bill Qty</th><th class="num">Unit Price</th><th class="num">Discount</th><th class="num">Amount</th><th>Serial / IMEI</th><th>Narration</th></tr></thead><tbody>
<?php foreach ($lines as $i => $l): $id = (int) $l['item_id']; $rem = max(0, round((float) $l['quantity'] - ($billed[$id] ?? 0) - ($challan[$id] ?? 0), 3)); ?>
<tr data-line data-price="<?= sales_h($l['unit_price']) ?>" data-oq="<?= sales_h($l['quantity']) ?>" data-od="<?= sales_h($l['discount_amount']) ?>"><td><?= $i + 1 ?></td><td><?= sales_h(trim($l['product_code'] . ' ' . $l['description'])) ?><br><span class="muted"><?= sales_h($l['model_caption']) ?> <?= sales_h($l['store_name']) ?></span></td>
<td class="num"><?= sales_qty_fmt($l['quantity']) ?></td><td class="num"><?= sales_qty_fmt($billed[$id] ?? 0) ?></td><td class="num"><?= sales_qty_fmt($rem) ?></td>
<td><input type="number" name="line[<?= $id ?>][quantity]" min="0" max="<?= sales_h($rem) ?>" step="0.001" value="<?= sales_h($rem) ?>" data-q <?= $rem <= 0 ? 'readonly' : '' ?>></td>
<td class="num"><?= sales_money($l['unit_price']) ?></td><td class="num" data-d>0.00</td><td class="num" data-t>0.00</td>
<td><input name="line[<?= $id ?>][serial_imei]" maxlength="255"></td><td><input name="line[<?= $id ?>][narration]" maxlength="255"></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="bottom"><div class="section"><div class="section-title">Payment</div>
<div class="grid g2" style="margin-top:0">
<div class="field"><label for="advance_apply">Apply Order Advance (max <?= sales_money($advanceLeft) ?>)</label><input id="advance_apply" name="advance_apply" type="number" min="0" max="<?= sales_h($advanceLeft) ?>" step="0.01" value="<?= sales_h($advanceLeft) ?>"></div>
<div class="field"><label for="cash_now">Cash Received Now</label><input id="cash_now" name="cash_now" type="number" min="0" step="0.01" value="0"></div>
<div class="field"><label for="payment_method">Payment Method</label><select id="payment_method" name="payment_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option><option value="other">Other</option></select></div>
<div class="field"><label for="payment_reference">Payment Reference</label><input id="payment_reference" name="payment_reference" maxlength="120"></div>
</div></div>
<div class="totals">
<div><span>Subtotal</span><strong id="t_sub">0.00</strong></div>
<div><label for="extra_charges">Extra Charges</label><input id="extra_charges" name="extra_charges" type="number" min="0" step="0.01" value="<?= $isFirstBill ? sales_h($order['extra_charges']) : '0' ?>"></div>
<div><label for="discount_amount">Less Discount</label><input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="<?= $isFirstBill ? sales_h($order['discount_amount']) : '0' ?>"></div>
<div><label for="vat_amount">VAT</label><input id="vat_amount" name="vat_amount" type="number" min="0" step="0.01" value="<?= $isFirstBill ? sales_h($order['vat_ait_amount']) : '0' ?>"></div>
<div><label for="ait_amount">AIT</label><input id="ait_amount" name="ait_amount" type="number" min="0" step="0.01" value="0"></div>
<div class="grand"><span>Net Payable</span><strong id="t_net">0.00</strong></div>
<div><span>Total Received</span><strong id="t_paid">0.00</strong></div>
<div class="grand"><span>Balance Due</span><strong id="t_bal">0.00</strong></div>
</div></div>
<?php if (!$isFirstBill): ?><p class="footnote">This order already has a bill, so header charges start at zero. The order's VAT / AIT was entered as one figure and defaults into VAT on the first bill.</p><?php endif; ?>
<div class="toolbar" style="margin-top:12px"><button class="btn primary">Create Invoice</button><a class="btn" href="<?= $self ?>?order=<?= urlencode($order['order_no']) ?>">Reset</a></div>
</form>
<?php endif; ?>
<?php
$script = $order && $order['status'] === 'open' ? '
function recalc(){let sub=0;document.querySelectorAll("[data-line]").forEach(tr=>{const q=Number(tr.querySelector("[data-q]").value||0),oq=Number(tr.dataset.oq),d=oq>0?Math.round(Number(tr.dataset.od)*q/oq*100)/100:0,t=Math.max(0,q*Number(tr.dataset.price)-d);tr.querySelector("[data-d]").textContent=money(d);tr.querySelector("[data-t]").textContent=money(t);sub+=t});
 const net=Math.max(0,sub+num("extra_charges")+num("vat_amount")+num("ait_amount")-num("discount_amount")),paid=num("advance_apply")+num("cash_now");
 document.getElementById("t_sub").textContent=money(sub);document.getElementById("t_net").textContent=money(net);document.getElementById("t_paid").textContent=money(paid);document.getElementById("t_bal").textContent=money(Math.max(0,net-paid))}
document.getElementById("bill-form").addEventListener("input",recalc);recalc();' : '';
sales_page_end($script);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
