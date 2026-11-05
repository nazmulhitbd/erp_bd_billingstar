<?php
/**
 * Shared code for the Sales Invoice/Bill and Sales Order screens: page shell, line cleaning, product data,
 * attachments, member points, additional info and money receipts. Tables: sales/schema.sql, the sales_order
 * schemas, inventory/schema.sql and sales/schema_v2.sql.
 */
require_once __DIR__ . '/sales_stock.php';

const SDOC_UPLOAD_DIR = __DIR__ . '/uploads';
const SDOC_UPLOAD_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'csv' => 'text/csv',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
const SDOC_METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile Banking', 'other' => 'Other'];
/** Fields of the Additional Info box, saved as JSON on the invoice or order. */
const SDOC_ADDITIONAL = INV_ADDITIONAL;

function sdoc_ready(mysqli $db)
{
    return inv_ready($db) && sales_tables_ready($db, ['sales_members', 'sales_member_points', 'sales_invoice_exchanges', 'sales_doc_attachments']);
}

/** Page shell: sales menu (same order as the Sales menu), branch picker, fieldset with the screen title. */
function sdoc_page_start(mysqli $db, $title, $active)
{
    $links = [
        'invoice' => ['../sales_invoice/sales_invoice.php', 'Sales Invoice/Bill'], 'return' => ['../sales_retrun/sales_return.php', 'Sales Return'],
        'order' => ['../sales_order/sales_order.php', 'Sales Order'], 'bill' => ['../sal_bill_from_sal_order/sal_bill_from_sal_order.php', 'Sales Bill From Sales Order'],
        'adjust' => ['../sales_order_adjustment/sales_order_adjustment.php', 'Sales Order Adjust'], 'pos' => ['../pos_sales/pos_sales.php', 'POS Sales'],
    ];
    $branch = inv_ready($db) ? inv_current_branch($db) : 0;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . sales_h($title) . '</title>'
        . '<link rel="stylesheet" href="../sales.css"><link rel="stylesheet" href="../../inventory/inv.css"><link rel="stylesheet" href="../sales_doc.css"></head><body><div class="wrap"><nav class="sales-nav">';
    foreach ($links as $key => [$href, $label]) {
        echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    if ($branch) {
        $query = $_GET;
        unset($query['set_branch']);
        echo '<form method="get" class="branch-pick">';
        foreach ($query as $k => $v) {
            if (is_scalar($v)) {
                echo '<input type="hidden" name="' . sales_h($k) . '" value="' . sales_h($v) . '">';
            }
        }
        echo '<label>Branch <select name="set_branch" onchange="this.form.submit()">';
        foreach (inv_branches($db) as $b) {
            echo '<option value="' . (int) $b['branch_id'] . '"' . ((int) $b['branch_id'] === $branch ? ' selected' : '') . '>' . sales_h($b['branch_name']) . '</option>';
        }
        echo '</select></label></form>';
    }
    echo '</nav><main class="panel"><fieldset class="box"><legend>' . sales_h($title) . '</legend>';
}

function sdoc_page_end($config = null)
{
    echo '</fieldset></main></div><script src="../sales.js"></script>';
    if ($config !== null) {
        echo '<script>window.SDOC=' . sales_js($config) . ';</script><script src="../sales_doc.js"></script>';
    }
    echo '</body></html>';
}

function sdoc_missing_html()
{
    return '<div class="errorbox"><strong>Sales tables are not fully installed.</strong><br>Import the sales and inventory SQL files (see <code>billing/inventory/README.md</code>), then <code>billing/sales/schema_v2.sql</code>, and reload this page.</div>';
}

/* ---------------- Lines ---------------- */

/**
 * Clean posted lines (JSON). Returns [lines, subtotal, totalQuantity].
 * Each line: product fields, store_name, warranty, product_narration, quantity, free_quantity, unit_price,
 * discount_amount, line_total and serials (list).
 */
function sdoc_clean_lines($raw, array $stores, $withSerials)
{
    $items = json_decode((string) $raw, true);
    if (!is_array($items) || !$items || count($items) > 300) {
        throw new RuntimeException('Add at least one item (up to 300 per document).');
    }
    $out = [];
    $subtotal = 0.0;
    $qtyTotal = 0.0;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $desc = sales_str($it['description'] ?? '', 255);
        $qty = sales_qty($it['quantity'] ?? 0);
        $free = sales_qty($it['free_quantity'] ?? 0);
        if ($desc === '' || $qty <= 0) {
            continue;
        }
        $price = sales_amount($it['unit_price'] ?? 0);
        $disc = min(sales_amount($it['discount_amount'] ?? 0), round($qty * $price, 2));
        $store = sales_str($it['store_name'] ?? '', 140);
        if ($store !== '' && !in_array($store, $stores, true)) {
            throw new RuntimeException('Store "' . $store . '" does not exist.');
        }
        $serials = $withSerials ? inv_parse_serials($it['serials'] ?? []) : [];
        if ($serials && count($serials) !== (int) round($qty + $free)) {
            throw new RuntimeException('"' . $desc . '" has ' . count($serials) . ' serial(s) for a quantity of ' . sales_qty_fmt($qty + $free) . '. They must match.');
        }
        $total = max(0.0, round($qty * $price - $disc, 2));
        $out[] = [
            'product_id' => ((int) ($it['product_id'] ?? 0)) ?: null, 'product_code' => sales_str($it['product_code'] ?? '', 60), 'product_group' => sales_str($it['product_group'] ?? '', 140),
            'brand' => sales_str($it['brand'] ?? '', 140), 'model_caption' => sales_str($it['model_caption'] ?? '', 190), 'description' => $desc,
            'product_narration' => sales_str($it['product_narration'] ?? '', 255), 'warranty' => sales_str($it['warranty'] ?? '', 100), 'store_name' => $store,
            'unit_name' => sales_str($it['unit_name'] ?? 'Pcs', 40) ?: 'Pcs', 'quantity' => $qty, 'free_quantity' => $free, 'unit_price' => $price,
            'discount_amount' => $disc, 'line_total' => $total, 'serials' => $serials,
        ];
        $subtotal += $total;
        $qtyTotal += $qty + $free;
    }
    if (!$out) {
        throw new RuntimeException('Each line needs a description and a quantity greater than zero.');
    }
    return [$out, round($subtotal, 2), round($qtyTotal, 3)];
}

/** Additional Info JSON from the posted add_* fields. */
function sdoc_additional_from_post()
{
    return inv_additional_from_post();
}

function sdoc_additional_html($json)
{
    $data = json_decode((string) $json, true) ?: [];
    $html = '<section class="section" id="additional-info" hidden><div class="section-title">Additional Info</div><div class="hgrid">';
    foreach (SDOC_ADDITIONAL as $k => $label) {
        $html .= '<div class="field' . ($k === 'remarks' || $k === 'installation_address' ? ' s6' : '') . '"><label for="add_' . $k . '">' . sales_h($label) . '</label><input id="add_' . $k . '" name="add_' . $k . '" form="docform" maxlength="255" value="' . sales_h($data[$k] ?? '') . '"></div>';
    }
    return $html . '</div></section>';
}

/** Product, availability and price data for the screen's JS. */
function sdoc_products(mysqli $db)
{
    return inv_products($db);
}

/**
 * JSON for the product info line: Qty Available, Last Price (last sale price, to this customer when known),
 * Last Serial (oldest serial in stock in that store), Dealer A / Sales Price, and Rest Of Qty (available less open orders).
 */
function sdoc_product_info(mysqli $db, $productId, $store, $customerId)
{
    $pid = (int) $productId;
    $qty = inv_available($db, $pid, $store);
    $last = $customerId > 0 ? sales_row($db, "SELECT i.unit_price FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id WHERE i.product_id=? AND v.customer_id=? AND v.status='posted' ORDER BY i.item_id DESC LIMIT 1", 'ii', [$pid, (int) $customerId]) : null;
    $last = $last ?: sales_row($db, "SELECT i.unit_price FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id WHERE i.product_id=? AND v.status='posted' ORDER BY i.item_id DESC LIMIT 1", 'i', [$pid]);
    $serial = sales_row($db, 'SELECT s.serial_no FROM inv_serials s WHERE s.product_id=? AND s.in_stock=1' . ($store !== '' ? ' AND s.store_name=?' : '') . ' ORDER BY s.serial_id LIMIT 1', $store !== '' ? 'is' : 'i', $store !== '' ? [$pid, $store] : [$pid]);
    $price = sales_row($db, 'SELECT p.default_unit_price, COALESCE(pp.dealer_a, 0) AS dealer_a, COALESCE(pp.mrp, 0) AS mrp FROM sales_products p LEFT JOIN inv_product_prices pp ON pp.product_id=p.product_id WHERE p.product_id=?', 'i', [$pid]);
    // Still to deliver on open orders: ordered less billed less delivered by challan.
    $ordered = (float) (sales_row(
        $db,
        'SELECT COALESCE(SUM(GREATEST(i.quantity - COALESCE(b.q, 0) - COALESCE(c.q, 0), 0)), 0) AS q FROM sales_order_items i JOIN sales_orders o ON o.order_id=i.order_id '
            . 'LEFT JOIN (SELECT order_item_id, SUM(quantity) AS q FROM sales_order_bill_items GROUP BY order_item_id) b ON b.order_item_id=i.item_id '
            . "LEFT JOIN (SELECT l.source_line_id, SUM(l.quantity + l.free_quantity) AS q FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id AND d.doc_type='challan' AND d.status='active' WHERE l.source_kind='sales_order' GROUP BY l.source_line_id) c ON c.source_line_id=i.item_id "
            . "WHERE o.status='open' AND i.product_id=?" . ($store !== '' ? ' AND i.store_name=?' : ''),
        $store !== '' ? 'is' : 'i',
        $store !== '' ? [$pid, $store] : [$pid]
    )['q'] ?? 0);
    return ['qty' => $qty, 'last_price' => (float) ($last['unit_price'] ?? 0), 'last_serial' => (string) ($serial['serial_no'] ?? ''), 'dealer_a' => (float) ($price['dealer_a'] ?? 0),
        'sales_price' => (float) ($price['default_unit_price'] ?? 0), 'rest_qty' => round($qty - max(0, $ordered), 3)];
}

/* ---------------- Members and points ---------------- */

function sdoc_point_settings(mysqli $db)
{
    return ['per100' => max(0.0, (float) inv_setting($db, 'member_points_per_100', '1')), 'value' => max(0.0, (float) inv_setting($db, 'member_point_value', '1'))];
}

function sdoc_member(mysqli $db, $memberNo)
{
    return $memberNo === '' ? null : sales_row($db, 'SELECT * FROM sales_members WHERE member_no=? AND is_active=1', 's', [$memberNo]);
}

/** Point balance of a member, optionally leaving one invoice's entries out (when that invoice is being edited). */
function sdoc_member_balance(mysqli $db, $memberId, $excludeInvoiceId = 0)
{
    $row = sales_row($db, 'SELECT m.opening_points + COALESCE((SELECT SUM(points) FROM sales_member_points p WHERE p.member_id=m.member_id AND (p.invoice_id IS NULL OR p.invoice_id<>?)), 0) AS b FROM sales_members m WHERE m.member_id=?', 'ii', [(int) $excludeInvoiceId, (int) $memberId]);
    return round((float) ($row['b'] ?? 0), 2);
}

/** Rewrite an invoice's earn / redeem entries. */
function sdoc_points_post(mysqli $db, $invoiceId, $memberId, $date, $earned, $redeemed)
{
    sales_exec($db, 'DELETE FROM sales_member_points WHERE invoice_id=?', 'i', [(int) $invoiceId])->close();
    if (!$memberId) {
        return;
    }
    $user = (int) ($GLOBALS['user_id'] ?? 0);
    if ($redeemed > 0) {
        sales_insert($db, "INSERT INTO sales_member_points (member_id, invoice_id, entry_date, kind, points, note, created_by) VALUES (?, ?, ?, 'redeem', ?, 'Point adjust on invoice', ?)", 'iisdi', [(int) $memberId, (int) $invoiceId, $date, -$redeemed, $user]);
    }
    if ($earned > 0) {
        sales_insert($db, "INSERT INTO sales_member_points (member_id, invoice_id, entry_date, kind, points, note, created_by) VALUES (?, ?, ?, 'earn', ?, 'Earned on invoice', ?)", 'iisdi', [(int) $memberId, (int) $invoiceId, $date, $earned, $user]);
    }
}

/* ---------------- Attachments ---------------- */

/** Handle attach / detach posts and downloads for a document kind. Returns true when it handled the request. */
function sdoc_attachment_request(mysqli $db, $kind, $self)
{
    if (isset($_GET['attachment'])) {
        $file = sales_row($db, 'SELECT * FROM sales_doc_attachments WHERE attachment_id=? AND doc_kind=?', 'is', [(int) $_GET['attachment'], $kind]);
        $path = $file ? SDOC_UPLOAD_DIR . '/' . basename($file['stored_name']) : '';
        if (!$file || !is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (in_array($file['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true) ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $file['original_name']) . '"');
        readfile($path);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($action, ['attach', 'detach'], true)) {
        return false;
    }
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $back = $self . '?id=' . $docId . '#attached';
    sales_require_csrf($back);
    try {
        if ($docId <= 0) {
            throw new RuntimeException('Save the document before attaching files.');
        }
        if ($action === 'detach') {
            $file = sales_row($db, 'SELECT * FROM sales_doc_attachments WHERE attachment_id=? AND doc_kind=? AND doc_id=?', 'isi', [(int) ($_POST['attachment_id'] ?? 0), $kind, $docId]);
            if ($file) {
                sales_exec($db, 'DELETE FROM sales_doc_attachments WHERE attachment_id=?', 'i', [(int) $file['attachment_id']])->close();
                @unlink(SDOC_UPLOAD_DIR . '/' . basename($file['stored_name']));
            }
            sales_flash_redirect($back, 'success', 'File removed.');
        }
        $up = $_FILES['document'] ?? null;
        if (!$up || $up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) {
            throw new RuntimeException('Choose a file to attach.');
        }
        if ($up['size'] > 5242880) {
            throw new RuntimeException('Files can be at most 5 MB.');
        }
        $ext = strtolower(pathinfo((string) $up['name'], PATHINFO_EXTENSION));
        if (!isset(SDOC_UPLOAD_TYPES[$ext])) {
            throw new RuntimeException('Attach a PDF, image, CSV, Excel or Word file.');
        }
        if (!is_dir(SDOC_UPLOAD_DIR) && !mkdir(SDOC_UPLOAD_DIR, 0775, true)) {
            throw new RuntimeException('The uploads folder could not be created.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($up['tmp_name'], SDOC_UPLOAD_DIR . '/' . $stored)) {
            throw new RuntimeException('Could not save the file. Check that billing/sales/uploads is writable.');
        }
        sales_insert($db, 'INSERT INTO sales_doc_attachments (doc_kind, doc_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'sisssii', [$kind, $docId, sales_cut(basename((string) $up['name']), 190), $stored, SDOC_UPLOAD_TYPES[$ext], (int) $up['size'], (int) ($GLOBALS['user_id'] ?? 0)]);
        sales_flash_redirect($back, 'success', 'File attached.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
    return true;
}

function sdoc_attachment_panel(mysqli $db, $kind, $docId, $self, $canEdit)
{
    $files = sales_rows($db, 'SELECT * FROM sales_doc_attachments WHERE doc_kind=? AND doc_id=? ORDER BY attachment_id', 'si', [$kind, (int) $docId]);
    echo '<section class="section" id="attached" hidden><div class="section-title">Attached Documents</div>';
    if ($canEdit) {
        echo '<form method="post" enctype="multipart/form-data" class="list-search">' . sales_csrf_field() . '<input type="hidden" name="action" value="attach"><input type="hidden" name="doc_id" value="' . (int) $docId . '">'
            . '<input class="input" type="file" name="document" required accept=".' . implode(',.', array_keys(SDOC_UPLOAD_TYPES)) . '"><button class="btn primary">Attach</button></form>';
    }
    echo '<table><tbody>' . ($files ? '' : '<tr><td class="empty">No documents.</td></tr>');
    foreach ($files as $a) {
        echo '<tr><td><a target="_blank" href="' . $self . '?attachment=' . (int) $a['attachment_id'] . '">' . sales_h($a['original_name']) . '</a></td><td class="num">' . number_format($a['file_size'] / 1024, 1) . ' KB</td><td>'
            . ($canEdit ? '<form method="post" onsubmit="return confirm(\'Remove this file?\')">' . sales_csrf_field() . '<input type="hidden" name="action" value="detach"><input type="hidden" name="doc_id" value="' . (int) $docId . '"><input type="hidden" name="attachment_id" value="' . (int) $a['attachment_id'] . '"><button class="btn small danger">Remove</button></form>' : '')
            . '</td></tr>';
    }
    echo '</tbody></table></section>';
}

/* ---------------- Prints ---------------- */

/** Money receipt for one or more payments. $rows: [date, no, method, reference, amount]. */
function sdoc_receipt_print(mysqli $db, $title, array $doc, array $rows, $againstLabel)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($title) . '</title><style>' . INV_PRINT_CSS . '.rc{border:1px solid #333;padding:14px;margin-bottom:18px;page-break-inside:avoid}.words{font-style:italic}</style></head><body' . (!empty($_GET['auto']) ? ' onload="window.print()"' : '') . '>';
    echo '<div class="bar"><button onclick="window.print()">Print</button></div>';
    if (!$rows) {
        echo '<p>No payment has been received yet.</p>';
    }
    foreach ($rows as [$date, $no, $method, $ref, $amount]) {
        echo '<div class="rc">' . inv_print_head($db, 'Money Receipt') . '<div class="meta"><div><b>Receipt No:</b> ' . sales_h($no) . '</div><div><b>Date:</b> ' . sales_h(inv_fmt_date($date)) . '</div>'
            . '<div><b>Received From:</b> ' . sales_h($doc['customer_name']) . '</div><div><b>Mobile:</b> ' . sales_h($doc['mobile']) . '</div>'
            . '<div><b>' . sales_h($againstLabel) . ':</b> ' . sales_h($doc['doc_no']) . '</div><div><b>Method:</b> ' . sales_h(SDOC_METHODS[$method] ?? ucfirst((string) $method)) . ($ref !== '' ? ' (' . sales_h($ref) . ')' : '') . '</div></div>'
            . '<p style="font-size:16px"><b>Amount: Tk ' . sales_money($amount) . '</b></p><div class="sign"><div>Customer</div><div></div><div></div><div>Received By</div></div></div>';
    }
    echo '</body></html>';
}

/* ---------------- Customer credit and SR link ---------------- */

/** SR names for the Sales Person type-ahead (empty until the trader tables exist). */
function sdoc_sr_names(mysqli $db)
{
    return sales_tables_ready($db, ['sales_representatives']) ? array_column(array_filter(sales_rows($db, 'SELECT * FROM sales_representatives WHERE is_active=1 ORDER BY sr_name'), fn($s) => empty($s['staff_only'])), 'sr_name') : [];
}

/** Credit the document to the SR whose name is its Sales Person (sales_invoices / sales_orders.sr_id, see sales_person/schema.sql). */
function sdoc_link_sr(mysqli $db, $table, $pk, $id)
{
    if (!in_array($table, ['sales_invoices', 'sales_orders'], true) || !inv_has_column($db, $table, 'sr_id')) {
        return;
    }
    sales_exec($db, "UPDATE $table t LEFT JOIN sales_representatives s ON s.sr_name=t.sales_person AND t.sales_person<>'' SET t.sr_id=s.sr_id WHERE t.$pk=?", 'i', [(int) $id])->close();
}

/**
 * Credit rules of the invoice's customer (Customer Management, customer/schema.sql), checked after the invoice is
 * written inside the save transaction: blocked customers are refused; when the invoice leaves money owing, the
 * customer must be allowed credit, stay within the credit limit, and have no other due older than the credit days.
 * Customers without credit rules are not limited.
 */
function sdoc_credit_check(mysqli $db, $invoiceId)
{
    if (!sales_tables_ready($db, ['customer_accounts'])) {
        return;
    }
    $inv = sales_row($db, 'SELECT invoice_id, customer_id, balance_amount FROM sales_invoices WHERE invoice_id=?', 'i', [(int) $invoiceId]);
    if (!$inv || !$inv['customer_id']) {
        return;
    }
    $c = sales_row($db, 'SELECT * FROM customer_accounts WHERE customer_id=?', 'i', [(int) $inv['customer_id']]);
    if (!$c || !(int) $c['has_credit_rules']) {
        return;
    }
    $name = $c['customer_name'];
    if ((int) $c['block_sales']) {
        throw new RuntimeException($name . ' is blocked for sales (Customer Management › Credit Limit).');
    }
    if ((float) $inv['balance_amount'] <= 0.004) {
        return;
    }
    if (!(int) $c['allow_credit']) {
        throw new RuntimeException($name . ' is not allowed credit. Receive the full amount (' . sales_money((float) $inv['balance_amount']) . ' still due) or allow credit in Customer Management.');
    }
    if ((float) $c['credit_limit'] > 0 && (float) $c['balance'] > (float) $c['credit_limit'] + 0.004) {
        throw new RuntimeException($name . "'s due would be " . sales_money($c['balance']) . ', over the credit limit of ' . sales_money($c['credit_limit']) . '. Receive at least ' . sales_money((float) $c['balance'] - (float) $c['credit_limit']) . ' more.');
    }
    if ((int) $c['credit_days'] > 0) {
        $old = sales_row($db, "SELECT MIN(sales_date) AS d FROM sales_invoices WHERE customer_id=? AND status='posted' AND balance_amount>0.004 AND invoice_id<>?", 'ii', [(int) $inv['customer_id'], (int) $invoiceId]);
        if (!empty($old['d']) && (int) (new DateTime($old['d']))->diff(new DateTime('today'))->format('%a') > (int) $c['credit_days']) {
            throw new RuntimeException($name . ' has a due from ' . inv_fmt_date($old['d']) . ', older than the ' . (int) $c['credit_days'] . ' credit days. Collect it first, or sell for cash.');
        }
    }
}
