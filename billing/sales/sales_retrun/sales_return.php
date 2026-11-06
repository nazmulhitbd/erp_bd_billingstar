<?php
/**
 * Sales Return: return items from a saved sales invoice.
 * The return value first reduces the invoice due (if chosen), the rest is refunded.
 * When the invoice took goods out of stock, the returned goods (and their serials) go back into the store.
 * Tables: billing/sales/schema.sql then billing/sales/sales_retrun/schema.sql; stock needs inventory/schema.sql and sales/schema_v2.sql.
 */
require_once dirname(__DIR__) . '/sales_common.php';
require_once dirname(__DIR__) . '/sales_stock.php';

$self = 'sales_return.php';
$schemaReady = sales_tables_ready($conn, ['sales_invoices', 'sales_invoice_items', 'sales_returns', 'sales_return_items', 'sales_doc_sequences']);

/** Already returned [invoice_item_id => ['q' => qty, 'f' => free]] for posted returns. */
function sales_returned_map(mysqli $db, $invoiceId)
{
    $map = [];
    $rows = sales_rows(
        $db,
        "SELECT ri.invoice_item_id, SUM(ri.quantity) AS q, SUM(ri.free_quantity) AS f FROM sales_return_items ri JOIN sales_returns r ON r.return_id=ri.return_id WHERE r.invoice_id=? AND r.status='posted' GROUP BY ri.invoice_item_id",
        'i',
        [(int) $invoiceId]
    );
    foreach ($rows as $row) {
        $map[(int) $row['invoice_item_id']] = ['q' => (float) $row['q'], 'f' => (float) $row['f']];
    }
    return $map;
}

function sales_invoice_refunded(mysqli $db, $invoiceId)
{
    $row = sales_row($db, "SELECT COALESCE(SUM(refund_amount),0) AS r FROM sales_returns WHERE invoice_id=? AND status='posted'", 'i', [(int) $invoiceId]);
    return round((float) ($row['r'] ?? 0), 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$schemaReady) {
        sales_flash_redirect($self, 'error', 'Sales return tables are missing. Import the schema files first.');
    }
    $action = (string) ($_POST['action'] ?? 'save');
    $userId = (int) ($GLOBALS['user_id'] ?? 0);

    if ($action === 'void') {
        $returnId = (int) ($_POST['return_id'] ?? 0);
        try {
            $conn->begin_transaction();
            $ret = sales_row($conn, 'SELECT * FROM sales_returns WHERE return_id=? FOR UPDATE', 'i', [$returnId]);
            if (!$ret || $ret['status'] !== 'posted') {
                throw new RuntimeException('Only posted returns can be voided.');
            }
            sales_row($conn, 'SELECT invoice_id FROM sales_invoices WHERE invoice_id=? FOR UPDATE', 'i', [(int) $ret['invoice_id']]);
            if (!empty($ret['stock_doc_id'])) {
                sales_stock_remove($conn, (int) $ret['stock_doc_id']);
            }
            sales_exec($conn, "UPDATE sales_returns SET status='void' WHERE return_id=?", 'i', [$returnId])->close();
            if ((float) $ret['due_adjusted'] > 0) {
                sales_exec($conn, 'UPDATE sales_invoices SET balance_amount = balance_amount + ? WHERE invoice_id=?', 'di', [(float) $ret['due_adjusted'], (int) $ret['invoice_id']])->close();
            }
            $conn->commit();
            sales_flash_redirect($self . '?view=list', 'success', 'Return ' . $ret['return_no'] . ' voided. Any due adjusted by it was added back to the invoice.');
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            sales_flash_redirect($self . '?view=list', 'error', $e->getMessage());
        }
    }

    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $back = $self;
    try {
        $conn->begin_transaction();
        $invoice = sales_row($conn, 'SELECT * FROM sales_invoices WHERE invoice_id=? FOR UPDATE', 'i', [$invoiceId]);
        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }
        $back = $self . '?invoice=' . urlencode($invoice['invoice_no']);
        if ($invoice['status'] !== 'posted') {
            throw new RuntimeException('Returns can only be made against posted invoices.');
        }
        $returnDate = sales_valid_date($_POST['return_date'] ?? '');
        if ($returnDate === null || $returnDate < $invoice['sales_date']) {
            throw new RuntimeException('Enter a valid return date on or after the invoice date (' . $invoice['sales_date'] . ').');
        }
        $returned = sales_returned_map($conn, $invoiceId);
        $invoiceLines = sales_rows($conn, 'SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number', 'i', [$invoiceId]);
        $posted = is_array($_POST['line'] ?? null) ? $_POST['line'] : [];
        $returnLines = [];
        $itemsAmount = 0.0;
        $totalQty = 0.0;
        foreach ($invoiceLines as $line) {
            $id = (int) $line['item_id'];
            $in = is_array($posted[$id] ?? null) ? $posted[$id] : [];
            $qty = sales_qty($in['quantity'] ?? 0);
            $free = sales_qty($in['free_quantity'] ?? 0);
            if ($qty <= 0 && $free <= 0) {
                continue;
            }
            $leftQty = round((float) $line['quantity'] - ($returned[$id]['q'] ?? 0), 3);
            $leftFree = round((float) $line['free_quantity'] - ($returned[$id]['f'] ?? 0), 3);
            if ($qty > $leftQty + 0.0005) {
                throw new RuntimeException('Return quantity for "' . $line['description'] . '" is more than the ' . sales_qty_fmt($leftQty) . ' still returnable.');
            }
            if ($free > $leftFree + 0.0005) {
                throw new RuntimeException('Free return quantity for "' . $line['description'] . '" is more than the ' . sales_qty_fmt($leftFree) . ' still returnable.');
            }
            $unitValue = (float) $line['quantity'] > 0 ? round((float) $line['line_total'] / (float) $line['quantity'], 4) : 0.0;
            $lineTotal = round($qty * $unitValue, 2);
            $itemsAmount += $lineTotal;
            $totalQty += $qty + $free;
            $returnLines[] = [
                'line' => $line,
                'quantity' => $qty,
                'free' => $free,
                'unit_value' => $unitValue,
                'total' => $lineTotal,
                'condition' => (($in['condition'] ?? '') === 'damaged') ? 'damaged' : 'good',
                // One serial per line in storage; the form shows them comma separated.
                'serial' => implode("\n", array_filter(array_map('trim', preg_split('/[\r\n,]+/', sales_str($in['serial_imei'] ?? $line['serial_imei'], 4000))), 'strlen')),
            ];
        }
        if (!$returnLines) {
            throw new RuntimeException('Enter a return quantity for at least one line.');
        }
        $itemsAmount = round($itemsAmount, 2);
        $deduction = sales_amount($_POST['deduction_amount'] ?? 0);
        if ($deduction > $itemsAmount) {
            throw new RuntimeException('Deduction cannot be more than the value of the returned items.');
        }
        $returnAmount = round($itemsAmount - $deduction, 2);
        $dueAdjust = sales_amount($_POST['due_adjusted'] ?? 0);
        if ($dueAdjust > (float) $invoice['balance_amount'] + 0.004) {
            throw new RuntimeException('Only ' . sales_money($invoice['balance_amount']) . ' is due on this invoice.');
        }
        if ($dueAdjust > $returnAmount) {
            throw new RuntimeException('Due adjustment cannot be more than the return amount.');
        }
        $refund = round($returnAmount - $dueAdjust, 2);
        $refundable = round((float) $invoice['cash_received'] - sales_invoice_refunded($conn, $invoiceId), 2);
        if ($refund > $refundable + 0.004) {
            throw new RuntimeException('Refund ' . sales_money($refund) . ' is more than the ' . sales_money(max(0, $refundable)) . ' the customer paid and has not been refunded. Adjust more against the due.');
        }
        $method = sales_payment_method('refund_method');

        $returnNo = sales_next_number($conn, 'SR', $returnDate);
        $returnId = sales_insert(
            $conn,
            'INSERT INTO sales_returns (return_no, invoice_id, return_date, customer_id, customer_name, mobile, reason, items_amount, deduction_amount, return_amount, due_adjusted, refund_amount, refund_method, refund_reference, total_quantity, created_by) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'sisisss' . 'ddddd' . 'ss' . 'd' . 'i',
            [
                $returnNo, $invoiceId, $returnDate, $invoice['customer_id'] !== null ? (int) $invoice['customer_id'] : null,
                $invoice['customer_name'], $invoice['mobile'], sales_post_string('reason', 255),
                $itemsAmount, $deduction, $returnAmount, $dueAdjust, $refund, $method, sales_post_string('refund_reference', 120),
                round($totalQty, 3), $userId,
            ]
        );
        foreach ($returnLines as $r) {
            $l = $r['line'];
            sales_insert(
                $conn,
                'INSERT INTO sales_return_items (return_id, invoice_item_id, product_id, product_code, description, store_name, unit_name, serial_imei, item_condition, quantity, free_quantity, unit_value, line_total) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iii' . str_repeat('s', 6) . 'dddd',
                [
                    $returnId, (int) $l['item_id'], $l['product_id'] !== null ? (int) $l['product_id'] : null, $l['product_code'], $l['description'],
                    $l['store_name'], $l['unit_name'], $r['serial'], $r['condition'], $r['quantity'], $r['free'], $r['unit_value'], $r['total'],
                ]
            );
        }
        if ($dueAdjust > 0) {
            sales_exec($conn, 'UPDATE sales_invoices SET balance_amount = GREATEST(0, balance_amount - ?) WHERE invoice_id=?', 'di', [$dueAdjust, $invoiceId])->close();
        }
        if (sales_tables_ready($conn, ['sales_members'])) {
            sales_stock_post_return($conn, $returnId);
        }
        notify_approval_needed('sales_return', 'Sales Return', $returnNo);
        $conn->commit();
        sales_flash_redirect($self . '?done=' . $returnId, 'success', 'Return ' . $returnNo . ' saved. Refund ' . sales_money($refund) . ', due reduced by ' . sales_money($dueAdjust) . '.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Sales return failed: ' . $e->getMessage());
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Print ---------- */
if (isset($_GET['print']) && $schemaReady) {
    $ret = sales_row($conn, 'SELECT r.*, i.invoice_no, i.sales_date FROM sales_returns r JOIN sales_invoices i ON i.invoice_id=r.invoice_id WHERE r.return_id=?', 'i', [(int) $_GET['print']]);
    if (!$ret) {
        http_response_code(404);
        exit('Return not found.');
    }
    $items = sales_rows($conn, 'SELECT * FROM sales_return_items WHERE return_id=? ORDER BY return_item_id', 'i', [(int) $ret['return_id']]);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= sales_h($ret['return_no']) ?></title>
    <style>body{font:14px Arial,sans-serif;margin:28px;color:#222}h1{margin:0 0 6px}table{border-collapse:collapse;width:100%;margin-top:14px}th,td{border:1px solid #aaa;padding:6px;text-align:left}th{background:#eee}.num{text-align:right}.totals{margin:16px 0 0 auto;width:320px}.totals div{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:5px}.void{color:#a33;font-weight:bold}@media print{button{display:none}}</style></head><body>
    <button onclick="window.print()">Print</button>
    <h1>Sales Return <?= sales_h($ret['return_no']) ?></h1><?php if ($ret['status'] === 'void'): ?><div class="void">VOID</div><?php endif; ?>
    <p><b>Date:</b> <?= sales_h($ret['return_date']) ?> &nbsp; <b>Invoice:</b> <?= sales_h($ret['invoice_no']) ?> (<?= sales_h($ret['sales_date']) ?>)<br><b>Customer:</b> <?= sales_h($ret['customer_name']) ?> <?= sales_h($ret['mobile']) ?><br><b>Reason:</b> <?= sales_h($ret['reason']) ?></p>
    <table><thead><tr><th>SL</th><th>Description</th><th>Serial / IMEI</th><th>Condition</th><th class="num">Qty</th><th class="num">Free</th><th class="num">Unit Value</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($items as $i => $it): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h(trim($it['product_code'] . ' ' . $it['description'])) ?></td><td><?= sales_h($it['serial_imei']) ?></td><td><?= sales_h(ucfirst($it['item_condition'])) ?></td><td class="num"><?= sales_qty_fmt($it['quantity']) ?></td><td class="num"><?= sales_qty_fmt($it['free_quantity']) ?></td><td class="num"><?= sales_money($it['unit_value']) ?></td><td class="num"><?= sales_money($it['line_total']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="totals"><?php foreach (['items_amount' => 'Items value', 'deduction_amount' => 'Less deduction', 'return_amount' => 'Return amount', 'due_adjusted' => 'Adjusted against due', 'refund_amount' => 'Refunded (' . $ret['refund_method'] . ')'] as $f => $l): ?><div><span><?= sales_h($l) ?></span><b><?= sales_money($ret[$f]) ?></b></div><?php endforeach; ?></div>
    </body></html><?php
    exit;
}

$flash = sales_take_flash();
$view = (string) ($_GET['view'] ?? 'form');
$invoiceNo = trim((string) ($_GET['invoice'] ?? ''));
$doneId = (int) ($_GET['done'] ?? 0);
$invoice = null;
$lines = [];
$returned = [];
$returns = [];
$recent = [];
$refundable = 0.0;
if ($schemaReady) {
    if ($view === 'list') {
        $like = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
        $returns = sales_list_rows($conn, 'SELECT r.return_id, r.return_no, r.return_date, r.customer_name, r.mobile, r.return_amount, r.due_adjusted, r.refund_amount, r.status, i.invoice_no FROM sales_returns r JOIN sales_invoices i ON i.invoice_id=r.invoice_id WHERE r.return_no LIKE ? OR i.invoice_no LIKE ? OR r.customer_name LIKE ? OR r.mobile LIKE ? ORDER BY r.return_id DESC', 'ssss', [$like, $like, $like, $like]);
    } elseif ($invoiceNo !== '') {
        $invoice = sales_row($conn, 'SELECT * FROM sales_invoices WHERE invoice_no=? OR ref_no=? ORDER BY invoice_id DESC LIMIT 1', 'ss', [$invoiceNo, $invoiceNo]);
        if ($invoice) {
            $lines = sales_rows($conn, 'SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number', 'i', [(int) $invoice['invoice_id']]);
            $returned = sales_returned_map($conn, (int) $invoice['invoice_id']);
            $refundable = max(0.0, round((float) $invoice['cash_received'] - sales_invoice_refunded($conn, (int) $invoice['invoice_id']), 2));
        }
    } else {
        $recent = sales_rows($conn, "SELECT invoice_id, invoice_no, ref_no, sales_date, customer_name, mobile, net_amount, balance_amount FROM sales_invoices WHERE status='posted' ORDER BY invoice_id DESC LIMIT 50");
    }
}

sales_page_start('Sales Return', 'return');
?>
<div class="titlebar"><h1>Sales Return</h1><div class="toolbar"><a class="btn" href="<?= $self ?>">New Return</a><a class="btn" href="<?= $self ?>?view=list">Return List</a><?php if ($doneId > 0): ?><a class="btn primary" target="_blank" href="<?= $self ?>?print=<?= $doneId ?>">Print return</a><?php endif; ?></div></div>
<?= sales_flash_html($flash) ?>
<?php if (!$schemaReady): ?>
<?= sales_schema_missing_html('billing/sales/sales_retrun/schema.sql') ?>
<?php elseif ($view === 'list'): ?>
<form class="list-search" method="get"><input type="hidden" name="view" value="list"><input class="input" name="q" value="<?= sales_h($_GET['q'] ?? '') ?>" placeholder="Return no, invoice no, customer or mobile"><button class="btn primary">Search</button></form>
<div class="table-scroll"><table><thead><tr><th>Return</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Mobile</th><th class="num">Return Amount</th><th class="num">Due Adjusted</th><th class="num">Refunded</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$returns): ?><tr><td colspan="10" class="empty">No returns found.</td></tr><?php endif; ?>
<?php foreach ($returns as $r): ?><tr><td><?= sales_h($r['return_no']) ?></td><td><?= sales_h($r['return_date']) ?></td><td><?= sales_h($r['invoice_no']) ?></td><td><?= sales_h($r['customer_name']) ?></td><td><?= sales_h($r['mobile']) ?></td><td class="num"><?= sales_money($r['return_amount']) ?></td><td class="num"><?= sales_money($r['due_adjusted']) ?></td><td class="num"><?= sales_money($r['refund_amount']) ?></td><td><span class="badge <?= $r['status'] === 'void' ? 'cancelled' : 'converted' ?>"><?= sales_h(ucfirst($r['status'])) ?></span></td>
<td class="nowrap"><a class="btn small" target="_blank" href="<?= $self ?>?print=<?= (int) $r['return_id'] ?>">Print</a>
<?php if ($r['status'] === 'posted'): ?><form method="post" style="display:inline" onsubmit="return confirm('Void return <?= sales_h($r['return_no']) ?>?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="return_id" value="<?= (int) $r['return_id'] ?>"><button class="btn small danger">Void</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif (!$invoice): ?>
<form class="list-search" method="get"><input class="input" name="invoice" value="<?= sales_h($invoiceNo) ?>" placeholder="Invoice no or reference" required><button class="btn primary">Load Invoice</button></form>
<?php if ($invoiceNo !== ''): ?><div class="alert error">No invoice matches "<?= sales_h($invoiceNo) ?>".</div><?php endif; ?>
<div class="section-title">Recent invoices</div>
<div class="table-scroll"><table><thead><tr><th>Invoice</th><th>Reference</th><th>Date</th><th>Customer</th><th>Mobile</th><th class="num">Net</th><th class="num">Due</th><th></th></tr></thead><tbody>
<?php if (!$recent): ?><tr><td colspan="8" class="empty">No invoices yet.</td></tr><?php endif; ?>
<?php foreach ($recent as $i): ?><tr><td><?= sales_h($i['invoice_no']) ?></td><td><?= sales_h($i['ref_no']) ?></td><td><?= sales_h($i['sales_date']) ?></td><td><?= sales_h($i['customer_name']) ?></td><td><?= sales_h($i['mobile']) ?></td><td class="num"><?= sales_money($i['net_amount']) ?></td><td class="num"><?= sales_money($i['balance_amount']) ?></td><td><a class="btn small primary" href="<?= $self ?>?invoice=<?= urlencode($i['invoice_no']) ?>">Return</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($invoice['status'] !== 'posted'): ?>
<div class="alert error">Invoice <?= sales_h($invoice['invoice_no']) ?> is <?= sales_h($invoice['status']) ?>, so items cannot be returned against it.</div>
<?php else: ?>
<div class="summary"><div>Invoice<b><?= sales_h($invoice['invoice_no']) ?></b></div><div>Date<b><?= sales_h($invoice['sales_date']) ?></b></div><div>Customer<b><?= sales_h($invoice['customer_name']) ?></b></div><div>Net<b><?= sales_money($invoice['net_amount']) ?></b></div><div>Due<b><?= sales_money($invoice['balance_amount']) ?></b></div><div>Refundable Paid<b><?= sales_money($refundable) ?></b></div></div>
<form method="post" id="ret-form" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="invoice_id" value="<?= (int) $invoice['invoice_id'] ?>">
<div class="grid g3">
<div class="field"><label for="return_date">Return Date</label><input id="return_date" name="return_date" type="date" value="<?= date('Y-m-d') ?>" min="<?= sales_h($invoice['sales_date']) ?>" required></div>
<div class="field"><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" placeholder="Why the goods came back"></div>
</div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Description</th><th class="num">Sold</th><th class="num">Returned</th><th class="num">Unit Value</th><th>Return Qty</th><th>Free Qty</th><th>Condition</th><th>Serial / IMEI</th><th class="num">Amount</th></tr></thead><tbody>
<?php foreach ($lines as $n => $l): $id = (int) $l['item_id']; $q = (float) $l['quantity']; $uv = $q > 0 ? (float) $l['line_total'] / $q : 0; $leftQ = max(0, round($q - ($returned[$id]['q'] ?? 0), 3)); $leftF = max(0, round((float) $l['free_quantity'] - ($returned[$id]['f'] ?? 0), 3)); ?>
<tr data-line data-uv="<?= sales_h(round($uv, 4)) ?>"><td><?= $n + 1 ?></td><td><?= sales_h(trim($l['product_code'] . ' ' . $l['description'])) ?><br><span class="muted"><?= sales_h($l['model_caption']) ?></span></td>
<td class="num"><?= sales_qty_fmt($q) ?><?= (float) $l['free_quantity'] > 0 ? ' + ' . sales_qty_fmt($l['free_quantity']) . ' free' : '' ?></td>
<td class="num"><?= sales_qty_fmt($returned[$id]['q'] ?? 0) ?></td><td class="num"><?= sales_money($uv) ?></td>
<td><input type="number" name="line[<?= $id ?>][quantity]" min="0" max="<?= sales_h($leftQ) ?>" step="0.001" value="0" data-q <?= $leftQ <= 0 ? 'readonly' : '' ?>></td>
<td><input type="number" name="line[<?= $id ?>][free_quantity]" min="0" max="<?= sales_h($leftF) ?>" step="0.001" value="0" <?= $leftF <= 0 ? 'readonly' : '' ?>></td>
<td><select name="line[<?= $id ?>][condition]"><option value="good">Good</option><option value="damaged">Damaged</option></select></td>
<td><input name="line[<?= $id ?>][serial_imei]" maxlength="4000" value="<?= sales_h(implode(', ', array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $l['serial_imei'])), 'strlen'))) ?>" title="Keep only the serials being returned, separated by commas"></td><td class="num" data-t>0.00</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="bottom"><div class="section"><div class="section-title">Refund</div>
<div class="grid g2" style="margin-top:0">
<div class="field"><label for="refund_method">Refund Method</label><select id="refund_method" name="refund_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option><option value="other">Other</option></select></div>
<div class="field"><label for="refund_reference">Refund Reference</label><input id="refund_reference" name="refund_reference" maxlength="120"></div>
</div></div>
<div class="totals">
<div><span>Items Value</span><strong id="t_items">0.00</strong></div>
<div><label for="deduction_amount">Less Deduction</label><input id="deduction_amount" name="deduction_amount" type="number" min="0" step="0.01" value="0"></div>
<div class="grand"><span>Return Amount</span><strong id="t_ret">0.00</strong></div>
<div><label for="due_adjusted">Adjust Against Due</label><input id="due_adjusted" name="due_adjusted" type="number" min="0" max="<?= sales_h($invoice['balance_amount']) ?>" step="0.01" value="0"></div>
<div class="grand"><span>Refund to Customer</span><strong id="t_refund">0.00</strong></div>
</div></div>
<div class="toolbar" style="margin-top:12px"><button class="btn primary">Save Return</button><a class="btn" href="<?= $self ?>?invoice=<?= urlencode($invoice['invoice_no']) ?>">Reset</a></div>
<p class="footnote">Item value is the invoice line amount after its line discount, divided by the paid quantity. Free items carry no value.</p>
</form>
<?php endif; ?>
<?php
$script = $invoice && $invoice['status'] === 'posted' ? '
const due=' . (float) $invoice['balance_amount'] . ';let dueTouched=false;
document.getElementById("due_adjusted").addEventListener("input",()=>{dueTouched=true});
function recalc(){let items=0;document.querySelectorAll("[data-line]").forEach(tr=>{const t=Math.round(Number(tr.querySelector("[data-q]").value||0)*Number(tr.dataset.uv)*100)/100;tr.querySelector("[data-t]").textContent=money(t);items+=t});
 const ret=Math.max(0,items-num("deduction_amount"));const d=document.getElementById("due_adjusted");if(!dueTouched)d.value=money(Math.min(due,ret));
 document.getElementById("t_items").textContent=money(items);document.getElementById("t_ret").textContent=money(ret);document.getElementById("t_refund").textContent=money(Math.max(0,ret-Number(d.value||0)))}
document.getElementById("ret-form").addEventListener("input",recalc);recalc();' : '';
sales_page_end($script);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
