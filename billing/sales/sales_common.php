<?php
/**
 * Shared bootstrap and helpers for the sales pages that live in billing/sales/<module>/.
 * Each page does: require_once dirname(__DIR__) . '/sales_common.php';
 */
ob_start();
require_once __DIR__ . '/../core/admin_bootstrap.php';
require_once __DIR__ . '/helpers.php';

$conn = $GLOBALS['conn'] ?? ($conn ?? null);
if (!$conn instanceof mysqli || $conn->connect_errno) {
    http_response_code(500);
    exit('Database connection is not available.');
}
$conn->set_charset('utf8mb4');

if (!function_exists('sales_json')) {
    function sales_json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('sales_post_string')) {
    function sales_post_string($key, $maxLength = 255)
    {
        return sales_cut(trim((string) ($_POST[$key] ?? '')), $maxLength);
    }
}

if (!function_exists('sales_amount')) {
    function sales_amount($value)
    {
        if (!is_numeric($value)) {
            return 0.0;
        }
        return max(0.0, round((float) $value, 2));
    }
}

function sales_qty($value)
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    return max(0.0, round((float) $value, 3));
}

function sales_str($value, $maxLength)
{
    return sales_cut(trim((string) ($value ?? '')), $maxLength);
}

function sales_valid_date($value, $allowEmpty = false)
{
    $value = trim((string) $value);
    if ($value === '') {
        return $allowEmpty ? '' : null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : null;
}

/** Prepare, bind and execute one statement. Throws on any failure. */
function sales_exec(mysqli $db, $sql, $types = '', array $params = [])
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Database error: ' . $db->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $audit = audit_sql($db, $sql, $types, $params);
    if (!$stmt->execute()) {
        audit_sql_end($audit, false);
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Database error: ' . $message);
    }
    audit_sql_end($audit, true, $stmt->insert_id);
    return $stmt;
}

function sales_rows(mysqli $db, $sql, $types = '', array $params = [])
{
    $stmt = sales_exec($db, $sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** One page of a list, with the pager under its table. */
function sales_list_rows(mysqli $db, $sql, $types = '', array $params = [])
{
    return list_page_rows(fn($q, $t, $p) => sales_rows($db, $q, $t, $p), $sql, $types, $params);
}

function sales_row(mysqli $db, $sql, $types = '', array $params = [])
{
    $rows = sales_rows($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

function sales_insert(mysqli $db, $sql, $types, array $params)
{
    $stmt = sales_exec($db, $sql, $types, $params);
    $id = (int) $db->insert_id;
    $stmt->close();
    return $id;
}

/** True when every listed table exists. */
function sales_tables_ready(mysqli $db, array $tables)
{
    foreach ($tables as $table) {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            return false;
        }
        try {
            $check = $db->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
        } catch (Throwable $e) {
            return false;
        }
        if (!$check) {
            return false;
        }
    }
    return true;
}

/**
 * Reserve the next document number inside the caller's transaction.
 * sales_invoice_sequences and sales_order_sequences keep one row per year;
 * sales_doc_sequences keeps one row per (doc_type, year) for newer documents.
 */
function sales_next_number(mysqli $db, $prefix, $year, $sequenceTable = 'sales_doc_sequences')
{
    $year = (int) $year;
    if ($sequenceTable === 'sales_doc_sequences') {
        sales_exec(
            $db,
            'INSERT INTO sales_doc_sequences (doc_type, sequence_year, last_number) VALUES (?, ?, 1) '
                . 'ON DUPLICATE KEY UPDATE last_number = last_number + 1',
            'si',
            [$prefix, $year]
        )->close();
        $row = sales_row($db, 'SELECT last_number FROM sales_doc_sequences WHERE doc_type=? AND sequence_year=? FOR UPDATE', 'si', [$prefix, $year]);
    } elseif (in_array($sequenceTable, ['sales_invoice_sequences', 'sales_order_sequences'], true)) {
        sales_exec(
            $db,
            'INSERT INTO ' . $sequenceTable . ' (sequence_year, last_number) VALUES (?, 1) '
                . 'ON DUPLICATE KEY UPDATE last_number = last_number + 1',
            'i',
            [$year]
        )->close();
        $row = sales_row($db, 'SELECT last_number FROM ' . $sequenceTable . ' WHERE sequence_year=? FOR UPDATE', 'i', [$year]);
    } else {
        throw new InvalidArgumentException('Unknown sequence table.');
    }
    if (!$row) {
        throw new RuntimeException('Unable to reserve a document number.');
    }
    return $prefix . $year . str_pad((string) $row['last_number'], 6, '0', STR_PAD_LEFT);
}

/** Preview of the next number (display only, not reserved). */
function sales_peek_number(mysqli $db, $prefix, $sequenceTable = 'sales_doc_sequences')
{
    $year = (int) date('Y');
    try {
        if ($sequenceTable === 'sales_doc_sequences') {
            $row = sales_row($db, 'SELECT last_number + 1 AS n FROM sales_doc_sequences WHERE doc_type=? AND sequence_year=?', 'si', [$prefix, $year]);
        } else {
            $row = sales_row($db, 'SELECT last_number + 1 AS n FROM ' . $sequenceTable . ' WHERE sequence_year=?', 'i', [$year]);
        }
    } catch (Throwable $e) {
        $row = null;
    }
    return $prefix . $year . str_pad((string) ($row['n'] ?? 1), 6, '0', STR_PAD_LEFT);
}

function sales_flash_redirect($url, $kind, $message)
{
    $_SESSION['sales_flash'] = ['kind' => $kind, 'message' => $message];
    header('Location: ' . $url, true, 303);
    exit;
}

function sales_take_flash()
{
    $flash = $_SESSION['sales_flash'] ?? null;
    unset($_SESSION['sales_flash']);
    return is_array($flash) ? $flash : null;
}

function sales_require_csrf($redirectUrl)
{
    if (!bus_admin_csrf_valid()) {
        audit_security('csrf_failed', 'Form with a bad or expired check');
        sales_flash_redirect($redirectUrl, 'error', 'Your form expired. Reload the page and try again.');
    }
}

function sales_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . sales_h($_SESSION['bus_admin_csrf'] ?? '') . '">';
}

function sales_customers(mysqli $db)
{
    try {
        return sales_rows($db, 'SELECT customer_id, customer_code, customer_group, customer_name, mobile, attention, address, bin, nid FROM sales_customers ORDER BY customer_name LIMIT 2000');
    } catch (Throwable $e) {
        return [];
    }
}

function sales_products(mysqli $db)
{
    try {
        return sales_rows($db, 'SELECT product_id, product_code, product_group, brand, model_caption, description, unit_name, warranty, default_unit_price FROM sales_products WHERE is_active=1 ORDER BY description LIMIT 3000');
    } catch (Throwable $e) {
        return [];
    }
}

function sales_stores(mysqli $db)
{
    try {
        return perm_filter_stores(array_column(sales_rows($db, 'SELECT store_name FROM sales_stores WHERE is_active=1 ORDER BY store_name'), 'store_name'));
    } catch (Throwable $e) {
        return [];
    }
}

/** Amount in words for voucher and invoice prints (Bangladeshi style: lakh, crore). */
function acc_words($amount)
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = fn($n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    $three = fn($n) => trim(($n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred ' : '') . $two($n % 100));
    $taka = (int) floor($amount);
    $paisa = (int) round(($amount - $taka) * 100);
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $name]) {
        if ($taka >= $unit) {
            $parts[] = ($unit === 10000000 ? acc_words_int(intdiv($taka, $unit), $three, $two) : $two(intdiv($taka, $unit))) . ' ' . $name;
            $taka %= $unit;
        }
    }
    if ($taka > 0) {
        $parts[] = $three($taka);
    }
    $text = ($parts ? implode(' ', $parts) : 'Zero') . ' Taka';
    return $text . ($paisa > 0 ? ' and ' . $two($paisa) . ' Paisa' : '') . ' Only';
}

function acc_words_int($n, $three, $two)
{
    return $n >= 1000 ? trim($two(intdiv($n, 1000)) . ' Thousand ' . $three($n % 1000)) : $three($n);
}

function sales_js($value)
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function sales_money($value)
{
    return number_format((float) $value, 2);
}

function sales_qty_fmt($value)
{
    return number_format((float) $value, 3);
}

/** Page header with the shared navigation between sales screens. */
function sales_page_start($title, $active = '')
{
    $links = [
        'invoice' => ['../sales_invoice/sales_invoice.php', 'Sales Invoice'],
        'pos' => ['../pos_sales/pos_sales.php', 'POS Sales'],
        'order' => ['../sales_order/sales_order.php', 'Sales Order'],
        'adjust' => ['../sales_order_adjustment/sales_order_adjustment.php', 'Order Adjustment'],
        'bill' => ['../sal_bill_from_sal_order/sal_bill_from_sal_order.php', 'Bill from Order'],
        'return' => ['../sales_retrun/sales_return.php', 'Sales Return'],
    ];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales.css"><link rel="stylesheet" href="../../doc_layout.css?v=' . (int) @filemtime(dirname(__DIR__) . '/doc_layout.css') . '"></head><body><div class="wrap">';
    echo '<nav class="sales-nav">';
    foreach ($links as $key => [$href, $label]) {
        echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    echo '</nav><main class="panel">';
}

function sales_page_end($script = '')
{
    echo '</main></div><script src="../sales.js"></script>';
    if ($script !== '') {
        echo '<script>' . $script . '</script>';
    }
    echo '</body></html>';
}

function sales_flash_html($flash)
{
    if (!$flash) {
        return '';
    }
    $kind = ($flash['kind'] ?? '') === 'success' ? 'success' : 'error';
    // data-popup: addon/dialog/dialog.js opens it as a Success / Failed popup; without scripts it stays this banner.
    return '<div class="alert ' . $kind . '" data-popup="' . $kind . '" role="status">' . sales_h($flash['message'] ?? '') . '</div>';
}

function sales_schema_missing_html($file)
{
    return '<div class="errorbox"><strong>Sales tables are not installed yet.</strong><br>Import <code>billing/sales/schema.sql</code> and then <code>'
        . sales_h($file) . '</code> into the MySQL database, then reload this page.</div>';
}

/** Billed quantity per order line: [order_item_id => qty]. Empty when the bill tables are not installed. */
function sales_order_billed_map(mysqli $db, $orderId)
{
    if (!sales_tables_ready($db, ['sales_order_bill_items', 'sales_order_bills'])) {
        return [];
    }
    $map = [];
    $rows = sales_rows(
        $db,
        'SELECT bi.order_item_id, SUM(bi.quantity) AS billed FROM sales_order_bill_items bi '
            . 'JOIN sales_order_bills b ON b.bill_id = bi.bill_id WHERE b.order_id=? GROUP BY bi.order_item_id',
        'i',
        [(int) $orderId]
    );
    foreach ($rows as $row) {
        $map[(int) $row['order_item_id']] = (float) $row['billed'];
    }
    return $map;
}

/** Advance already applied to bills for an order. */
function sales_order_advance_applied(mysqli $db, $orderId)
{
    if (!sales_tables_ready($db, ['sales_order_bills'])) {
        return 0.0;
    }
    $row = sales_row($db, 'SELECT COALESCE(SUM(advance_applied),0) AS applied FROM sales_order_bills WHERE order_id=?', 'i', [(int) $orderId]);
    return round((float) ($row['applied'] ?? 0), 2);
}

/**
 * Quantity per order line delivered by an active challan (Challan, Sales Order Wise): [order_item_id => qty].
 * Those goods are billed through Sales Bill From Challan, so they are no longer open for Bill from Order.
 */
function sales_order_challan_map(mysqli $db, $orderId)
{
    if (!sales_tables_ready($db, ['inv_docs', 'inv_doc_lines'])) {
        return [];
    }
    $rows = sales_rows(
        $db,
        "SELECT l.source_line_id, SUM(l.quantity + l.free_quantity) AS q FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id AND d.doc_type='challan' AND d.status='active' "
            . "JOIN sales_order_items i ON i.item_id=l.source_line_id WHERE l.source_kind='sales_order' AND i.order_id=? GROUP BY l.source_line_id",
        'i',
        [(int) $orderId]
    );
    return array_map('floatval', array_column($rows, 'q', 'source_line_id'));
}

/** Set an order to converted when every line is fully billed or delivered by challan, otherwise open. Cancelled orders are left alone. */
function sales_order_refresh_status(mysqli $db, $orderId)
{
    $billed = sales_order_billed_map($db, $orderId);
    $challan = sales_order_challan_map($db, $orderId);
    $lines = sales_rows($db, 'SELECT item_id, quantity FROM sales_order_items WHERE order_id=?', 'i', [(int) $orderId]);
    $complete = $lines && ($billed || $challan);
    foreach ($lines as $line) {
        if ((float) $line['quantity'] - ($billed[(int) $line['item_id']] ?? 0.0) - ($challan[(int) $line['item_id']] ?? 0.0) > 0.0005) {
            $complete = false;
            break;
        }
    }
    sales_exec(
        $db,
        "UPDATE sales_orders SET status=? WHERE order_id=? AND status<>'cancelled'",
        'si',
        [$complete ? 'converted' : 'open', (int) $orderId]
    )->close();
    return $complete ? 'converted' : 'open';
}

/** Posted payment method limited to the values allowed by the payment ENUM columns. */
function sales_payment_method($key)
{
    $method = (string) ($_POST[$key] ?? 'cash');
    return in_array($method, ['cash', 'bank', 'mobile', 'other'], true) ? $method : 'cash';
}
