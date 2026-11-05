<?php
/**
 * Shared code for the Trader Info pages in billing/trader/.
 * Reuses the sales helpers (login, CSRF, prepared statements, flash messages).
 */
require_once dirname(__DIR__) . '/sales/sales_common.php';

const TRADER_UPLOAD_DIR = __DIR__ . '/uploads';
const TRADER_UPLOAD_MAX = 5242880; // 5 MB
const TRADER_UPLOAD_TYPES = [
    'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'csv' => 'text/csv',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

function trader_schema_ready(mysqli $db)
{
    return sales_tables_ready($db, ['trader_categories', 'trader_groups', 'traders', 'trader_attachments', 'sales_representatives', 'trader_sr_assignments', 'trader_flags', 'trader_conversions']);
}

/** All categories keyed by id. */
function trader_categories(mysqli $db)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (sales_rows($db, 'SELECT * FROM trader_categories ORDER BY sort_order, category_id') as $row) {
            $cache[(int) $row['category_id']] = $row;
        }
    }
    return $cache;
}

function trader_groups(mysqli $db, $categoryId = 0, $activeOnly = false)
{
    $sql = 'SELECT g.group_id, g.category_id, g.group_name, g.is_active, c.category_name FROM trader_groups g JOIN trader_categories c ON c.category_id=g.category_id WHERE 1=1';
    $types = '';
    $params = [];
    if ($categoryId > 0) {
        $sql .= ' AND g.category_id=?';
        $types .= 'i';
        $params[] = (int) $categoryId;
    }
    if ($activeOnly) {
        $sql .= ' AND g.is_active=1';
    }
    return sales_rows($db, $sql . ' ORDER BY c.sort_order, g.group_name', $types, $params);
}

/** Sales persons. Office staff who only have a portal login (staff_only, billing/hr/schema.sql) are not listed. */
function trader_srs(mysqli $db, $activeOnly = true)
{
    return array_values(array_filter(sales_rows($db, 'SELECT * FROM sales_representatives' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sr_name'), fn($s) => empty($s['staff_only'])));
}

/** Find a group by name inside a category, creating it when $create is true. Returns group_id or null. */
function trader_group_id(mysqli $db, $categoryId, $groupName, $create = false)
{
    $groupName = sales_str($groupName, 120);
    if ($groupName === '') {
        return null;
    }
    $row = sales_row($db, 'SELECT group_id FROM trader_groups WHERE category_id=? AND group_name=?', 'is', [(int) $categoryId, $groupName]);
    if ($row) {
        return (int) $row['group_id'];
    }
    if (!$create) {
        throw new RuntimeException('Group "' . $groupName . '" does not exist in this category. Add it first.');
    }
    return sales_insert($db, 'INSERT INTO trader_groups (category_id, group_name, created_by) VALUES (?, ?, ?)', 'isi', [(int) $categoryId, $groupName, (int) ($GLOBALS['user_id'] ?? 0)]);
}

/** Code is the category prefix plus the zero-padded trader id, e.g. CRC000012. */
function trader_set_code(mysqli $db, $traderId)
{
    sales_exec(
        $db,
        "UPDATE traders t JOIN trader_categories c ON c.category_id=t.category_id SET t.trader_code=CONCAT(c.code_prefix, LPAD(t.trader_id, 6, '0')) WHERE t.trader_id=?",
        'i',
        [(int) $traderId]
    )->close();
    $row = sales_row($db, 'SELECT trader_code FROM traders WHERE trader_id=?', 'i', [(int) $traderId]);
    return $row['trader_code'] ?? '';
}

/**
 * Copy a trader into the tables other modules search:
 * customer-type traders into sales_customers, supplier-type traders into rma_suppliers.
 * Each copy is skipped quietly when that module's tables are not installed.
 */
function trader_sync(mysqli $db, $traderId)
{
    $t = sales_row(
        $db,
        'SELECT t.*, c.kind, COALESCE(g.group_name, \'\') AS group_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.trader_id=?',
        'i',
        [(int) $traderId]
    );
    if (!$t) {
        return;
    }
    $address = (string) ($t['address'] ?? '');
    if ($t['kind'] === 'customer' && sales_tables_ready($db, ['sales_customers'])) {
        $customerId = (int) ($t['sales_customer_id'] ?? 0);
        if ($customerId > 0 && !sales_row($db, 'SELECT 1 FROM sales_customers WHERE customer_id=?', 'i', [$customerId])) {
            $customerId = 0;
        }
        if ($customerId === 0) {
            $existing = sales_row($db, 'SELECT customer_id FROM sales_customers WHERE customer_code=?', 's', [$t['trader_code']]);
            $customerId = $existing ? (int) $existing['customer_id'] : 0;
        }
        $values = [sales_cut($t['trader_code'], 40), sales_cut($t['group_name'], 120), sales_cut($t['trader_name'], 190), $t['mobile'], sales_cut($t['contact_person'], 190), $address, $t['bin'], $t['nid']];
        if ($customerId > 0) {
            sales_exec($db, 'UPDATE sales_customers SET customer_code=?, customer_group=?, customer_name=?, mobile=?, attention=?, address=?, bin=?, nid=? WHERE customer_id=?', 'ssssssssi', array_merge($values, [$customerId]))->close();
        } else {
            $customerId = sales_insert($db, 'INSERT INTO sales_customers (customer_code, customer_group, customer_name, mobile, attention, address, bin, nid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 'ssssssss', $values);
        }
        sales_exec($db, 'UPDATE traders SET sales_customer_id=? WHERE trader_id=?', 'ii', [$customerId, (int) $traderId])->close();
    }
    if ($t['kind'] === 'supplier' && sales_tables_ready($db, ['rma_suppliers'])) {
        $supplierId = (int) ($t['rma_supplier_id'] ?? 0);
        if ($supplierId > 0 && !sales_row($db, 'SELECT 1 FROM rma_suppliers WHERE supplier_id=?', 'i', [$supplierId])) {
            $supplierId = 0;
        }
        if ($supplierId === 0) {
            // rma_suppliers.supplier_name is unique, so reuse a same-named supplier.
            $existing = sales_row($db, 'SELECT supplier_id FROM rma_suppliers WHERE supplier_name=?', 's', [$t['trader_name']]);
            $supplierId = $existing ? (int) $existing['supplier_id'] : 0;
        } else {
            $clash = sales_row($db, 'SELECT supplier_id FROM rma_suppliers WHERE supplier_name=? AND supplier_id<>?', 'si', [$t['trader_name'], $supplierId]);
            if ($clash) {
                throw new RuntimeException('Another RMA supplier is already named "' . $t['trader_name'] . '". Use a different name.');
            }
        }
        $values = [sales_cut($t['group_name'], 120), sales_cut($t['trader_name'], 190), $t['mobile'], sales_cut($t['contact_person'], 190), $address, (int) $t['is_active']];
        if ($supplierId > 0) {
            sales_exec($db, 'UPDATE rma_suppliers SET supplier_group=?, supplier_name=?, mobile=?, attention=?, address=?, is_active=? WHERE supplier_id=?', 'sssssii', array_merge($values, [$supplierId]))->close();
        } else {
            $supplierId = sales_insert($db, 'INSERT INTO rma_suppliers (supplier_group, supplier_name, mobile, attention, address, is_active) VALUES (?, ?, ?, ?, ?, ?)', 'sssssi', $values);
        }
        sales_exec($db, 'UPDATE traders SET rma_supplier_id=? WHERE trader_id=?', 'ii', [$supplierId, (int) $traderId])->close();
    }
}

/** Current SR per trader id: [trader_id => sr_name]. */
function trader_current_sr_map(mysqli $db, array $traderIds)
{
    $traderIds = array_values(array_unique(array_filter(array_map('intval', $traderIds))));
    if (!$traderIds) {
        return [];
    }
    $sql = 'SELECT a.trader_id, s.sr_id, s.sr_name, a.effective_from FROM trader_sr_assignments a JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE a.effective_to IS NULL AND a.trader_id IN (' . implode(',', array_fill(0, count($traderIds), '?')) . ')';
    $map = [];
    foreach (sales_rows($db, $sql, str_repeat('i', count($traderIds)), $traderIds) as $row) {
        $map[(int) $row['trader_id']] = $row;
    }
    return $map;
}

/* ---------------- Shared by Customer Management and Sales Person Management ---------------- */

/**
 * The signed-in user's flags for a menu permission column (customer_mgmt, sales_person_mgmt). Everything is allowed
 * until the column exists, so the pages work before the role screen has been used.
 */
function mgmt_permissions(mysqli $db, $column, array $names)
{
    static $cache = [];
    if (!isset($cache[$column])) {
        $flags = array_fill(0, count($names), true);
        if (sales_row($db, 'SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'admin_role\' AND column_name=?', 's', [$column])) {
            $row = sales_row($db, "SELECT r.$column AS f FROM admin_user u JOIN admin_role r ON r.user_role_id=u.user_role_id WHERE u.user_id=?", 'i', [(int) ($GLOBALS['user_id'] ?? 0)]);
            if ($row) {
                $parts = explode(',', (string) $row['f']);
                foreach ($flags as $i => $v) {
                    $flags[$i] = ($parts[$i] ?? '0') === '1';
                }
            }
        }
        $cache[$column] = array_combine(array_keys($names), $flags);
    }
    return $cache[$column];
}

/** Stop with a message when the user lacks a permission (pages call this before doing anything). */
function mgmt_require($allowed, $what)
{
    if ($allowed) {
        return;
    }
    http_response_code(403);
    exit('<p style="font:14px Arial;margin:30px">Your role does not allow ' . sales_h($what) . '. Ask an administrator to change it in Settings &rsaquo; View Role.</p>');
}

function mgmt_page_start($module, array $menu, $title, $active)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . sales_h($title) . '</title>'
        . '<link rel="stylesheet" href="../sales/sales.css"><link rel="stylesheet" href="../inventory/inv.css"></head><body><div class="wrap"><nav class="acc-nav"><span><b>' . sales_h($module) . ':</b>';
    foreach ($menu as $key => [$href, $label]) {
        echo '<a href="' . $href . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    echo '</span></nav><main class="panel"><fieldset class="box"><legend>' . sales_h($title) . '</legend>';
}

function mgmt_page_end($script = '')
{
    echo '</fieldset></main></div><script src="../sales/sales.js"></script>' . ($script !== '' ? '<script>' . $script . '</script>' : '') . '</body></html>';
}

/** A readable temporary password (no 0/O, 1/l/I). */
function portal_temp_password($length = 10)
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function trader_page_start($title, $active = '')
{
    $links = [
        'credit' => ['trader_info.php?cat=3', 'Credit Customer'],
        'supplier' => ['trader_info.php?cat=1', 'Supplier Info'],
        'cashsup' => ['trader_info.php?cat=5', 'Cash Supplier'],
        'group' => ['trader_group.php', 'Trader Group'],
        'sr' => ['trader_sr.php', 'Trader wise SR Fixing'],
        'flag' => ['trader_flag.php', 'Trader Flag Fixing'],
        'search' => ['trader_search.php', 'Trader Search'],
        'convert' => ['trader_convert.php', 'Trader Convert'],
    ];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales/sales.css">';
    echo '<style>fieldset.box{border:1px solid #9aa6b5;border-radius:4px;padding:12px 14px;margin:0}fieldset.box>legend{font-size:20px;font-weight:bold;padding:0 6px}'
        . '.row-form{display:grid;grid-template-columns:150px 1fr;gap:6px 10px;align-items:center}.inline{display:flex;gap:6px;align-items:center}.inline input{flex:1}'
        . '.selected-row td{background:#fff7d6}.check{width:auto!important;height:auto!important}</style></head><body><div class="wrap"><nav class="sales-nav">';
    foreach ($links as $key => [$href, $label]) {
        echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    echo '</nav><main class="panel">';
}

function trader_page_end($script = '')
{
    echo '</main></div><script src="../sales/sales.js"></script>';
    if ($script !== '') {
        echo '<script>' . $script . '</script>';
    }
    echo '</body></html>';
}

function trader_missing_html()
{
    return '<div class="errorbox"><strong>Trader tables are not installed yet.</strong><br>Import <code>billing/trader/schema.sql</code> into the MySQL database, then reload this page.</div>';
}

function trader_category_options(array $categories, $selected, $withAll = false)
{
    $html = $withAll ? '<option value="0">All categories</option>' : '';
    foreach ($categories as $id => $c) {
        $html .= '<option value="' . (int) $id . '"' . ((int) $selected === (int) $id ? ' selected' : '') . '>' . sales_h($c['category_name']) . '</option>';
    }
    return $html;
}
