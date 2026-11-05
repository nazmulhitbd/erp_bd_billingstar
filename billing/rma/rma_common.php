<?php
ob_start();
require_once dirname(__DIR__) . '/core/admin_bootstrap.php';
require_once dirname(__DIR__) . '/sales/helpers.php';

// Company code used in document numbers, e.g. DESCOM2023000003.
const RMA_DOC_PREFIX = 'DES';

$conn = $GLOBALS['conn'] ?? ($GLOBALS['link'] ?? null);
if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Database connection is not available.');
}
$conn->set_charset('utf8mb4');
$GLOBALS['rma_conn'] = $conn;

function rma_h($value)
{
    return sales_h($value);
}

function rma_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rma_post($key, $maxLength = 255)
{
    return sales_cut(trim((string) ($_POST[$key] ?? '')), $maxLength);
}

function rma_amount($value, $decimals = 2)
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    return max(0.0, round((float) $value, $decimals));
}

function rma_date_or_null($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

function rma_user_id()
{
    return (int) ($GLOBALS['user_id'] ?? 0);
}

function rma_user_name()
{
    return (string) ($GLOBALS['user_name'] ?? '');
}

/** Run a prepared statement; types use mysqli bind_param letters. Throws on failure. */
function rma_exec($sql, $types = '', array $params = [])
{
    $conn = $GLOBALS['rma_conn'];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Database error: ' . $conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $audit = audit_sql($conn, $sql, $types, $params);
    if (!$stmt->execute()) {
        audit_sql_end($audit, false);
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Database error: ' . $error);
    }
    audit_sql_end($audit, true, $stmt->insert_id);
    return $stmt;
}

function rma_rows($sql, $types = '', array $params = [])
{
    try {
        $stmt = rma_exec($sql, $types, $params);
    } catch (Throwable $error) {
        error_log('RMA query failed: ' . $error->getMessage());
        return [];
    }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function rma_row($sql, $types = '', array $params = [])
{
    $rows = rma_rows($sql, $types, $params);
    return $rows[0] ?? null;
}

function rma_schema_ready()
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool) $GLOBALS['rma_conn']->query('SELECT 1 FROM rma_stock_ledger LIMIT 0')
            && (bool) $GLOBALS['rma_conn']->query('SELECT 1 FROM sales_customers LIMIT 0');
    }
    return $ready;
}

/** Reserve the next number for a document type, e.g. DESCOM2023000003. Call inside a transaction. */
function rma_doc_no($docCode, $year)
{
    rma_exec(
        'INSERT INTO rma_sequences (doc_type, seq_year, last_number) VALUES (?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
        'si',
        [$docCode, $year]
    )->close();
    $row = rma_row('SELECT last_number FROM rma_sequences WHERE doc_type=? AND seq_year=?', 'si', [$docCode, $year]);
    if (!$row) {
        throw new RuntimeException('Unable to reserve a document number.');
    }
    return RMA_DOC_PREFIX . $docCode . $year . str_pad((string) $row['last_number'], 6, '0', STR_PAD_LEFT);
}

function rma_next_doc_preview($docCode)
{
    $year = (int) date('Y');
    $row = rma_row('SELECT last_number + 1 AS n FROM rma_sequences WHERE doc_type=? AND seq_year=?', 'si', [$docCode, $year]);
    return RMA_DOC_PREFIX . $docCode . $year . str_pad((string) ($row['n'] ?? 1), 6, '0', STR_PAD_LEFT);
}

/** Write one stock movement. $line needs store_name, product_id, product_group, brand, model_no. */
function rma_ledger($date, array $line, $category, $qtyIn, $qtyOut, $unitCost, $serial, $docType, $docId, $docNo, $party)
{
    $productId = (int) ($line['product_id'] ?? 0) ?: null;
    $store = (string) ($line['store_name'] ?? '');
    $group = (string) ($line['product_group'] ?? '');
    $brand = (string) ($line['brand'] ?? '');
    $model = (string) ($line['model_no'] ?? '');
    $userId = rma_user_id();
    rma_exec(
        'INSERT INTO rma_stock_ledger (txn_date, store_name, product_id, product_group, brand, model_no, category, serial_no, qty_in, qty_out, unit_cost, doc_type, doc_id, doc_no, party_name, created_by) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'ssisssssdddsissi',
        [$date, $store, $productId, $group, $brand, $model, $category, (string) $serial, (float) $qtyIn, (float) $qtyOut, (float) $unitCost, $docType, (int) $docId, (string) $docNo, (string) $party, $userId]
    )->close();
}

function rma_store_default($purpose)
{
    $row = rma_row('SELECT store_name FROM rma_store_settings WHERE purpose=?', 's', [$purpose]);
    return $row['store_name'] ?? 'RMA STORE';
}

function rma_flash($kind, $message)
{
    $_SESSION['rma_flash'] = ['kind' => $kind, 'message' => $message];
}

function rma_take_flash()
{
    $flash = $_SESSION['rma_flash'] ?? null;
    unset($_SESSION['rma_flash']);
    return is_array($flash) ? $flash : null;
}

function rma_redirect($url)
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Reject POSTs with a bad CSRF token. */
function rma_require_csrf($backUrl)
{
    if (!bus_admin_csrf_valid()) {
        rma_flash('error', 'Your form expired. Reload the page and try again.');
        rma_redirect($backUrl);
    }
}

function rma_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . rma_h($_SESSION['bus_admin_csrf'] ?? '') . '">';
}

function rma_page_header($title, $bodyClass = '')
{
    $flash = rma_take_flash();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . rma_h($title) . '</title><link rel="stylesheet" href="rma.css?v=1"></head><body class="' . rma_h($bodyClass) . '">';
    echo '<div class="rma-wrap"><fieldset class="rma-box"><legend>' . rma_h($title) . '</legend><div class="rma-inner">';
    if ($flash) {
        echo '<div class="rma-alert ' . ($flash['kind'] === 'success' ? 'ok' : 'err') . '" data-popup="' . ($flash['kind'] === 'success' ? 'success' : 'error') . '" role="status">' . rma_h($flash['message']) . '</div>';
    }
    if (!rma_schema_ready()) {
        echo '<div class="rma-alert err"><b>RMA tables are not installed.</b> Import <code>billing/sales/schema.sql</code> and then <code>billing/rma/schema.sql</code> into the BusBook database, then reload this page.</div>';
    }
}

function rma_page_footer($script = '')
{
    echo '</div></fieldset></div>';
    echo '<div class="rma-modal" id="rma-modal" hidden><div class="rma-dialog"><div class="rma-dialog-title"><span id="rma-modal-title">Confirm Message</span><button type="button" class="rma-x" data-close>&times;</button></div>'
        . '<div class="rma-dialog-body" id="rma-modal-body"></div><div class="rma-dialog-foot" id="rma-modal-foot"></div></div></div>';
    echo '<script src="rma.js?v=1"></script>';
    if ($script !== '') {
        echo '<script>' . $script . '</script>';
    }
    echo '</body></html>';
}

/** Where Close goes: the Dashboard, the shell's home page. */
function rma_home_url()
{
    return '../dashboard/dashboard.php';
}

/* ------------------------------------------------------------------ AJAX lookups */

function rma_lookup($kind, $term, $mode)
{
    $like = '%' . $term . '%';
    switch ($kind) {
        case 'customer':
            return rma_rows(
                'SELECT customer_id, customer_group, customer_name, mobile, address, attention, '
                    . "CONCAT(customer_name, ' · ', mobile) AS _label FROM sales_customers "
                    . 'WHERE customer_name LIKE ? OR mobile LIKE ? OR customer_code LIKE ? ORDER BY customer_name LIMIT 20',
                'sss', [$like, $like, $like]
            );
        case 'supplier':
            return rma_rows(
                'SELECT supplier_id, supplier_group, supplier_name, mobile, address, attention, '
                    . "CONCAT(supplier_name, ' · ', mobile) AS _label FROM rma_suppliers "
                    . 'WHERE is_active=1 AND (supplier_name LIKE ? OR mobile LIKE ?) ORDER BY supplier_name LIMIT 20',
                'ss', [$like, $like]
            );
        case 'product':
            return rma_rows(
                "SELECT product_id, product_group, brand, IF(model_caption<>'', model_caption, description) AS model_no, description, "
                    . "CONCAT(IF(model_caption<>'', model_caption, description), ' · ', brand) AS _label FROM sales_products "
                    . 'WHERE is_active=1 AND (description LIKE ? OR model_caption LIKE ? OR product_code LIKE ? OR brand LIKE ?) ORDER BY description LIMIT 20',
                'ssss', [$like, $like, $like, $like]
            );
        case 'sold_serial':
            return rma_rows(
                'SELECT i.invoice_no AS sales_invoice_no, i.sales_date, it.unit_price AS sales_value, '
                    . "IF(it.serial_imei<>'', it.serial_imei, it.serial_common) AS sold_serial, "
                    . "IF(it.serial_imei<>'', it.serial_imei, it.serial_common) AS received_serial, "
                    . "it.product_id, it.product_group, it.brand, IF(it.model_caption<>'', it.model_caption, it.description) AS model_no, it.description, "
                    . 'i.customer_id, i.customer_group, i.customer_name, i.mobile, i.address, i.attention, '
                    . "CONCAT(IF(it.serial_imei<>'', it.serial_imei, it.serial_common), ' · ', i.invoice_no, ' · ', i.customer_name) AS _label "
                    . 'FROM sales_invoice_items it JOIN sales_invoices i ON i.invoice_id=it.invoice_id '
                    . 'WHERE it.serial_imei LIKE ? OR it.serial_common LIKE ? OR i.invoice_no LIKE ? ORDER BY i.invoice_id DESC LIMIT 20',
                'sss', [$like, $like, $like]
            );
        case 'complain_item':
            return rma_complain_item_lookup($like, $mode);
        case 'replace_out_item':
            return rma_rows(
                'SELECT ri.item_id AS replace_out_item_id, r.replace_out_no AS out_job_ref_no, ri.product_group AS out_product_group, '
                    . 'ri.model_no AS out_model_no, ri.out_serial, ri.qty AS out_qty, ri.product_id, ri.product_group, ri.brand, ri.model_no, '
                    . 'ri.description, ri.out_serial AS serial_no, ri.qty, '
                    . 'r.supplier_id, r.supplier_group, r.supplier_name, r.mobile, r.address, '
                    . "CONCAT(r.replace_out_no, ' · ', ri.model_no, ' · ', ri.out_serial) AS _label "
                    . "FROM rma_replace_out_items ri JOIN rma_replace_outs r ON r.replace_out_id=ri.replace_out_id "
                    . "WHERE r.status='approved' AND ri.line_status='sent' AND (r.replace_out_no LIKE ? OR ri.out_serial LIKE ? OR ri.model_no LIKE ? OR r.supplier_name LIKE ?) "
                    . 'ORDER BY ri.item_id DESC LIMIT 20',
                'ssss', [$like, $like, $like, $like]
            );
        case 'doc':
            return [];
    }
    return [];
}

function rma_complain_item_lookup($like, $mode)
{
    $statusFilter = [
        'replace_out' => "ci.line_status IN ('received','diagnosed')",
        'delivery' => "ci.line_status IN ('received','diagnosed','replaced_in')",
        'bill' => "ci.line_status <> 'settled'",
        'diagnosis' => "ci.line_status IN ('received','diagnosed')",
        'utilize' => "ci.line_status NOT IN ('delivered','settled')",
    ][$mode] ?? "ci.line_status <> 'settled'";
    $rows = rma_rows(
        'SELECT ci.*, c.complain_no, c.customer_id, c.customer_group, c.customer_name, c.mobile, c.address, c.attention, c.complain_date '
            . 'FROM rma_complain_items ci JOIN rma_complains c ON c.complain_id=ci.complain_id '
            . "WHERE c.status='approved' AND $statusFilter AND (c.complain_no LIKE ? OR ci.received_serial LIKE ? OR ci.model_no LIKE ? OR c.customer_name LIKE ? OR c.mobile LIKE ?) "
            . 'ORDER BY ci.item_id DESC LIMIT 20',
        'sssss', [$like, $like, $like, $like, $like]
    );
    $out = [];
    foreach ($rows as $r) {
        $base = [
            'complain_item_id' => $r['item_id'],
            '_label' => $r['complain_no'] . ' · ' . $r['model_no'] . ' · ' . $r['received_serial'] . ' · ' . $r['customer_name'],
        ];
        if ($mode === 'replace_out') {
            $out[] = $base + [
                'product_id' => $r['product_id'], 'product_group' => $r['product_group'], 'brand' => $r['brand'],
                'model_no' => $r['model_no'], 'description' => $r['description'], 'under_warranty' => $r['under_warranty'],
                'purchase_serial' => $r['received_serial'], 'out_serial' => $r['received_serial'], 'qty' => $r['qty'],
                'problem_name' => $r['problem_name'], 'no_of_time' => $r['no_of_time'], 'pre_job_ref_no' => $r['complain_no'],
            ];
        } elseif ($mode === 'delivery') {
            $out[] = $base + [
                'complain_no' => $r['complain_no'], 'received_product_group' => $r['product_group'],
                'received_model_no' => $r['model_no'], 'received_serial' => $r['received_serial'], 'received_qty' => $r['qty'],
                'delivery_qty' => $r['qty'], 'product_id' => $r['product_id'], 'product_group' => $r['product_group'],
                'brand' => $r['brand'], 'model_no' => $r['model_no'], 'description' => $r['description'],
                'customer_id' => $r['customer_id'], 'customer_group' => $r['customer_group'], 'customer_name' => $r['customer_name'],
                'mobile' => $r['mobile'], 'address' => $r['address'],
            ];
        } elseif ($mode === 'bill') {
            $out[] = $base + [
                'complain_no' => $r['complain_no'], 'model_no' => $r['model_no'], 'serial_no' => $r['received_serial'],
                'description' => $r['problem_name'] !== '' ? 'Service: ' . $r['problem_name'] : 'Service charge',
                'service_charge' => $r['amount_charges'],
                'customer_id' => $r['customer_id'], 'customer_group' => $r['customer_group'], 'customer_name' => $r['customer_name'],
                'mobile' => $r['mobile'], 'address' => $r['address'],
            ];
        } elseif ($mode === 'utilize') {
            $out[] = $base + ['job_ref_no' => $r['complain_no'], 'engineer_name' => $r['engineer_name']];
        } else { // diagnosis
            $out[] = $base + [
                'job_ref_no' => $r['complain_no'], 'model_no' => $r['model_no'], 'received_serial' => $r['received_serial'],
                'problem_name' => $r['problem_name'], 'customer_name' => $r['customer_name'], 'engineer_name' => $r['engineer_name'],
            ];
        }
    }
    return $out;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup') {
    if (!rma_schema_ready()) {
        rma_json(['error' => 'Install the RMA tables first using schema.sql.'], 503);
    }
    $kind = (string) ($_GET['kind'] ?? '');
    $term = sales_cut(trim((string) ($_GET['q'] ?? '')), 100);
    $mode = (string) ($_GET['mode'] ?? '');
    rma_json(rma_lookup($kind, $term, $mode));
}

/* ------------------------------------------------------------------ Shared option lists */

/** One page of a list, with the pager under its table. */
function rma_list_rows($sql, $types = '', array $params = [])
{
    return list_page_rows(fn($q, $t, $p) => rma_rows($q, $t, $p), $sql, $types, $params);
}

function rma_store_options()
{
    return perm_filter_stores(array_column(rma_rows('SELECT store_name FROM sales_stores WHERE is_active=1 ORDER BY store_name'), 'store_name'));
}

function rma_problem_options()
{
    return array_column(rma_rows('SELECT problem_name FROM rma_problems WHERE is_active=1 ORDER BY problem_name'), 'problem_name');
}
