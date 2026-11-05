<?php
/**
 * Sales Order Adjustment: change quantities, prices, discounts, header charges,
 * advance and delivery date of an open order, add or remove lines, and keep an audit trail.
 * Tables: billing/sales/schema.sql, sales_order/schema.sql, sales_order_adjustment/schema.sql.
 */
require_once dirname(__DIR__) . '/sales_common.php';

$self = 'sales_order_adjustment.php';
$schemaReady = sales_tables_ready($conn, ['sales_orders', 'sales_order_items', 'sales_order_adjustments', 'sales_order_adjustment_lines', 'sales_doc_sequences']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$schemaReady) {
        sales_flash_redirect($self, 'error', 'Adjustment tables are missing. Import the schema files first.');
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
            throw new RuntimeException('Only open orders can be adjusted.');
        }
        $reason = sales_post_string('reason', 255);
        if ($reason === '') {
            throw new RuntimeException('Enter the reason for this adjustment.');
        }
        $adjDate = sales_valid_date($_POST['adjustment_date'] ?? '');
        if ($adjDate === null) {
            throw new RuntimeException('Enter a valid adjustment date.');
        }
        $newDelivery = sales_valid_date($_POST['delivery_date'] ?? '', true);
        if ($newDelivery === null) {
            throw new RuntimeException('Enter a valid delivery date or leave it blank.');
        }
        $newDelivery = $newDelivery === '' ? null : $newDelivery;

        $billed = sales_order_billed_map($conn, $orderId);
        $current = sales_rows($conn, 'SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number FOR UPDATE', 'i', [$orderId]);
        $posted = is_array($_POST['line'] ?? null) ? $_POST['line'] : [];
        $auditLines = [];
        $keep = [];
        foreach ($current as $line) {
            $id = (int) $line['item_id'];
            $in = is_array($posted[$id] ?? null) ? $posted[$id] : [];
            $billedQty = $billed[$id] ?? 0.0;
            $old = [(float) $line['quantity'], (float) $line['unit_price'], (float) $line['discount_amount'], (float) $line['line_total']];
            if (!empty($in['remove'])) {
                if ($billedQty > 0) {
                    throw new RuntimeException('"' . $line['description'] . '" is already billed and cannot be removed.');
                }
                $auditLines[] = [$id, 'removed', $line['description'], $old, [0.0, 0.0, 0.0, 0.0]];
                continue;
            }
            $qty = sales_qty($in['quantity'] ?? $line['quantity']);
            $price = sales_amount($in['unit_price'] ?? $line['unit_price']);
            $disc = sales_amount($in['discount_amount'] ?? $line['discount_amount']);
            if ($qty <= 0) {
                throw new RuntimeException('Quantity for "' . $line['description'] . '" must be greater than zero. Tick Remove to drop the line.');
            }
            if ($qty + 0.0005 < $billedQty) {
                throw new RuntimeException('Quantity for "' . $line['description'] . '" cannot be less than the billed quantity (' . sales_qty_fmt($billedQty) . ').');
            }
            $total = max(0.0, round($qty * $price - $disc, 2));
            $new = [$qty, $price, $disc, $total];
            if (abs($new[0] - $old[0]) > 0.0005 || abs($new[1] - $old[1]) > 0.004 || abs($new[2] - $old[2]) > 0.004) {
                $auditLines[] = [$id, 'changed', $line['description'], $old, $new];
            }
            $keep[$id] = $new;
        }

        $added = [];
        $newItems = json_decode((string) ($_POST['new_items_json'] ?? '[]'), true);
        foreach (is_array($newItems) ? $newItems : [] as $item) {
            $description = sales_str($item['description'] ?? '', 255);
            $qty = sales_qty($item['quantity'] ?? 0);
            if ($description === '' || $qty <= 0) {
                continue;
            }
            $price = sales_amount($item['unit_price'] ?? 0);
            $disc = sales_amount($item['discount_amount'] ?? 0);
            $added[] = [
                'product_id' => ((int) ($item['product_id'] ?? 0)) > 0 ? (int) $item['product_id'] : null,
                'product_code' => sales_str($item['product_code'] ?? '', 60),
                'product_group' => sales_str($item['product_group'] ?? '', 140),
                'brand' => sales_str($item['brand'] ?? '', 140),
                'model_caption' => sales_str($item['model_caption'] ?? '', 190),
                'description' => $description,
                'store_name' => sales_str($item['store_name'] ?? '', 140),
                'unit_name' => sales_str($item['unit_name'] ?? 'Pcs', 40) ?: 'Pcs',
                'warranty' => sales_str($item['warranty'] ?? '', 100),
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_amount' => $disc,
                'line_total' => max(0.0, round($qty * $price - $disc, 2)),
            ];
        }
        if (!$keep && !$added) {
            throw new RuntimeException('An order must keep at least one line. Cancel the order instead.');
        }

        $extra = sales_amount($_POST['extra_charges'] ?? $order['extra_charges']);
        $discount = sales_amount($_POST['discount_amount'] ?? $order['discount_amount']);
        $vatAit = sales_amount($_POST['vat_ait_amount'] ?? $order['vat_ait_amount']);
        $advance = sales_amount($_POST['advance_received'] ?? $order['advance_received']);
        $subtotal = 0.0;
        $totalQty = 0.0;
        foreach ($keep as $v) {
            $subtotal += $v[3];
            $totalQty += $v[0];
        }
        foreach ($added as $a) {
            $subtotal += $a['line_total'];
            $totalQty += $a['quantity'];
        }
        $subtotal = round($subtotal, 2);
        $net = max(0.0, round($subtotal + $extra + $vatAit - $discount, 2));
        if ($advance > $net) {
            throw new RuntimeException('Advance cannot be more than the new net amount (' . sales_money($net) . ').');
        }
        $applied = sales_order_advance_applied($conn, $orderId);
        if ($advance + 0.004 < $applied) {
            throw new RuntimeException('Advance cannot go below the amount already applied to bills (' . sales_money($applied) . ').');
        }

        $headerChanged = abs($extra - (float) $order['extra_charges']) > 0.004
            || abs($discount - (float) $order['discount_amount']) > 0.004
            || abs($vatAit - (float) $order['vat_ait_amount']) > 0.004
            || abs($advance - (float) $order['advance_received']) > 0.004
            || ($newDelivery ?? '') !== ($order['delivery_date'] ?? '');
        if (!$auditLines && !$added && !$headerChanged) {
            throw new RuntimeException('Nothing was changed.');
        }

        // Apply line changes.
        foreach ($auditLines as [$id, $action]) {
            if ($action === 'removed') {
                sales_exec($conn, 'DELETE FROM sales_order_items WHERE item_id=? AND order_id=?', 'ii', [$id, $orderId])->close();
            }
        }
        foreach ($keep as $id => [$qty, $price, $disc, $total]) {
            sales_exec(
                $conn,
                'UPDATE sales_order_items SET quantity=?, unit_price=?, discount_amount=?, line_total=? WHERE item_id=? AND order_id=?',
                'ddddii',
                [$qty, $price, $disc, $total, $id, $orderId]
            )->close();
        }
        $lineNo = (int) (sales_row($conn, 'SELECT COALESCE(MAX(line_number),0) AS n FROM sales_order_items WHERE order_id=?', 'i', [$orderId])['n'] ?? 0);
        foreach ($added as $a) {
            $lineNo++;
            $newId = sales_insert(
                $conn,
                'INSERT INTO sales_order_items (order_id, product_id, product_code, product_group, brand, model_caption, description, store_name, unit_name, warranty, quantity, unit_price, discount_amount, line_total, line_number) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ii' . str_repeat('s', 8) . 'dddd' . 'i',
                [$orderId, $a['product_id'], $a['product_code'], $a['product_group'], $a['brand'], $a['model_caption'], $a['description'], $a['store_name'], $a['unit_name'], $a['warranty'], $a['quantity'], $a['unit_price'], $a['discount_amount'], $a['line_total'], $lineNo]
            );
            $auditLines[] = [$newId, 'added', $a['description'], [0.0, 0.0, 0.0, 0.0], [$a['quantity'], $a['unit_price'], $a['discount_amount'], $a['line_total']]];
        }

        $balance = round($net - $advance, 2);
        sales_exec(
            $conn,
            'UPDATE sales_orders SET delivery_date=?, subtotal=?, extra_charges=?, discount_amount=?, vat_ait_amount=?, net_amount=?, advance_received=?, balance_amount=?, total_quantity=? WHERE order_id=?',
            'sddddddddi',
            [$newDelivery, $subtotal, $extra, $discount, $vatAit, $net, $advance, $balance, round($totalQty, 3), $orderId]
        )->close();

        $userId = (int) ($GLOBALS['user_id'] ?? 0);
        $adjNo = sales_next_number($conn, 'SA', (int) substr($adjDate, 0, 4));
        $adjId = sales_insert(
            $conn,
            'INSERT INTO sales_order_adjustments (adjustment_no, order_id, adjustment_date, reason, old_subtotal, new_subtotal, old_net_amount, new_net_amount, old_advance, new_advance, old_delivery_date, new_delivery_date, created_by) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'siss' . str_repeat('d', 6) . 'ssi',
            [$adjNo, $orderId, $adjDate, $reason, (float) $order['subtotal'], $subtotal, (float) $order['net_amount'], $net, (float) $order['advance_received'], $advance, $order['delivery_date'], $newDelivery, $userId]
        );
        foreach ($auditLines as [$itemId, $action, $description, $old, $new]) {
            sales_insert(
                $conn,
                'INSERT INTO sales_order_adjustment_lines (adjustment_id, order_item_id, action, description, old_quantity, new_quantity, old_unit_price, new_unit_price, old_discount, new_discount, old_line_total, new_line_total) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiss' . str_repeat('d', 8),
                [$adjId, $itemId, $action, $description, $old[0], $new[0], $old[1], $new[1], $old[2], $new[2], $old[3], $new[3]]
            );
        }
        $status = sales_order_refresh_status($conn, $orderId);
        $conn->commit();
        $message = 'Adjustment ' . $adjNo . ' saved for order ' . $order['order_no'] . '.';
        if ($status === 'converted') {
            $message .= ' Every line is now fully billed, so the order is marked converted.';
        }
        sales_flash_redirect($self . '?view=list', 'success', $message);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Sales order adjustment failed: ' . $e->getMessage());
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Detail print ---------- */
if (isset($_GET['print']) && $schemaReady) {
    $adj = sales_row($conn, 'SELECT a.*, o.order_no, o.customer_name, o.mobile FROM sales_order_adjustments a JOIN sales_orders o ON o.order_id=a.order_id WHERE a.adjustment_id=?', 'i', [(int) $_GET['print']]);
    if (!$adj) {
        http_response_code(404);
        exit('Adjustment not found.');
    }
    $lines = sales_rows($conn, 'SELECT * FROM sales_order_adjustment_lines WHERE adjustment_id=? ORDER BY line_id', 'i', [(int) $adj['adjustment_id']]);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= sales_h($adj['adjustment_no']) ?></title>
    <style>body{font:14px Arial,sans-serif;margin:28px;color:#222}table{border-collapse:collapse;width:100%;margin-top:14px}th,td{border:1px solid #aaa;padding:6px;text-align:left}th{background:#eee}.num{text-align:right}@media print{button{display:none}}</style></head><body>
    <button onclick="window.print()">Print</button>
    <h1>Sales Order Adjustment <?= sales_h($adj['adjustment_no']) ?></h1>
    <p><b>Order:</b> <?= sales_h($adj['order_no']) ?> &nbsp; <b>Customer:</b> <?= sales_h($adj['customer_name']) ?> <?= sales_h($adj['mobile']) ?><br><b>Date:</b> <?= sales_h($adj['adjustment_date']) ?> &nbsp; <b>Reason:</b> <?= sales_h($adj['reason']) ?></p>
    <table><tr><th></th><th class="num">Before</th><th class="num">After</th></tr>
    <tr><td>Subtotal</td><td class="num"><?= sales_money($adj['old_subtotal']) ?></td><td class="num"><?= sales_money($adj['new_subtotal']) ?></td></tr>
    <tr><td>Net amount</td><td class="num"><?= sales_money($adj['old_net_amount']) ?></td><td class="num"><?= sales_money($adj['new_net_amount']) ?></td></tr>
    <tr><td>Advance</td><td class="num"><?= sales_money($adj['old_advance']) ?></td><td class="num"><?= sales_money($adj['new_advance']) ?></td></tr>
    <tr><td>Delivery date</td><td class="num"><?= sales_h($adj['old_delivery_date'] ?? '') ?></td><td class="num"><?= sales_h($adj['new_delivery_date'] ?? '') ?></td></tr></table>
    <table><thead><tr><th>Action</th><th>Description</th><th class="num">Old Qty</th><th class="num">New Qty</th><th class="num">Old Price</th><th class="num">New Price</th><th class="num">Old Disc.</th><th class="num">New Disc.</th><th class="num">Old Total</th><th class="num">New Total</th></tr></thead><tbody>
    <?php foreach ($lines as $l): ?><tr><td><?= sales_h(ucfirst($l['action'])) ?></td><td><?= sales_h($l['description']) ?></td><td class="num"><?= sales_qty_fmt($l['old_quantity']) ?></td><td class="num"><?= sales_qty_fmt($l['new_quantity']) ?></td><td class="num"><?= sales_money($l['old_unit_price']) ?></td><td class="num"><?= sales_money($l['new_unit_price']) ?></td><td class="num"><?= sales_money($l['old_discount']) ?></td><td class="num"><?= sales_money($l['new_discount']) ?></td><td class="num"><?= sales_money($l['old_line_total']) ?></td><td class="num"><?= sales_money($l['new_line_total']) ?></td></tr><?php endforeach; ?>
    <?php if (!$lines): ?><tr><td colspan="10">Header values only.</td></tr><?php endif; ?>
    </tbody></table></body></html><?php
    exit;
}

$flash = sales_take_flash();
$view = (string) ($_GET['view'] ?? 'form');
$orderNo = trim((string) ($_GET['order'] ?? ''));
$order = null;
$lines = [];
$billed = [];
$openOrders = [];
$adjustments = [];
if ($schemaReady) {
    if ($view === 'list') {
        $q = trim((string) ($_GET['q'] ?? ''));
        $like = '%' . $q . '%';
        $adjustments = sales_list_rows($conn, 'SELECT a.adjustment_id, a.adjustment_no, a.adjustment_date, a.reason, a.old_net_amount, a.new_net_amount, o.order_no, o.customer_name FROM sales_order_adjustments a JOIN sales_orders o ON o.order_id=a.order_id WHERE a.adjustment_no LIKE ? OR o.order_no LIKE ? OR o.customer_name LIKE ? ORDER BY a.adjustment_id DESC', 'sss', [$like, $like, $like]);
    } elseif ($orderNo !== '') {
        $order = sales_row($conn, 'SELECT * FROM sales_orders WHERE order_no=? OR ref_no=? ORDER BY order_id DESC LIMIT 1', 'ss', [$orderNo, $orderNo]);
        if ($order) {
            $lines = sales_rows($conn, 'SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number', 'i', [(int) $order['order_id']]);
            $billed = sales_order_billed_map($conn, (int) $order['order_id']);
        }
    } else {
        $openOrders = sales_rows($conn, "SELECT order_id, order_no, ref_no, order_date, delivery_date, customer_name, mobile, net_amount FROM sales_orders WHERE status='open' ORDER BY order_id DESC LIMIT 200");
    }
}
$products = ($schemaReady && $order) ? sales_products($conn) : [];

sales_page_start('Sales Order Adjustment', 'adjust');
?>
<div class="titlebar"><h1>Sales Order Adjustment</h1><div class="toolbar"><a class="btn" href="<?= $self ?>">Pick Order</a><a class="btn" href="<?= $self ?>?view=list">Adjustment History</a></div></div>
<?= sales_flash_html($flash) ?>
<?php if (!$schemaReady): ?>
<?= sales_schema_missing_html('billing/sales/sales_order_adjustment/schema.sql') ?>
<?php elseif ($view === 'list'): ?>
<form class="list-search" method="get"><input type="hidden" name="view" value="list"><input class="input" name="q" value="<?= sales_h($_GET['q'] ?? '') ?>" placeholder="Adjustment no, order no or customer"><button class="btn primary">Search</button></form>
<div class="table-scroll"><table><thead><tr><th>Adjustment</th><th>Date</th><th>Order</th><th>Customer</th><th>Reason</th><th class="num">Old Net</th><th class="num">New Net</th><th class="num">Difference</th><th></th></tr></thead><tbody>
<?php if (!$adjustments): ?><tr><td colspan="9" class="empty">No adjustments found.</td></tr><?php endif; ?>
<?php foreach ($adjustments as $a): ?><tr><td><?= sales_h($a['adjustment_no']) ?></td><td><?= sales_h($a['adjustment_date']) ?></td><td><?= sales_h($a['order_no']) ?></td><td><?= sales_h($a['customer_name']) ?></td><td><?= sales_h($a['reason']) ?></td><td class="num"><?= sales_money($a['old_net_amount']) ?></td><td class="num"><?= sales_money($a['new_net_amount']) ?></td><td class="num"><?= sales_money((float) $a['new_net_amount'] - (float) $a['old_net_amount']) ?></td><td><a class="btn small" target="_blank" href="<?= $self ?>?print=<?= (int) $a['adjustment_id'] ?>">View</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif (!$order): ?>
<form class="list-search" method="get"><input class="input" name="order" value="<?= sales_h($orderNo) ?>" placeholder="Order no or reference" required><button class="btn primary">Load Order</button></form>
<?php if ($orderNo !== ''): ?><div class="alert error">No order matches "<?= sales_h($orderNo) ?>".</div><?php endif; ?>
<div class="section-title">Open orders</div>
<div class="table-scroll"><table><thead><tr><th>Order</th><th>Reference</th><th>Date</th><th>Delivery</th><th>Customer</th><th>Mobile</th><th class="num">Net</th><th></th></tr></thead><tbody>
<?php if (!$openOrders): ?><tr><td colspan="8" class="empty">No open orders.</td></tr><?php endif; ?>
<?php foreach ($openOrders as $o): ?><tr><td><?= sales_h($o['order_no']) ?></td><td><?= sales_h($o['ref_no']) ?></td><td><?= sales_h($o['order_date']) ?></td><td><?= sales_h($o['delivery_date'] ?? '') ?></td><td><?= sales_h($o['customer_name']) ?></td><td><?= sales_h($o['mobile']) ?></td><td class="num"><?= sales_money($o['net_amount']) ?></td><td><a class="btn small primary" href="<?= $self ?>?order=<?= urlencode($o['order_no']) ?>">Adjust</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($order['status'] !== 'open'): ?>
<div class="alert error">Order <?= sales_h($order['order_no']) ?> is <?= sales_h($order['status']) ?>. Only open orders can be adjusted.</div>
<?php else: ?>
<div class="summary"><div>Order<b><?= sales_h($order['order_no']) ?></b></div><div>Date<b><?= sales_h($order['order_date']) ?></b></div><div>Customer<b><?= sales_h($order['customer_name']) ?></b></div><div>Mobile<b><?= sales_h($order['mobile']) ?></b></div><div>Current Net<b><?= sales_money($order['net_amount']) ?></b></div></div>
<form method="post" id="adj-form" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>"><input type="hidden" name="new_items_json" id="new_items_json" value="[]">
<div class="grid g3">
<div class="field"><label for="adjustment_date">Adjustment Date</label><input id="adjustment_date" name="adjustment_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
<div class="field"><label for="delivery_date">Delivery Date</label><input id="delivery_date" name="delivery_date" type="date" value="<?= sales_h($order['delivery_date'] ?? '') ?>"></div>
<div class="field"><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" required placeholder="Why the order changed"></div>
</div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Description</th><th class="num">Billed</th><th>Quantity</th><th>Unit Price</th><th>Discount</th><th class="num">Amount</th><th>Remove</th></tr></thead><tbody id="lines-body">
<?php foreach ($lines as $i => $l): $id = (int) $l['item_id']; $b = $billed[$id] ?? 0; ?>
<tr data-line><td><?= $i + 1 ?></td><td><?= sales_h(trim($l['product_code'] . ' ' . $l['description'])) ?><br><span class="muted"><?= sales_h($l['model_caption']) ?></span></td><td class="num"><?= sales_qty_fmt($b) ?></td>
<td><input type="number" name="line[<?= $id ?>][quantity]" min="<?= $b > 0 ? sales_h($b) : '0.001' ?>" step="0.001" value="<?= sales_h($l['quantity']) ?>" data-q></td>
<td><input type="number" name="line[<?= $id ?>][unit_price]" min="0" step="0.01" value="<?= sales_h($l['unit_price']) ?>" data-p></td>
<td><input type="number" name="line[<?= $id ?>][discount_amount]" min="0" step="0.01" value="<?= sales_h($l['discount_amount']) ?>" data-d></td>
<td class="num" data-t><?= sales_money($l['line_total']) ?></td>
<td><?php if ($b > 0): ?><span class="muted">Billed</span><?php else: ?><input type="checkbox" name="line[<?= $id ?>][remove]" value="1" data-r style="width:auto;height:auto"><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<section class="section product-form"><div class="section-title">Add New Line</div>
<div class="grid g5" style="margin-top:0">
<div class="field"><label>Product Search</label><div class="relative"><input id="product_search" placeholder="Code, model or name"><div class="suggestions" id="product-suggestions" hidden></div></div></div>
<div class="field"><label>Description</label><input id="n_desc"></div>
<div class="field"><label>Quantity</label><input id="n_qty" type="number" min="0.001" step="0.001" value="1"></div>
<div class="field"><label>Unit Price</label><input id="n_price" type="number" min="0" step="0.01" value="0"></div>
<div class="field"><label>Discount</label><input id="n_disc" type="number" min="0" step="0.01" value="0"></div>
</div>
<div class="toolbar" style="margin-top:8px"><button type="button" class="btn primary" id="add-new">Add line</button></div>
<table style="margin-top:8px"><tbody id="new-body"></tbody></table></section>

<div class="bottom"><div></div><div class="totals">
<div><span>Subtotal</span><strong id="t_sub">0.00</strong></div>
<div><label for="extra_charges">Extra Charges</label><input id="extra_charges" name="extra_charges" type="number" min="0" step="0.01" value="<?= sales_h($order['extra_charges']) ?>"></div>
<div><label for="discount_amount">Less Discount</label><input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="<?= sales_h($order['discount_amount']) ?>"></div>
<div><label for="vat_ait_amount">VAT / AIT</label><input id="vat_ait_amount" name="vat_ait_amount" type="number" min="0" step="0.01" value="<?= sales_h($order['vat_ait_amount']) ?>"></div>
<div class="grand"><span>New Net Amount</span><strong id="t_net">0.00</strong></div>
<div><label for="advance_received">Advance Received</label><input id="advance_received" name="advance_received" type="number" min="0" step="0.01" value="<?= sales_h($order['advance_received']) ?>"></div>
<div class="grand"><span>Balance</span><strong id="t_bal">0.00</strong></div>
<div><span>Change in Net</span><strong id="t_diff">0.00</strong></div>
</div></div>
<div class="toolbar" style="margin-top:12px"><button class="btn primary">Save Adjustment</button><a class="btn" href="<?= $self ?>?order=<?= urlencode($order['order_no']) ?>">Reset</a></div>
</form>
<?php endif; ?>
<?php
$script = $order && $order['status'] === 'open' ? '
const products=' . sales_js($products) . ',oldNet=' . (float) $order['net_amount'] . ';const added=[];
const ps=document.getElementById("product_search");let picked=null;
attachSuggest(ps,document.getElementById("product-suggestions"),products,["description","model_caption","product_code","brand"],productLabel,p=>{picked=p;ps.value=(p.product_code||"")+" "+p.description;document.getElementById("n_desc").value=p.description;document.getElementById("n_price").value=p.default_unit_price;document.getElementById("n_qty").focus()});
ps.addEventListener("input",()=>{picked=null});
function recalc(){let sub=0;document.querySelectorAll("[data-line]").forEach(tr=>{const r=tr.querySelector("[data-r]");const t=Math.max(0,Number(tr.querySelector("[data-q]").value||0)*Number(tr.querySelector("[data-p]").value||0)-Number(tr.querySelector("[data-d]").value||0));tr.querySelector("[data-t]").textContent=money(t);tr.style.opacity=r&&r.checked?.45:1;if(!(r&&r.checked))sub+=t});
 added.forEach(a=>sub+=a.line_total);const net=Math.max(0,sub+num("extra_charges")+num("vat_ait_amount")-num("discount_amount"));
 document.getElementById("t_sub").textContent=money(sub);document.getElementById("t_net").textContent=money(net);document.getElementById("t_bal").textContent=money(Math.max(0,net-num("advance_received")));document.getElementById("t_diff").textContent=money(net-oldNet);
 document.getElementById("new_items_json").value=JSON.stringify(added)}
function renderAdded(){const b=document.getElementById("new-body");b.innerHTML="";added.forEach((a,n)=>b.insertAdjacentHTML("beforeend",`<tr><td>New</td><td>${safe(a.description)}</td><td class=num>${qtyFmt(a.quantity)}</td><td class=num>${money(a.unit_price)}</td><td class=num>${money(a.discount_amount)}</td><td class=num>${money(a.line_total)}</td><td><button type=button class="btn small danger" data-ndel="${n}">Delete</button></td></tr>`));recalc()}
document.getElementById("add-new").addEventListener("click",()=>{const d=document.getElementById("n_desc").value.trim(),q=num("n_qty"),p=num("n_price"),dc=num("n_disc");if(!d||q<=0){alert("Enter a description and a quantity greater than zero.");return}
 const base=picked||{};added.push({product_id:base.product_id||0,product_code:base.product_code||"",product_group:base.product_group||"",brand:base.brand||"",model_caption:base.model_caption||"",warranty:base.warranty||"",unit_name:base.unit_name||"Pcs",description:d,quantity:q,unit_price:p,discount_amount:dc,line_total:Math.max(0,Math.round((q*p-dc)*100)/100)});
 picked=null;ps.value="";document.getElementById("n_desc").value="";document.getElementById("n_qty").value=1;document.getElementById("n_price").value=0;document.getElementById("n_disc").value=0;renderAdded()});
document.addEventListener("click",e=>{const d=e.target.closest("[data-ndel]");if(d){added.splice(+d.dataset.ndel,1);renderAdded()}});
document.getElementById("adj-form").addEventListener("input",recalc);document.getElementById("adj-form").addEventListener("change",recalc);
document.getElementById("adj-form").addEventListener("submit",recalc);recalc();' : '';
sales_page_end($script);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
