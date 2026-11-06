<?php
/**
 * Sales Order: book goods for a customer with delivery date, advance, payment schedule, additional info and attachments.
 * An open order with no bill or challan yet can be edited here (Edit); after that use Sales Order Adjust.
 * Bills are made in Sales Bill From Sales Order, deliveries in Challan (Sales Order Wise).
 * Tables: sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql, inventory/schema.sql, sales/schema_v2.sql.
 */
require_once dirname(__DIR__) . '/sales_doc.php';

$self = 'sales_order.php';
$ready = sdoc_ready($conn) && sales_tables_ready($conn, ['sales_orders', 'sales_order_items', 'sales_order_schedules', 'sales_order_sequences', 'sales_order_bills', 'sales_order_bill_items']);
if (!$ready) {
    sdoc_page_start($conn, 'Sales Order', 'order');
    echo sdoc_missing_html();
    sdoc_page_end();
    exit;
}
$userId = (int) ($GLOBALS['user_id'] ?? 0);

/** Custom Preview layouts for orders. */
const SO_TEMPLATES = [
    1 => ['Order1', 'Sales Order', []],
    2 => ['Order2', 'Without Price', ['price' => false]],
    3 => ['Order3', 'With Payment Schedule', ['schedule' => true]],
    4 => ['Order4', 'Proforma Invoice', ['title' => 'Proforma Invoice']],
];

function so_load(mysqli $db, $id)
{
    $o = sales_row($db, 'SELECT * FROM sales_orders WHERE order_id=?', 'i', [(int) $id]);
    if (!$o) {
        return null;
    }
    return [
        'order' => $o,
        'items' => sales_rows($db, 'SELECT * FROM sales_order_items WHERE order_id=? ORDER BY line_number, item_id', 'i', [(int) $id]),
        'schedules' => sales_rows($db, 'SELECT * FROM sales_order_schedules WHERE order_id=? ORDER BY payment_date, schedule_id', 'i', [(int) $id]),
        'bills' => sales_rows($db, 'SELECT b.bill_id, b.invoice_id, v.invoice_no, v.sales_date, v.net_amount, v.status FROM sales_order_bills b JOIN sales_invoices v ON v.invoice_id=b.invoice_id WHERE b.order_id=? ORDER BY b.bill_id', 'i', [(int) $id]),
    ];
}

/** Why an order's lines can no longer be edited here ('' when they can). */
function so_line_lock(mysqli $db, array $o)
{
    if ($o['status'] !== 'open') {
        return $o['order_no'] . ' is ' . $o['status'] . '.';
    }
    if (($o['approval_status'] ?? '') === 'approved') {
        return $o['order_no'] . ' is approved. Use Sales Order Adjust to change it.';
    }
    if (sales_row($db, 'SELECT 1 FROM sales_order_bills WHERE order_id=? LIMIT 1', 'i', [(int) $o['order_id']])) {
        return $o['order_no'] . ' already has a bill. Use Sales Order Adjust to change it.';
    }
    if (sales_row($db, "SELECT 1 FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id AND d.status='active' JOIN sales_order_items i ON i.item_id=l.source_line_id WHERE l.source_kind='sales_order' AND i.order_id=? LIMIT 1", 'i', [(int) $o['order_id']])) {
        return $o['order_no'] . ' already has a challan. Use Sales Order Adjust to change it.';
    }
    return '';
}

function so_header_post()
{
    return [
        'ref_no' => sales_post_string('ref_no', 80), 'customer_group' => sales_post_string('customer_group', 120), 'customer_name' => sales_post_string('customer_name', 190),
        'mobile' => sales_post_string('mobile', 40), 'attention' => sales_post_string('attention', 190), 'narration' => sales_post_string('narration', 2000),
        'address' => sales_post_string('address', 2000), 'bin' => sales_post_string('bin', 60), 'nid' => sales_post_string('nid', 60),
        'sales_person' => sales_post_string('sales_person', 140), 'delivery_point' => sales_post_string('delivery_point', 255),
        'create_installation' => !empty($_POST['create_installation']) ? 1 : 0, 'additional_info' => sdoc_additional_from_post(),
    ];
}

function so_bind(array $values)
{
    return implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $values));
}

/* ---------- Requests that are not page loads ---------- */

sdoc_attachment_request($conn, 'order', $self);

if (isset($_GET['info'])) {
    sales_json(sdoc_product_info($conn, (int) $_GET['info'], trim((string) ($_GET['store'] ?? '')), (int) ($_GET['customer'] ?? 0)));
}
foreach (['search' => 'order_no', 'ref' => 'ref_no', 'mobile' => 'mobile'] as $param => $col) {
    if (isset($_GET[$param]) && trim((string) $_GET[$param]) !== '') {
        $found = sales_row($conn, "SELECT order_id FROM sales_orders WHERE $col=? ORDER BY order_id DESC LIMIT 1", 's', [trim((string) $_GET[$param])]);
        if ($found) {
            header('Location: ' . $self . '?id=' . (int) $found['order_id'], true, 303);
            exit;
        }
        sales_flash_redirect($self, 'error', 'No order matches "' . trim((string) $_GET[$param]) . '".');
    }
}

if (isset($_GET['print']) || isset($_GET['receipt'])) {
    $d = so_load($conn, (int) ($_GET['print'] ?? $_GET['receipt']));
    if (!$d) {
        http_response_code(404);
        exit('Order not found.');
    }
    $o = $d['order'];
    if (isset($_GET['receipt'])) {
        $rows = (float) $o['advance_received'] > 0 ? [[$o['order_date'], 'AR-' . $o['order_no'], 'cash', 'Advance', $o['advance_received']]] : [];
        sdoc_receipt_print($conn, 'Advance Receipt ' . $o['order_no'], $o + ['doc_no' => $o['order_no']], $rows, 'Against Order');
        exit;
    }
    $opt = (SO_TEMPLATES[(int) ($_GET['tpl'] ?? 1)] ?? SO_TEMPLATES[1])[2] + ['price' => true, 'schedule' => false, 'title' => 'Sales Order'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($o['order_no']) . '</title><style>' . INV_PRINT_CSS . '</style></head><body' . (!empty($_GET['auto']) ? ' onload="window.print()"' : '') . '>';
    echo '<div class="bar"><button onclick="window.print()">Print</button></div>' . inv_print_head($conn, $opt['title']);
    $meta = ['Order No' => $o['order_no'], 'Order Date' => inv_fmt_date($o['order_date']), 'Delivery Date' => inv_fmt_date($o['delivery_date']), 'Bill Type' => $o['bill_type'], 'Ref No' => $o['ref_no'],
        'Customer' => $o['customer_name'] . ($o['customer_group'] ? ' (' . $o['customer_group'] . ')' : ''), 'Mobile' => $o['mobile'], 'Address' => $o['address'], 'Attention' => $o['attention'],
        'Sales Person' => $o['sales_person'], 'Delivery Point' => $o['delivery_point'], 'BIN' => $o['bin'], 'NID' => $o['nid'], 'Status' => ucfirst($o['status'])];
    foreach (json_decode((string) ($o['additional_info'] ?? ''), true) ?: [] as $k => $v) {
        $meta[SDOC_ADDITIONAL[$k] ?? $k] = $v;
    }
    echo '<div class="meta">';
    foreach ($meta as $k => $v) {
        if ((string) $v !== '') {
            echo '<div><b>' . sales_h($k) . ':</b> ' . sales_h($v) . '</div>';
        }
    }
    echo '</div><table><thead><tr><th>SL</th><th>Model No/Caption</th><th>Description</th><th>Store</th><th>Warranty</th><th class="num">Qty</th><th>Unit</th>' . ($opt['price'] ? '<th class="num">Unit Price</th><th class="num">Discount</th><th class="num">Amount</th>' : '') . '</tr></thead><tbody>';
    foreach ($d['items'] as $i => $it) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h(trim($it['product_code'] . ' ' . $it['model_caption'])) . '</td><td>' . sales_h($it['description']) . '</td><td>' . sales_h($it['store_name']) . '</td><td>' . sales_h($it['warranty']) . '</td><td class="num">' . sales_qty_fmt($it['quantity']) . '</td><td>' . sales_h($it['unit_name']) . '</td>'
            . ($opt['price'] ? '<td class="num">' . sales_money($it['unit_price']) . '</td><td class="num">' . sales_money($it['discount_amount']) . '</td><td class="num">' . sales_money($it['line_total']) . '</td>' : '') . '</tr>';
    }
    echo '</tbody></table>';
    if ($opt['price']) {
        echo '<table class="totals">';
        foreach (['subtotal' => 'Total Amount', 'extra_charges' => 'Add Extra Charges', 'discount_amount' => 'Less Discount', 'vat_ait_amount' => 'Add VAT & AIT', 'net_amount' => 'Net Payable Amount', 'advance_received' => 'Advance Received', 'balance_amount' => 'Balance'] as $k => $l) {
            echo '<tr><td>' . $l . '</td><td class="num">' . sales_money($o[$k]) . '</td></tr>';
        }
        echo '<tr><td>Total Quantity</td><td class="num">' . sales_qty_fmt($o['total_quantity']) . '</td></tr></table>';
    }
    if ($opt['schedule'] || $d['schedules']) {
        echo '<h3>Payment Schedule</h3><table style="width:auto"><thead><tr><th>Payment Day</th><th>Payment Date</th></tr></thead><tbody>';
        foreach ($d['schedules'] as $s) {
            echo '<tr><td>' . sales_h($s['payment_day']) . '</td><td>' . inv_fmt_date($s['payment_date']) . '</td></tr>';
        }
        echo ($d['schedules'] ? '' : '<tr><td colspan="2" class="muted">No schedule.</td></tr>') . '</tbody></table>';
    }
    echo ($o['narration'] ? '<p><b>Narration:</b> ' . nl2br(sales_h($o['narration'])) . '</p>' : '') . '<div class="sign"><div>Customer</div><div>Prepared By</div><div>Checked By</div><div>Authorised By</div></div></body></html>';
    exit;
}

/* ---------- Saving ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $back = $self . ($orderId ? '?id=' . $orderId : '');
    sales_require_csrf($back);
    try {
        $conn->begin_transaction();
        $old = $orderId > 0 ? sales_row($conn, 'SELECT * FROM sales_orders WHERE order_id=? FOR UPDATE', 'i', [$orderId]) : null;
        if ($orderId > 0 && !$old) {
            throw new RuntimeException('Order not found.');
        }
        switch ($action) {
            case 'edit_details':
                if (!$old || $old['status'] === 'cancelled') {
                    throw new RuntimeException('Open an order that is not cancelled.');
                }
                $h = so_header_post();
                if ($h['customer_name'] === '') {
                    throw new RuntimeException('Enter the customer name.');
                }
                $h['customer_id'] = ((int) ($_POST['customer_id'] ?? 0)) ?: null;
                $delivery = sales_valid_date($_POST['delivery_date'] ?? '', true);
                if ($delivery === null) {
                    throw new RuntimeException('Enter a valid delivery date or leave it blank.');
                }
                $h['delivery_date'] = $delivery ?: null;
                sales_exec($conn, 'UPDATE sales_orders SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($h))) . ' WHERE order_id=?', so_bind($h) . 'i', array_merge(array_values($h), [$orderId]))->close();
                sdoc_link_sr($conn, 'sales_orders', 'order_id', $orderId);
                $done = 'Details of ' . $old['order_no'] . ' saved.';
                break;

            case 'cancel':
                if (!$old) {
                    throw new RuntimeException('Open an order first.');
                }
                if ($lock = so_line_lock($conn, $old)) {
                    throw new RuntimeException($lock);
                }
                sales_exec($conn, "UPDATE sales_orders SET status='cancelled' WHERE order_id=?", 'i', [$orderId])->close();
                $done = $old['order_no'] . ' cancelled.';
                break;

            case 'save':
                if ($old && ($lock = so_line_lock($conn, $old))) {
                    throw new RuntimeException($lock);
                }
                $date = sales_valid_date($_POST['order_date'] ?? '');
                $delivery = sales_valid_date($_POST['delivery_date'] ?? '', true);
                if ($date === null || $delivery === null) {
                    throw new RuntimeException('Enter a valid order date and delivery date.');
                }
                $h = so_header_post();
                if ($h['customer_name'] === '') {
                    throw new RuntimeException('Enter or select a customer.');
                }
                [$lines, $subtotal, $qtyTotal] = sdoc_clean_lines($_POST['items_json'] ?? '', inv_stores($conn), false);
                $extra = sales_amount($_POST['extra_charges'] ?? 0);
                $discount = sales_amount($_POST['discount_amount'] ?? 0);
                $vat = sales_amount($_POST['vat_amount'] ?? 0);
                $net = round($subtotal + $extra + $vat - $discount, 2);
                if ($net < 0) {
                    throw new RuntimeException('The discount is more than the order value.');
                }
                $advance = sales_amount($_POST['advance_received'] ?? 0);
                if ($advance > $net + 0.004) {
                    throw new RuntimeException('Advance received cannot be more than the net payable amount.');
                }
                $schedules = [];
                foreach (json_decode((string) ($_POST['schedules_json'] ?? '[]'), true) ?: [] as $s) {
                    $sd = sales_valid_date($s['due_date'] ?? '');
                    if ($sd === null) {
                        throw new RuntimeException('Each payment schedule row needs a valid date.');
                    }
                    $schedules[] = [sales_str($s['note'] ?? '', 40), $sd];
                }
                $cols = $h + ['order_date' => $date, 'delivery_date' => $delivery ?: null, 'bill_type' => ($_POST['bill_type'] ?? '') === 'Cash' ? 'Cash' : 'Credit',
                    'customer_id' => ((int) ($_POST['customer_id'] ?? 0)) ?: null, 'subtotal' => $subtotal, 'extra_charges' => $extra, 'discount_amount' => $discount, 'vat_ait_amount' => $vat,
                    'net_amount' => $net, 'advance_received' => $advance, 'balance_amount' => round($net - $advance, 2), 'total_quantity' => $qtyTotal];
                if ($old) {
                    sales_exec($conn, 'UPDATE sales_orders SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE order_id=?', so_bind($cols) . 'i', array_merge(array_values($cols), [$orderId]))->close();
                    sales_exec($conn, 'DELETE FROM sales_order_items WHERE order_id=?', 'i', [$orderId])->close();
                    sales_exec($conn, 'DELETE FROM sales_order_schedules WHERE order_id=?', 'i', [$orderId])->close();
                    $orderNo = $old['order_no'];
                } else {
                    $orderNo = sales_next_number($conn, 'SO', $date, 'sales_order_sequences');
                    $cols += ['order_no' => $orderNo, 'branch_id' => inv_current_branch($conn), 'created_by' => $userId];
                    $orderId = sales_insert($conn, 'INSERT INTO sales_orders (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', so_bind($cols), array_values($cols));
                }
                foreach ($lines as $n => $l) {
                    $row = ['order_id' => $orderId, 'line_number' => $n + 1] + array_diff_key($l, ['serials' => 1, 'free_quantity' => 1, 'product_narration' => 1]);
                    sales_insert($conn, 'INSERT INTO sales_order_items (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', so_bind($row), array_values($row));
                }
                foreach ($schedules as [$day, $sd]) {
                    sales_insert($conn, 'INSERT INTO sales_order_schedules (order_id, payment_day, payment_date) VALUES (?, ?, ?)', 'iss', [$orderId, $day, $sd]);
                }
                sdoc_link_sr($conn, 'sales_orders', 'order_id', $orderId);
                if (!$old) {
                    notify_approval_needed('sales_order', 'Sales Order', $orderNo, $cols['customer_name'] ?? '');
                }
                $done = $orderNo . ' saved.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
        $conn->commit();
        sales_flash_redirect($self . '?id=' . $orderId, 'success', $done);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Sales order ' . $action . ' failed: ' . $e->getMessage());
        if ($action === 'save') {
            $_SESSION['so_repost'] = $_POST;
        }
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Page ---------- */

$flash = sales_take_flash();
$repost = ($flash['kind'] ?? '') === 'error' ? ($_SESSION['so_repost'] ?? null) : null;
unset($_SESSION['so_repost']);
sdoc_page_start($conn, 'Sales Order', 'order');
echo sales_flash_html($flash);

if (($_GET['view'] ?? '') === 'list') {
    $from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01');
    $to = sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d');
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $rows = sales_list_rows($conn, 'SELECT * FROM sales_orders WHERE order_date BETWEEN ? AND ? AND (order_no LIKE ? OR ref_no LIKE ? OR customer_name LIKE ? OR mobile LIKE ?)' . perm_branch_sql('branch_id') . ' ORDER BY order_date DESC, order_id DESC', 'ssssss', [$from, $to, $like, $like, $like, $like]);
    echo '<form method="get" class="list-search"><input type="hidden" name="view" value="list"><input class="input" type="date" name="from" value="' . sales_h($from) . '" style="max-width:160px"><input class="input" type="date" name="to" value="' . sales_h($to) . '" style="max-width:160px">'
        . '<input class="input" name="q" value="' . sales_h($q) . '" placeholder="Order, ref, customer or mobile"><button class="btn primary">Search</button><a class="btn" href="' . $self . '">New</a></form>';
    echo '<div class="table-scroll" style="max-height:640px"><table><thead><tr><th>SL</th><th>Order No</th><th>Date</th><th>Delivery</th><th>Customer</th><th>Mobile</th><th class="num">Net</th><th class="num">Advance</th><th>Status</th><th>Select</th><th>Bill</th><th>Print</th></tr></thead><tbody>';
    echo $rows ? '' : '<tr><td colspan="12" class="empty">Nothing in this period.</td></tr>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['order_no']) . '</td><td>' . inv_fmt_date($r['order_date']) . '</td><td>' . inv_fmt_date($r['delivery_date']) . '</td><td>' . sales_h($r['customer_name']) . '</td><td>' . sales_h($r['mobile']) . '</td>'
            . '<td class="num">' . sales_money($r['net_amount']) . '</td><td class="num">' . sales_money($r['advance_received']) . '</td><td><span class="badge ' . sales_h($r['status']) . '">' . sales_h(ucfirst($r['status'])) . '</span></td>'
            . '<td><a class="btn small" href="' . $self . '?id=' . (int) $r['order_id'] . '">Select</a></td><td>' . ($r['status'] === 'open' ? '<a class="btn small" href="../sal_bill_from_sal_order/sal_bill_from_sal_order.php?order=' . urlencode($r['order_no']) . '">Make Bill</a>' : '') . '</td>'
            . '<td><a class="btn small" target="_blank" href="' . $self . '?print=' . (int) $r['order_id'] . '">Print</a></td></tr>';
    }
    echo '</tbody></table></div>';
    sdoc_page_end();
    exit;
}

$d = isset($_GET['id']) ? so_load($conn, (int) $_GET['id']) : null;
$o = $d['order'] ?? null;
$lock = $o ? so_line_lock($conn, $o) : '';
$h = $repost ?: ($o ?: ['order_date' => date('Y-m-d'), 'delivery_date' => date('Y-m-d'), 'bill_type' => 'Credit', 'sales_person' => $GLOBALS['user_name'] ?? '']);
$val = fn($k, $def = '') => sales_h($h[$k] ?? $def);
$id = (int) ($o['order_id'] ?? 0);
$prevId = sales_row($conn, 'SELECT order_id FROM sales_orders WHERE order_id<? ORDER BY order_id DESC LIMIT 1', 'i', [$o ? $id : PHP_INT_MAX]);
$nextId = $o ? sales_row($conn, 'SELECT order_id FROM sales_orders WHERE order_id>? ORDER BY order_id LIMIT 1', 'i', [$id]) : null;
$nav = fn($r) => $r ? 'href="' . $self . '?id=' . (int) $r['order_id'] . '"' : 'aria-disabled="true" style="opacity:.4"';
$stores = inv_stores($conn);
$defaultStore = inv_setting($conn, 'default_store', '');
$items = $repost ? (json_decode((string) ($repost['items_json'] ?? '[]'), true) ?: []) : ($d['items'] ?? []);
?>
<form method="post" id="docform" class="si" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="action" id="action" value="save"><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="customer_id" id="customer_id" value="<?= $val('customer_id') ?>">
<input type="hidden" name="items_json" id="items_json"><input type="hidden" name="schedules_json" id="schedules_json">
<header class="si-hero">
  <div class="si-hero-main">
    <span class="si-hero-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.4 11h11L21 8H6.2M9 19h.01M17 19h.01"/></svg></span>
    <div>
      <h2>Sales Order</h2>
      <?php $soState = !$o ? ['New', 'new'] : ($o['status'] === 'cancelled' ? ['Cancelled', 'void'] : ($o['status'] === 'converted' ? ['Billed', 'ok'] : ((($o['approval_status'] ?? '') === 'approved') ? ['Open · Approved', 'ok'] : ['Open · Pending approval', 'wait']))); ?>
      <p class="si-meta"><b><?= sales_h($o['order_no'] ?? sales_peek_number($conn, 'SO', 'sales_order_sequences')) ?></b><span class="si-status si-<?= $soState[1] ?>"><?= $soState[0] ?></span>
        <span><?= sales_h(inv_branch_name($conn, $o['branch_id'] ?? inv_current_branch($conn))) ?></span>
        <?php if ($o): ?><span><?= sales_h(date('d M Y', strtotime($o['order_date']))) ?></span><?php endif; ?>
        <?= $o && $lock ? '<span class="si-lock">' . sales_h($lock) . ' <a href="../sales_order_adjustment/sales_order_adjustment.php?order=' . urlencode($o['order_no']) . '">Open Sales Order Adjust</a></span>' : '' ?>
        <?= $o && $o['status'] === 'open' ? '<span><a href="../sal_bill_from_sal_order/sal_bill_from_sal_order.php?order=' . urlencode($o['order_no']) . '">Make Bill</a></span>' : '' ?></p>
    </div>
  </div>
  <div class="si-hero-tools">
    <div class="sd-group" role="group" aria-label="Record"><a class="btn" href="<?= $self ?>">New</a><a class="btn" href="<?= $self ?>?view=list">List View</a>
      <a class="btn round" title="Previous" <?= $nav($prevId) ?>>&#9664;</a><button type="button" class="btn round" id="hold" title="Hold this unsaved order, or bring back the held one">&#10074;&#10074;</button><a class="btn round" title="Next" <?= $nav($nextId) ?>>&#9654;</a></div>
  </div>
</header>

<div class="si-layout">
<div class="si-main">
<section class="sd-card" data-step="1">
  <header class="sd-head"><span class="sd-num"><span>1</span></span><div><h3>Customer</h3><p>Search by mobile number, or type the name and pick from the list. A saved customer fills the rest.</p></div></header>
  <div class="sgrid sd-grid">
    <div class="sd-f"><label for="mobile">Mobile No</label><div class="inline"><input id="mobile" name="mobile" maxlength="40" inputmode="tel" value="<?= $val('mobile') ?>" placeholder="Mobile Number"><button type="button" class="btn small" id="mobile-search">Search</button></div></div>
    <div class="sd-f s2"><label for="customer_name">Customer Name</label><div class="relative"><input id="customer_name" name="customer_name" maxlength="190" value="<?= $val('customer_name') ?>"><div class="suggestions" id="customer-suggestions" hidden></div></div></div>
    <div class="sd-f"><label for="customer_group">Customer Group</label><input id="customer_group" name="customer_group" maxlength="120" value="<?= $val('customer_group') ?>"></div>
    <div class="sd-f"><label for="attention">Attention</label><input id="attention" name="attention" maxlength="190" value="<?= $val('attention') ?>"></div>
    <div class="sd-f"><label for="sales_person">Sales Person</label><input id="sales_person" name="sales_person" maxlength="140" value="<?= $val('sales_person') ?>" placeholder="Sales Person" list="dl-sr"><datalist id="dl-sr"><?php foreach (sdoc_sr_names($conn) as $n): ?><option value="<?= sales_h($n) ?>"><?php endforeach; ?></datalist></div>
    <div class="sd-f s2"><label for="address">Address</label><input id="address" name="address" maxlength="2000" value="<?= $val('address') ?>"></div>
    <div class="sd-f"><label for="delivery_point">Delivery Point</label><input id="delivery_point" name="delivery_point" maxlength="255" value="<?= $val('delivery_point') ?>" placeholder="Delivery Point"></div>
    <div class="sd-f"><label for="bin">BIN</label><input id="bin" name="bin" maxlength="60" value="<?= $val('bin') ?>"></div>
    <div class="sd-f"><label for="nid">NID</label><input id="nid" name="nid" maxlength="60" value="<?= $val('nid') ?>"></div>
  </div>
</section>
<section class="sd-card" data-step="2">
  <header class="sd-head"><span class="sd-num"><span>2</span></span><div><h3>Order details</h3><p>The number fills itself. Set the order date and the date the customer expects delivery.</p></div></header>
  <div class="sgrid sd-grid si-details">
    <div class="sd-f"><label for="doc_no">Order No</label><div class="inline"><input id="doc_no" value="<?= sales_h($o['order_no'] ?? sales_peek_number($conn, 'SO', 'sales_order_sequences')) ?>"><button type="button" class="btn small" data-find="search">Find</button></div></div>
    <div class="sd-f half"><label for="order_date">Order Date</label><input id="order_date" type="date" name="order_date" value="<?= $val('order_date') ?>" required></div>
    <div class="sd-f half"><label for="delivery_date">Delivery Date</label><input id="delivery_date" type="date" name="delivery_date" value="<?= $val('delivery_date') ?>"></div>
    <div class="sd-f half"><label for="bill_type">Bill Type</label><select id="bill_type" name="bill_type"><?php foreach (['Credit', 'Cash'] as $opt): ?><option<?= ($h['bill_type'] ?? '') === $opt ? ' selected' : '' ?>><?= $opt ?></option><?php endforeach; ?></select></div>
    <div class="sd-f"><label for="ref_no">Ref No</label><div class="inline"><input id="ref_no" name="ref_no" maxlength="80" value="<?= $val('ref_no') ?>"><button type="button" class="btn small" data-find="ref">Find</button></div></div>
    <div class="sd-f"><label for="narration">Narration</label><input id="narration" name="narration" maxlength="2000" value="<?= $val('narration') ?>"></div>
  </div>
</section>

<section class="sd-card entry" data-step="3">
  <header class="sd-head"><span class="sd-num"><span>3</span></span><div><h3>Add products</h3><p>Search the model, pick the store, enter quantity and price, then press Add. Repeat for every product.</p></div></header>
  <div class="sd-grid sd-line">
    <div class="sd-f s3"><label for="e_model">Model No/Caption</label><div class="relative"><input id="e_model" placeholder="Search model, code or name"><div class="suggestions" id="product-suggestions" hidden></div></div></div>
    <div class="sd-f s3"><label for="e_store">Store</label><select id="e_store"><option value="">Pick store</option><?php foreach ($stores as $s): ?><option<?= $s === $defaultStore ? ' selected' : '' ?>><?= sales_h($s) ?></option><?php endforeach; ?></select></div>
    <div class="sd-f s2"><label for="e_qty">Quantity</label><input id="e_qty" type="number" min="0" step="0.001"></div>
    <div class="sd-f s2"><label for="e_price">Unit Price</label><input id="e_price" type="number" min="0" step="0.01"></div>
    <div class="sd-f"><label for="e_disc">Discount</label><input id="e_disc" type="number" min="0" step="0.01" value="0"></div>
    <div class="sd-f sd-add"><button type="button" class="btn primary" id="add-line">Add</button></div>
  </div>
  <div class="sd-chips" aria-live="polite">
    <span>In stock <b id="i_qty">0.00</b></span><span>Rest of qty <b id="i_rest">0.00</b></span><span class="sd-adv">Advance received <b id="i_advance"><?= sales_money($h['advance_received'] ?? 0) ?></b></span><span id="i_cost"></span><span id="i_prices"></span>
  </div>
  <details class="sd-more">
    <summary>Product details <small>(filled from the product; change only if needed)</small></summary>
    <div class="sd-grid sd-details">
      <div class="sd-f"><label for="e_group">Product Group</label><input id="e_group"></div>
      <div class="sd-f"><label for="e_brand">Brand</label><input id="e_brand"></div>
      <div class="sd-f s2"><label for="e_desc">Description</label><input id="e_desc"></div>
      <div class="sd-f"><label for="e_unit">Unit Name</label><input id="e_unit" value="Pcs"></div>
      <div class="sd-f"><label for="e_warranty">Warranty</label><input id="e_warranty"></div>
    </div>
  </details>
</section>

<section class="sd-card">
  <header class="sd-head sd-head-bar"><div><h3>Items <span class="si-count" id="si_count_head"></span></h3><p><?= $o ? 'Lines of ' . sales_h($o['order_no']) . '.' : 'Products you add appear here.' ?></p></div>
    <div class="sd-tools">
      <div class="sd-group" role="group" aria-label="Change"><button type="button" class="btn" data-mode="edit"<?= $o ? '' : ' disabled' ?>>Edit</button><button type="button" class="btn" data-mode="details"<?= $o ? '' : ' disabled' ?>>Edit Details</button></div>
      <div class="sd-group" role="group" aria-label="Print"><?php if ($o): ?><a class="btn" target="_blank" href="<?= $self ?>?print=<?= $id ?>">Preview</a><a class="btn" target="_blank" href="<?= $self ?>?print=<?= $id ?>&auto=1">Print</a><?php else: ?><button type="button" class="btn" disabled>Preview</button><button type="button" class="btn" disabled>Print</button><?php endif; ?>
        <button type="button" class="btn" data-toggle="bills"<?= $o ? '' : ' disabled' ?>>Bill Preview</button><button type="button" class="btn" data-toggle="bills" data-print="1"<?= $o ? '' : ' disabled' ?>>Bill Print</button>
        <button type="button" class="btn" data-toggle="custom-preview"<?= $o ? '' : ' disabled' ?>>Custom Preview</button>
        <?php if ($o): ?><a class="btn" target="_blank" href="<?= $self ?>?receipt=<?= $id ?>">Receipt</a><?php else: ?><button type="button" class="btn" disabled>Receipt</button><?php endif; ?></div>
      <div class="sd-group" role="group" aria-label="More"><button type="button" class="btn" data-toggle="additional-info">Additional Info</button><button type="button" class="btn" data-focus="t_discount">VAT &amp; Discount</button><button type="button" class="btn" data-focus="t_extra">Additional Extra Charges</button>
        <button type="button" class="btn" id="show-cost">Show Cost</button><button type="button" class="btn" id="show-price">Show Price</button><button type="button" class="btn" data-toggle="attached"<?= $o ? '' : ' disabled' ?>>Attached</button></div>
    </div>
  </header>
<details class="section" id="custom-preview" hidden open><summary>Custom Preview Hide</summary><div class="toolbar"><?php foreach (SO_TEMPLATES as $n => [$label, $title]): ?><a class="btn" target="_blank" title="<?= sales_h($title) ?>" href="<?= $self ?>?print=<?= $id ?>&tpl=<?= $n ?>"><?= $label ?></a><?php endforeach; ?></div></details>
<section class="section" id="bills" hidden><div class="section-title">Bills made from this order</div><table><thead><tr><th>Invoice No</th><th>Date</th><th class="num">Net</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($d['bills'] ?? [] as $b): ?><tr><td><?= sales_h($b['invoice_no']) ?></td><td><?= inv_fmt_date($b['sales_date']) ?></td><td class="num"><?= sales_money($b['net_amount']) ?></td><td><?= sales_h(ucfirst($b['status'])) ?></td>
<td><a class="btn small" target="_blank" data-bill href="../sales_invoice/sales_invoice.php?print=<?= (int) $b['invoice_id'] ?>">Preview</a> <a class="btn small" target="_blank" href="../sales_invoice/sales_invoice.php?print=<?= (int) $b['invoice_id'] ?>&auto=1">Print</a></td></tr><?php endforeach; ?>
<?= empty($d['bills']) ? '<tr><td colspan="5" class="empty">No bill yet.</td></tr>' : '' ?></tbody></table></section>
<?= sdoc_additional_html($h['additional_info'] ?? '') ?>
  <div class="table-scroll grid-box"><table><thead><tr id="grid-head"></tr></thead><tbody id="grid-body"></tbody></table></div>
</section>
</div>

<aside class="si-side">
  <section class="si-sum" aria-label="Summary">
    <div class="si-sum-top"><span>Net payable</span><b><small><?= sales_h(bs_currency_symbol()) ?></small> <span id="si_net">0.00</span></b><p><span id="si_count">0</span> item(s) &middot; <span id="si_qty">0</span> qty<span id="si_change_wrap" hidden> &middot; change <span id="si_change">0.00</span></span></p></div>
    <table class="stotals" id="totals"></table>
  </section>
  <section class="sd-card si-pay" data-step="4">
    <header class="sd-head"><span class="sd-num"><span>4</span></span><div><h3>Advance and payment days</h3><p>Advance goes in the summary above. Add the days the customer will pay.</p></div></header>
    <div class="sd-grid si-paygrid">
      <div class="sd-f"><label for="pay_note">Payment Day</label><input id="pay_note" placeholder="e.g. On delivery"></div>
      <div class="sd-f"><label for="pay_date">Payment Date</label><input id="pay_date" type="date"></div>
      <div class="sd-f sd-end"><button type="button" class="btn" id="date-add">Add Payment Date</button></div>
      <label class="sd-f sd-check"><input class="check" type="checkbox" name="create_installation" value="1"<?= !empty($h['create_installation']) ? ' checked' : '' ?>> Create Installation</label>
    </div>
    <table class="mini"><thead><tr><th>Payment Day</th><th>Payment Date</th><th></th></tr></thead><tbody id="s_body"></tbody></table>
  </section>
  <div class="si-actions" data-step="5">
    <button class="btn primary si-save" id="save-btn" type="submit">Save</button>
    <div class="si-actions-row"><a class="btn" href="javascript:history.back()">Close</a>
    <?php if ($o && !$lock): ?><button class="btn danger" name="action" value="cancel" formnovalidate onclick="return confirm('Cancel <?= sales_h($o['order_no']) ?>?')">Cancel Order</button><?php endif; ?></div>
    <p class="si-hint">Save books the order; stock leaves the store when it is billed.</p>
  </div>
</aside>
</div>
<input type="hidden" name="extra_charges" id="h_extra" value="<?= $val('extra_charges', '0') ?>"><input type="hidden" name="discount_amount" id="h_discount" value="<?= $val('discount_amount', '0') ?>"><input type="hidden" name="vat_amount" id="h_vat" value="<?= $val('vat_ait_amount', $repost['vat_amount'] ?? '0') ?>"><input type="hidden" name="advance_received" id="h_advance" value="<?= $val('advance_received', '0') ?>">
</form>
<?php
if ($o) {
    sdoc_attachment_panel($conn, 'order', $id, $self, $o['status'] !== 'cancelled');
}
sdoc_page_end([
    'kind' => 'order', 'ui' => '../../doc_ui.js', 'self' => $self, 'docId' => $id, 'saved' => (bool) $o, 'lineLock' => $lock, 'void' => $o && $o['status'] === 'cancelled',
    'items' => $items, 'products' => sdoc_products($conn), 'customers' => sales_customers($conn), 'defaultStore' => $defaultStore, 'stockOn' => false,
    'schedules' => $repost ? (json_decode((string) ($repost['schedules_json'] ?? '[]'), true) ?: []) : array_map(fn($s) => ['due_date' => $s['payment_date'], 'note' => $s['payment_day'], 'amount' => 0], $d['schedules'] ?? []),
    'exchanges' => [], 'member' => null, 'points' => null, 'paid' => 0, 'due' => 0, 'held' => 'sdoc_hold_order',
]);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
