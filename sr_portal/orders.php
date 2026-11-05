<?php
/**
 * Book Order: the SR books a sales order for one of their customers. It reaches the office as an open Sales Order
 * waiting for approval (sales_person and sr_id set to the SR). Prices come from the customer's price level and may
 * not go below the product's lower price. Own orders are listed; one still pending with no bill or challan can be cancelled.
 */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
if (!(int) $a['can_book_orders']) {
    cp_redirect('index.php', 'err', 'Your account may not book orders. Ask the office.');
}
const SR_ORDER_ROWS = 10;

/** Reserve the next SO number the way the office Sales Order page does (sales_order_sequences, one row per year). */
function sr_order_no($year)
{
    cp_exec('INSERT INTO sales_order_sequences (sequence_year, last_number) VALUES (?, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1', 'i', [$year])->close();
    $n = (int) cp_row('SELECT last_number FROM sales_order_sequences WHERE sequence_year=? FOR UPDATE', 'i', [$year])['last_number'];
    return 'SO' . $year . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
}

/** An order of this SR that may still be cancelled, or a reason why not. */
function sr_order_locked(array $o)
{
    if ($o['status'] !== 'open' || $o['approval_status'] !== 'pending') {
        return 'The office has already ' . ($o['status'] === 'cancelled' ? 'cancelled' : 'approved') . ' this order.';
    }
    $billed = cp_row('SELECT 1 AS x FROM sales_order_bills WHERE order_id=? LIMIT 1', 'i', [(int) $o['order_id']]);
    $challan = cp_row("SELECT 1 AS x FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id AND d.status='active' JOIN sales_order_items i ON i.item_id=l.source_line_id WHERE l.source_kind='sales_order' AND i.order_id=? LIMIT 1", 'i', [(int) $o['order_id']]);
    return $billed || $challan ? 'This order already has a bill or challan.' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    cp_check_csrf('orders.php');
    if ($action === 'cancel') {
        $o = cp_row('SELECT * FROM sales_orders WHERE order_id=? AND sr_id=?', 'ii', [(int) ($_POST['order_id'] ?? 0), (int) $a['sr_id']]);
        if (!$o) {
            cp_redirect('orders.php', 'err', 'Order not found.');
        }
        $why = sr_order_locked($o);
        if ($why !== '') {
            cp_redirect('orders.php?id=' . (int) $o['order_id'], 'err', $why);
        }
        cp_exec("UPDATE sales_orders SET status='cancelled' WHERE order_id=? AND status='open' AND approval_status='pending'", 'i', [(int) $o['order_id']])->close();
        cp_redirect('orders.php', 'ok', $o['order_no'] . ' cancelled.');
    }

    $cid = (int) ($_POST['customer_id'] ?? 0);
    $back = 'orders.php?new=1&customer=' . $cid;
    $c = sr_customer($a, $cid);
    if (!$c || !(int) $c['is_active']) {
        cp_redirect('orders.php?new=1', 'err', 'Choose one of your customers.');
    }
    if ((int) $c['block_sales']) {
        cp_redirect($back, 'err', 'Sales to ' . $c['customer_name'] . ' are blocked by the office.');
    }
    $delivery = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['delivery_date'] ?? '')) && strtotime($_POST['delivery_date']) ? (string) $_POST['delivery_date'] : null;
    if ($delivery !== null && $delivery < date('Y-m-d')) {
        cp_redirect($back, 'err', 'The delivery date cannot be in the past.');
    }
    $billType = ($_POST['bill_type'] ?? '') === 'Credit' ? 'Credit' : 'Cash';
    if ($billType === 'Credit' && !(int) $c['allow_credit']) {
        cp_redirect($back, 'err', $c['customer_name'] . ' buys for cash only.');
    }

    // Lines: "CODE — caption" from the picker (or just the code), quantity, and an optional rate.
    $wanted = [];
    foreach ((array) ($_POST['product'] ?? []) as $i => $label) {
        $code = trim(explode(' — ', (string) $label)[0]);
        $qty = round((float) ($_POST['qty'][$i] ?? 0), 3);
        if ($code === '' && $qty <= 0) {
            continue;
        }
        if ($code === '' || $qty <= 0) {
            cp_redirect($back, 'err', 'Row ' . ($i + 1) . ' needs both a product and a quantity.');
        }
        $p = cp_row('SELECT product_id FROM sales_products WHERE product_code=? AND is_active=1', 's', [$code]);
        if (!$p) {
            cp_redirect($back, 'err', 'Row ' . ($i + 1) . ': product "' . $code . '" was not found.');
        }
        $wanted[] = ['id' => (int) $p['product_id'], 'qty' => $qty, 'rate' => trim((string) ($_POST['rate'][$i] ?? ''))];
    }
    if (!$wanted || count($wanted) > SR_ORDER_ROWS) {
        cp_redirect($back, 'err', 'Add at least one product.');
    }
    $products = array_column(sr_products('', array_column($wanted, 'id'), $c['price_level'], SR_ORDER_ROWS), null, 'product_id');
    $lines = [];
    $subtotal = $qtyTotal = 0.0;
    foreach ($wanted as $n => $w) {
        $p = $products[$w['id']];
        $rate = $w['rate'] === '' ? $p['price'] : round((float) $w['rate'], 2);
        if ($rate <= 0) {
            cp_redirect($back, 'err', $p['label'] . ' has no sales price. Ask the office.');
        }
        if ((float) $p['lower_price'] > 0 && $rate < (float) $p['lower_price']) {
            cp_redirect($back, 'err', $p['label'] . ': the rate cannot be below ' . cp_money($p['lower_price']) . '.');
        }
        $total = round($rate * $w['qty'], 2);
        $subtotal += $total;
        $qtyTotal += $w['qty'];
        $lines[] = [$w['id'], $p['product_code'], $p['product_group'], $p['brand'], $p['model_caption'], $p['description'] !== '' ? $p['description'] : $p['model_caption'], $p['store'], $p['unit_name'], $p['warranty'], $w['qty'], $rate, $total, $n + 1];
    }
    $subtotal = round($subtotal, 2);
    if ($billType === 'Credit' && (float) $c['credit_limit'] > 0 && (float) $c['balance'] + $subtotal > (float) $c['credit_limit'] + 0.004) {
        cp_redirect($back, 'err', 'This order would take ' . $c['customer_name'] . ' over the credit limit of ' . cp_money($c['credit_limit']) . ' (owing ' . cp_money($c['balance']) . ').');
    }

    $db = $GLOBALS['cp_db'];
    $db->begin_transaction();
    try {
        $orderNo = sr_order_no((int) date('Y'));
        $orderId = cp_insert(
            "INSERT INTO sales_orders (order_no, ref_no, order_date, delivery_date, bill_type, customer_id, customer_group, customer_name, mobile, attention, narration, address, bin, nid,
                sales_person, sr_id, delivery_point, subtotal, net_amount, balance_amount, total_quantity, status, approval_status, portal_account_id)
             VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', 'pending', ?)",
            'ssssi' . 'sssssssss' . 'is' . 'dddd' . 'i',
            [$orderNo, cp_post('ref_no', 80), $delivery, $billType, $cid, $c['customer_group'], $c['customer_name'], $c['mobile'], $c['attention'], cp_post('narration', 1000), (string) $c['address'], $c['bin'], $c['nid'],
                $a['sr_name'], (int) $a['sr_id'], cp_post('delivery_point', 255), $subtotal, $subtotal, $subtotal, round($qtyTotal, 3), (int) $a['account_id']]
        );
        foreach ($lines as $l) {
            cp_insert('INSERT INTO sales_order_items (order_id, product_id, product_code, product_group, brand, model_caption, description, store_name, unit_name, warranty, quantity, unit_price, line_total, line_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'iissssssssdddi', array_merge([$orderId], $l));
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        error_log('SR portal order failed: ' . $e->getMessage());
        cp_redirect($back, 'err', 'The order could not be saved. Please try again.');
    }
    notify_admin('sales/sales_order/sales_order.php', 'Order to approve: ' . $orderNo, 'Booked by ' . $a['sr_name'] . ' in the portal.');
    cp_redirect('orders.php?id=' . $orderId, 'ok', $orderNo . ' sent to the office for approval.');
}

if (isset($_GET['id'])) {
    $o = cp_row('SELECT * FROM sales_orders WHERE order_id=? AND sr_id=?', 'ii', [(int) $_GET['id'], (int) $a['sr_id']]);
    if (!$o) {
        cp_redirect('orders.php', 'err', 'Order not found.');
    }
    $items = cp_rows('SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number, item_id', 'i', [(int) $o['order_id']]);
    sr_page_start('Order ' . $o['order_no'], 'orders');
    $state = $o['status'] === 'open' ? ($o['approval_status'] === 'pending' ? 'Waiting for approval' : 'Approved') : ucfirst($o['status']);
    echo '<div class="card meta"><div><b>Date:</b> ' . cp_date($o['order_date']) . '</div><div><b>Customer:</b> ' . cp_h($o['customer_name']) . '</div><div><b>Bill type:</b> ' . cp_h($o['bill_type']) . '</div>'
        . '<div><b>Delivery:</b> ' . ($o['delivery_date'] ? cp_date($o['delivery_date']) : '—') . ($o['delivery_point'] !== '' ? ', ' . cp_h($o['delivery_point']) : '') . '</div><div><b>Status:</b> ' . cp_h($state) . '</div>'
        . ($o['ref_no'] !== '' ? '<div><b>Customer ref:</b> ' . cp_h($o['ref_no']) . '</div>' : '') . ((string) $o['narration'] !== '' ? '<div><b>Note:</b> ' . cp_h($o['narration']) . '</div>' : '') . '</div>';
    echo '<div class="scroll"><table><thead><tr><th>SL</th><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>';
    foreach ($items as $n => $it) {
        echo '<tr><td>' . ($n + 1) . '</td><td>' . cp_h(trim($it['product_code'] . ' ' . $it['model_caption'] . ' ' . ($it['description'] !== $it['model_caption'] ? $it['description'] : ''))) . '</td><td class="num">' . cp_qty($it['quantity']) . ' ' . cp_h($it['unit_name']) . '</td>'
            . '<td class="num">' . cp_money($it['unit_price']) . '</td><td class="num">' . cp_money($it['line_total']) . '</td></tr>';
    }
    echo '</tbody></table></div><table class="totals"><tr><td><b>Total</b></td><td class="num"><b>' . cp_money($o['net_amount']) . '</b></td></tr></table><p>';
    if (sr_order_locked($o) === '') {
        echo '<form method="post" class="inline">' . cp_csrf_field() . '<input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="' . (int) $o['order_id'] . '"><button class="btn danger" data-confirm="Cancel order ' . cp_h($o['order_no']) . '?">Cancel order</button></form> ';
    }
    echo '<a class="btn light" href="orders.php">My orders</a></p>';
    sr_page_end();
    exit;
}

if (isset($_GET['new'])) {
    $customers = sr_customers($a);
    $c = isset($_GET['customer']) ? sr_customer($a, (int) $_GET['customer']) : null;
    sr_page_start('Book Order', 'orders');
    if (!$customers) {
        echo '<div class="alert err">You have no customers to book for. The office assigns customers in Trader wise SR Fixing.</div>';
        sr_page_end();
        exit;
    }
    if (!$c) {
        echo '<form method="get" class="card row"><input type="hidden" name="new" value="1"><div><label for="customer">Customer</label><select id="customer" name="customer" required><option value="">Choose…</option>';
        foreach ($customers as $x) {
            echo '<option value="' . (int) $x['customer_id'] . '">' . cp_h($x['customer_name'] . ($x['mobile'] !== '' ? ' · ' . $x['mobile'] : '')) . '</option>';
        }
        echo '</select></div><div class="auto"><button class="btn">Next</button></div></form>';
        sr_page_end();
        exit;
    }
    $products = sr_products('', [], $c['price_level'], 3000);
    echo '<div class="card meta"><div><b>Customer:</b> ' . cp_h($c['customer_name']) . '</div><div><b>Price level:</b> ' . cp_h(ucfirst($c['price_level'])) . '</div><div><b>Owing:</b> ' . cp_money($c['balance']) . '</div>'
        . '<div><b>Credit:</b> ' . ((int) $c['allow_credit'] ? ((float) $c['credit_limit'] > 0 ? cp_money(max(0, (float) $c['credit_limit'] - (float) $c['balance'])) . ' left' : 'Allowed') : 'Cash only') . '</div>'
        . '<div><a href="orders.php?new=1">Change customer</a></div></div>';
    if ((int) $c['block_sales']) {
        echo '<div class="alert err">Sales to this customer are blocked by the office.</div>';
        sr_page_end();
        exit;
    }
    echo '<datalist id="products">';
    foreach ($products as $p) {
        echo '<option value="' . cp_h($p['label']) . '">' . cp_h(cp_money($p['price']) . ' · stock ' . cp_qty($p['stock_all'])) . '</option>';
    }
    echo '</datalist><form method="post" class="card grid">' . cp_csrf_field() . '<input type="hidden" name="customer_id" value="' . (int) $c['customer_id'] . '">'
        . '<div class="row"><div><label for="bill_type">Bill type</label><select id="bill_type" name="bill_type"><option>Cash</option>' . ((int) $c['allow_credit'] ? '<option selected>Credit</option>' : '') . '</select></div>'
        . '<div><label for="delivery_date">Delivery date</label><input id="delivery_date" type="date" name="delivery_date" min="' . date('Y-m-d') . '"></div>'
        . '<div><label for="ref_no">Customer ref / PO</label><input id="ref_no" name="ref_no" maxlength="80"></div></div>'
        . '<div class="scroll"><table><thead><tr><th>#</th><th>Product (type code or name)</th><th class="num">Qty</th><th class="num">Rate (blank = list price)</th></tr></thead><tbody>';
    for ($i = 0; $i < SR_ORDER_ROWS; $i++) {
        echo '<tr><td>' . ($i + 1) . '</td><td><input name="product[]" list="products" aria-label="Product ' . ($i + 1) . '"></td><td><input name="qty[]" type="number" min="0" step="any" aria-label="Quantity ' . ($i + 1) . '"></td>'
            . '<td><input name="rate[]" type="number" min="0" step="0.01" aria-label="Rate ' . ($i + 1) . '"></td></tr>';
    }
    echo '</tbody></table></div><div><label for="delivery_point">Delivery point</label><input id="delivery_point" name="delivery_point" maxlength="255" value="' . cp_h(mb_substr((string) $c['address'], 0, 255)) . '"></div>'
        . '<div><label for="narration">Note for the office</label><textarea id="narration" name="narration" maxlength="1000"></textarea></div>'
        . '<div><button class="btn">Send order to office</button></div></form><p class="muted">The office checks stock and approves the order before delivery. Rates below the product\'s lowest allowed price are refused.</p>';
    sr_page_end();
    exit;
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'converted', 'cancelled', 'all'], true) ? (string) $_GET['status'] : 'all';
$where = ['sr_id=?'];
$where[] = ['pending' => "status='open' AND approval_status='pending'", 'approved' => "status='open' AND approval_status='approved'", 'converted' => "status='converted'", 'cancelled' => "status='cancelled'", 'all' => '1=1'][$status];
$rows = cp_rows('SELECT order_id, order_no, order_date, customer_name, net_amount, status, approval_status, portal_account_id FROM sales_orders WHERE ' . implode(' AND ', $where) . ' ORDER BY order_id DESC LIMIT 300', 'i', [(int) $a['sr_id']]);
sr_page_start('My Orders', 'orders');
echo '<p><a class="btn" href="orders.php?new=1">Book a new order</a></p><div class="tabs">';
foreach (['all' => 'All', 'pending' => 'Waiting', 'approved' => 'Approved', 'converted' => 'Billed', 'cancelled' => 'Cancelled'] as $k => $l) {
    echo '<a class="btn small' . ($status === $k ? '' : ' light') . '" href="orders.php?status=' . $k . '">' . $l . '</a>';
}
echo '</div><div class="scroll"><table><thead><tr><th>Date</th><th>Order No</th><th>Customer</th><th class="num">Amount</th><th>Status</th><th>Booked</th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="6" class="empty">No orders.</td></tr>';
foreach ($rows as $o) {
    $state = $o['status'] === 'open' ? ($o['approval_status'] === 'pending' ? ['Waiting', ''] : ['Approved', 'ok']) : ($o['status'] === 'converted' ? ['Billed', 'ok'] : ['Cancelled', 'bad']);
    echo '<tr><td>' . cp_date($o['order_date']) . '</td><td><a href="orders.php?id=' . (int) $o['order_id'] . '">' . cp_h($o['order_no']) . '</a></td><td>' . cp_h($o['customer_name']) . '</td><td class="num">' . cp_money($o['net_amount']) . '</td>'
        . '<td><span class="badge ' . $state[1] . '">' . $state[0] . '</span></td><td>' . ($o['portal_account_id'] ? 'Portal' : 'Office') . '</td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
